#!/usr/bin/env python3
"""
mix_engine.py - offline mixer that Claude drives with a JSON file.

Instead of dragging knobs on screenshots, Claude writes a mix config
(tracks -> insert chains -> fader/pan -> sends -> buses -> master), renders it
in seconds, measures the result with mix_analyzer.py, edits the JSON and
renders again. Real VST3 plugins (FabFilter, Valhalla, Waves, ...) are hosted
through Spotify's `pedalboard`; a few processors that pedalboard lacks are
built in (de-esser, spectral ducking a la TrackSpacer, saturation).

Outputs of `render`:
  <out>/mix.wav                         stereo mix (32-bit float, never clips)
  <out>/stems/<track>.wav               every track post-fader (dry) + bus returns
  <out>/presets/<track>/NN_<plugin>.vstpreset   exact state of every VST3 insert
  <out>/cubase_recipe.md                how to rebuild the same mix in Cubase
  <out>/analysis/report.md|json|png     mix_analyzer report (with --analyze)

Sub-commands:
  render CONFIG.json [--analyze] [--previous report.json] [--label v3]
  list-plugins [DIR ...]                find VST3 plugins (and names inside shells such as WaveShell)
  params PLUGIN.vst3 [--name NAME]      list a plugin's parameter names, units and ranges

Requires: numpy, scipy, soundfile, pedalboard (pip install pedalboard).
"""
from __future__ import annotations

import argparse
import datetime as _dt
import glob
import json
import math
import os
import re
import sys

import numpy as np
import soundfile as sf
from scipy import signal

try:
    import pedalboard as pb
except ImportError:  # pragma: no cover
    pb = None

HERE = os.path.dirname(os.path.abspath(__file__))
EPS = 1e-12

DEFAULT_VST3_DIRS = [
    r"C:\Program Files\Common Files\VST3",
    "/Library/Audio/Plug-Ins/VST3",
    os.path.expanduser("~/Library/Audio/Plug-Ins/VST3"),
    os.path.expanduser("~/.vst3"),
    "/usr/lib/vst3",
    "/usr/local/lib/vst3",
]


def need_pb():
    if pb is None:
        sys.exit("нужен pedalboard: pip install pedalboard")


def db2a(d):
    return 10.0 ** (d / 20.0)


def a2db(a):
    return 20.0 * math.log10(max(abs(a), 1e-10))


def note_to_seconds(bpm, note):
    """'1/4', '1/8', '1/8d' (dotted), '1/8t' (triplet), '1/16' ..."""
    m = re.fullmatch(r"1/(\d+)([dt]?)", note.strip())
    if not m:
        raise ValueError(f"не понимаю длительность {note!r}")
    sec = 60.0 / bpm * 4.0 / int(m.group(1))
    return sec * (1.5 if m.group(2) == "d" else 2 / 3 if m.group(2) == "t" else 1.0)


# ------------------------------------------------------------------ custom DSP
def _stft(x, sr, nfft):
    hop = nfft // 4
    f, t, Z = signal.stft(x, fs=sr, nperseg=nfft, noverlap=nfft - hop, boundary="even", padded=True)
    return f, t, Z, hop


def _istft(Z, sr, nfft, n):
    hop = nfft // 4
    _, y = signal.istft(Z, fs=sr, nperseg=nfft, noverlap=nfft - hop, boundary=True)
    y = y[..., :n]
    if y.shape[-1] < n:
        y = np.pad(y, [(0, 0)] * (y.ndim - 1) + [(0, n - y.shape[-1])])
    return y


def _smooth_gr(gr_db, frame_s, attack_ms, release_ms):
    """one-pole smoothing of a gain-reduction curve (dB, >=0) along the last axis"""
    a_att = math.exp(-frame_s / max(attack_ms / 1000, 1e-4))
    a_rel = math.exp(-frame_s / max(release_ms / 1000, 1e-4))
    out = np.zeros_like(gr_db)
    prev = np.zeros(gr_db.shape[:-1])
    for i in range(gr_db.shape[-1]):
        x = gr_db[..., i]
        coef = np.where(x > prev, a_att, a_rel)
        prev = coef * prev + (1 - coef) * x
        out[..., i] = prev
    return out


def deesser(x, sr, freq=5500.0, relative_db=-6.0, ratio=4.0, range_db=8.0, attack_ms=2.0, release_ms=60.0,
            detect_hi=10000.0, **_):
    """Split-band de-esser. Detects 'freq..detect_hi' energy relative to the vocal body (200 Hz-4 kHz,
    75th percentile of active frames) and turns down only the band above 'freq' when the esses exceed
    body + relative_db. relative_db=-6 means: esses may peak at most ~6 dB below the vowels."""
    nfft = 1 << int(round(math.log2(0.021 * sr)))
    f, _, Z, hop = _stft(x, sr, nfft)
    P = (np.abs(Z) ** 2).mean(axis=0)  # mono detection [bins, frames]
    sib = 10 * np.log10(P[(f >= freq) & (f < min(detect_hi, sr / 2))].sum(axis=0) + EPS)
    body = 10 * np.log10(P[(f >= 200) & (f < 4000)].sum(axis=0) + EPS)
    tot = 10 * np.log10(P.sum(axis=0) + EPS)
    sys.path.insert(0, HERE)
    from mix_analyzer import active_mask  # noqa: E402
    act = active_mask(tot, 30)
    body_ref = np.percentile(body[act], 75) if act.any() else np.max(body)
    over = np.maximum(0.0, sib - (body_ref + relative_db))
    gr = np.minimum(over * (1 - 1 / max(ratio, 1.0)), range_db)
    gr = _smooth_gr(gr[None, :], hop / sr, attack_ms, release_ms)[0]
    g = 10 ** (-gr / 20)
    Z[:, f >= freq, :] *= g[None, None, :]
    return _istft(Z, sr, nfft, x.shape[-1]).astype(np.float32), {"max_gr_db": float(gr.max()),
                                                                 "mean_gr_when_active_db": float(gr[gr > 0.5].mean())
                                                                 if (gr > 0.5).any() else 0.0}


def spectral_duck(x, sr, key, f_lo=300.0, f_hi=5000.0, depth_db=3.0, range_db=20.0, attack_ms=10.0,
                  release_ms=150.0, **_):
    """TrackSpacer-style ducking: turn this track down in the third-octave bands where the key (vocal) is
    loud, by up to depth_db. Bands outside f_lo..f_hi are untouched."""
    nfft = 1 << int(round(math.log2(0.043 * sr)))
    n = x.shape[-1]
    k = key[..., :n]
    if k.shape[-1] < n:
        k = np.pad(k, [(0, 0), (0, n - k.shape[-1])])
    f, _, Z, hop = _stft(x, sr, nfft)
    _, _, K, _ = _stft(k, sr, nfft)
    Pk = (np.abs(K) ** 2).mean(axis=0)
    centers = 1000 * 2 ** (np.arange(-17, 14) / 3)
    centers = centers[(centers >= f_lo) & (centers <= f_hi)]
    gain = np.ones((len(f), Z.shape[-1]))
    for c in centers:
        m = (f >= c * 2 ** (-1 / 6)) & (f < c * 2 ** (1 / 6))
        if not m.any():
            continue
        lv = 10 * np.log10(Pk[m].sum(axis=0) + EPS)
        ref = np.percentile(lv, 95)
        amt = np.clip((lv - (ref - range_db)) / range_db, 0, 1) * depth_db
        amt = _smooth_gr(amt[None, :], hop / sr, attack_ms, release_ms)[0]
        gain[m, :] = 10 ** (-amt / 20)[None, :]
    Z *= gain[None, :, :]
    return _istft(Z, sr, nfft, n).astype(np.float32), {"max_duck_db": float(-20 * np.log10(gain.min() + EPS))}


def cut_filter(x, sr, freq=80.0, slope_db_oct=12, kind="highpass", **_):
    """Butterworth low/high cut with the usual EQ slopes: 6/12/18/24/36/48 dB per octave"""
    order = max(1, int(round(slope_db_oct / 6)))
    sos = signal.butter(order, min(freq, sr / 2 * 0.99), kind, fs=sr, output="sos")
    return signal.sosfilt(sos, x, axis=-1).astype(np.float32), {}


def saturate(x, sr, drive_db=6.0, mix=0.3, **_):
    """tanh saturation with level-matched wet signal, blended in parallel"""
    d = db2a(drive_db)
    wet = np.tanh(x * d)
    rx = math.sqrt(float(np.mean(x ** 2)) + EPS)
    rw = math.sqrt(float(np.mean(wet ** 2)) + EPS)
    wet *= rx / rw
    return ((1 - mix) * x + mix * wet).astype(np.float32), {}


# --------------------------------------------------------------- processors
class Insert:
    """One insert slot. Builds a pedalboard plugin or a custom numpy processor from a dict."""

    def __init__(self, spec, sr, base_dir):
        self.spec = dict(spec)
        self.type = spec["type"].lower()
        self.sr = sr
        self.plugin = None
        self.custom = None
        self.info = {}
        self.defaults = {}
        t, s = self.type, self.spec
        if t in ("deesser", "spectral_duck", "saturate", "hpf", "lpf"):
            self.custom = {"deesser": deesser, "spectral_duck": spectral_duck, "saturate": saturate,
                           "hpf": cut_filter, "lpf": cut_filter}[t]
            if t in ("hpf", "lpf"):
                self.spec.setdefault("freq", 80.0 if t == "hpf" else 16000.0)
                self.spec["kind"] = "highpass" if t == "hpf" else "lowpass"
            return
        need_pb()
        if t == "peak":
            self.plugin = pb.PeakFilter(cutoff_frequency_hz=s["freq"], gain_db=s.get("gain_db", 0), q=s.get("q", 1.0))
        elif t == "lowshelf":
            self.plugin = pb.LowShelfFilter(cutoff_frequency_hz=s["freq"], gain_db=s.get("gain_db", 0),
                                            q=s.get("q", 0.707))
        elif t == "highshelf":
            self.plugin = pb.HighShelfFilter(cutoff_frequency_hz=s["freq"], gain_db=s.get("gain_db", 0),
                                             q=s.get("q", 0.707))
        elif t == "comp":
            self.plugin = pb.Pedalboard([
                pb.Compressor(threshold_db=s.get("threshold_db", -18), ratio=s.get("ratio", 3),
                              attack_ms=s.get("attack_ms", 10), release_ms=s.get("release_ms", 120)),
                pb.Gain(gain_db=s.get("makeup_db", 0))])
        elif t == "limiter":
            self.plugin = pb.Limiter(threshold_db=s.get("threshold_db", -1), release_ms=s.get("release_ms", 100))
        elif t == "gain":
            self.plugin = pb.Gain(gain_db=s.get("gain_db", 0))
        elif t == "reverb":
            self.plugin = pb.Reverb(room_size=s.get("room_size", 0.5), damping=s.get("damping", 0.5),
                                    wet_level=s.get("wet", 1.0), dry_level=s.get("dry", 0.0),
                                    width=s.get("width", 1.0))
        elif t == "delay":
            sec = s.get("seconds")
            if sec is None:
                sec = note_to_seconds(s["bpm"], s.get("note", "1/4"))
            self.plugin = pb.Delay(delay_seconds=sec, feedback=s.get("feedback", 0.25), mix=s.get("mix", 1.0))
            self.info["delay_ms"] = round(sec * 1000, 1)
        elif t == "ir":
            path = os.path.join(base_dir, s["file"]) if not os.path.isabs(s["file"]) else s["file"]
            self.plugin = pb.Convolution(path, mix=s.get("mix", 1.0))
        elif t == "vst3":
            path = os.path.expanduser(s["path"])
            kw = {}
            if s.get("plugin_name"):
                kw["plugin_name"] = s["plugin_name"]
            self.plugin = pb.load_plugin(path, initialization_timeout=s.get("timeout", 10.0), **kw)
            if s.get("preset"):
                pp = os.path.join(base_dir, s["preset"]) if not os.path.isabs(s["preset"]) else s["preset"]
                self.plugin.load_preset(pp)
            for k in self.plugin.parameters.keys():
                try:
                    self.defaults[k] = getattr(self.plugin, k)
                except Exception:  # noqa: BLE001
                    pass
            for k, v in (s.get("params") or {}).items():
                if k not in self.plugin.parameters:
                    close = [p for p in self.plugin.parameters if k.lower() in p.lower()][:8]
                    raise KeyError(f"{self.plugin.name}: нет параметра {k!r}. Похожие: {close}. "
                                   f"Полный список: mix_engine.py params \"{path}\"")
                setattr(self.plugin, k, v)
        else:
            raise ValueError(f"неизвестный тип insert: {t}")

    @property
    def label(self):
        if self.type == "vst3" and self.plugin is not None:
            return self.plugin.name
        return self.type

    def process(self, x, keys):
        """x: float32 [2, n]"""
        if self.custom is not None:
            kw = {k: v for k, v in self.spec.items() if k not in ("type", "key", "note", "bypass", "comment")}
            if self.type == "spectral_duck":
                key_name = self.spec["key"]
                if key_name not in keys:
                    raise KeyError(f"spectral_duck: ключевой трек {key_name!r} ещё не обработан "
                                   "(он должен стоять в списке tracks раньше)")
                kw["key"] = keys[key_name]
            y, info = self.custom(x, self.sr, **kw)
            self.info.update({k: round(v, 2) for k, v in info.items()})
            return y
        y = self.plugin.process(x.astype(np.float32), self.sr, reset=True)
        if y.ndim == 1:
            y = np.stack([y, y])
        if y.shape[0] == 1:
            y = np.repeat(y, 2, axis=0)
        return y[:2].astype(np.float32)

    def changed_params(self):
        if self.type != "vst3":
            return {k: v for k, v in self.spec.items() if k not in ("type", "kind")}
        out = {}
        for k in self.plugin.parameters.keys():
            try:
                v = getattr(self.plugin, k)
            except Exception:  # noqa: BLE001
                continue
            if k in (self.spec.get("params") or {}) or self.defaults.get(k) != v:
                out[k] = v if isinstance(v, (int, float, str, bool)) else str(v)
        return out

    def save_preset(self, path):
        if self.type != "vst3":
            return None
        try:
            data = self.plugin.preset_data
        except Exception as e:  # noqa: BLE001
            return f"не удалось сохранить пресет: {e}"
        with open(path, "wb") as fh:
            fh.write(data)
        return path


# ---------------------------------------------------------------- the mixer
def load_stem(path, sr_target):
    x, sr = sf.read(path, always_2d=True, dtype="float32")
    x = x.T  # [ch, n]
    if sr != sr_target:
        g = math.gcd(int(sr), int(sr_target))
        x = signal.resample_poly(x, sr_target // g, sr // g, axis=1).astype(np.float32)
    mono_src = x.shape[0] == 1
    if mono_src:
        x = np.repeat(x, 2, axis=0)
    return x[:2], mono_src


def pan_stereo(x, pan, mono_src):
    """constant-power pan (-1 = L, +1 = R). Mono sources are panned; stereo sources are balanced."""
    pan = float(np.clip(pan, -1, 1))
    th = (pan + 1) * math.pi / 4
    if mono_src:
        m = x.mean(axis=0)
        return np.stack([m * math.cos(th), m * math.sin(th)]) * math.sqrt(2)
    gl = min(1.0, math.cos(th) * math.sqrt(2))
    gr = min(1.0, math.sin(th) * math.sqrt(2))
    return np.stack([x[0] * gl, x[1] * gr])


def set_width(x, width):
    if width == 1.0:
        return x
    m = (x[0] + x[1]) / 2
    s = (x[0] - x[1]) / 2 * width
    return np.stack([m + s, m - s])


def shift(x, samples):
    if samples == 0:
        return x
    if samples > 0:
        return np.pad(x, [(0, 0), (samples, 0)])[:, :x.shape[1]]
    return np.pad(x[:, -samples:], [(0, 0), (0, -samples)])


def safe(name):
    return re.sub(r"[^\w\-]+", "_", name).strip("_")


def render(cfg_path, label=None, do_analyze=False, previous=None, out_override=None):
    with open(cfg_path, encoding="utf-8") as fh:
        cfg = json.load(fh)
    base = os.path.dirname(os.path.abspath(cfg_path))
    label = label or cfg.get("label") or _dt.datetime.now().strftime("v%Y%m%d_%H%M%S")
    out = out_override or os.path.join(base, cfg.get("out_dir", "renders"), safe(label))
    os.makedirs(os.path.join(out, "stems"), exist_ok=True)
    os.makedirs(os.path.join(out, "presets"), exist_ok=True)

    def P(p):
        return p if os.path.isabs(p) else os.path.join(base, p)

    # session sample rate = first track's rate unless given
    sr = cfg.get("sample_rate") or sf.info(P(cfg["tracks"][0]["file"])).samplerate
    tail = int(cfg.get("tail_seconds", 3.0) * sr)
    tracks_out = {}
    keys = {}
    bus_in = {}
    log = {"label": label, "config": os.path.abspath(cfg_path), "sample_rate": sr, "tracks": {}, "buses": {},
           "master": {}}
    n_max = 0
    stems_cache = {}
    for tr in cfg["tracks"]:
        if tr.get("mute"):
            continue
        x, mono_src = load_stem(P(tr["file"]), sr)
        stems_cache[tr["name"]] = (x, mono_src)
        n_max = max(n_max, x.shape[1])
    n_total = n_max + tail

    for tr in cfg["tracks"]:
        if tr.get("mute"):
            continue
        name = tr["name"]
        x, mono_src = stems_cache[name]
        x = np.pad(x, [(0, 0), (0, n_total - x.shape[1])])
        x = shift(x, int(tr.get("delay_samples", 0) + round(tr.get("delay_ms", 0) * sr / 1000)))
        if tr.get("invert"):
            x = -x
        x *= db2a(tr.get("clip_gain_db", 0.0))
        tinfo = {"inserts": []}
        pdir = os.path.join(out, "presets", safe(name))
        for i, spec in enumerate(tr.get("inserts", [])):
            if spec.get("bypass"):
                continue
            ins = Insert(spec, sr, base)
            x = ins.process(x, keys)
            entry = {"slot": i + 1, "type": ins.type, "name": ins.label, "params": ins.changed_params()}
            if ins.info:
                entry["info"] = ins.info
            if ins.type == "vst3":
                os.makedirs(pdir, exist_ok=True)
                entry["preset"] = ins.save_preset(os.path.join(pdir, f"{i + 1:02d}_{safe(ins.label)}.vstpreset"))
            tinfo["inserts"].append(entry)
        keys[name] = x.copy()  # post-insert, pre-fader: used as sidechain key
        x = x * db2a(tr.get("fader_db", 0.0))
        x = set_width(x, tr.get("width", 1.0))
        x = pan_stereo(x, tr.get("pan", 0.0), mono_src and tr.get("width", 1.0) == 1.0)
        tracks_out[name] = x
        for bus, lvl in (tr.get("sends") or {}).items():
            bus_in.setdefault(bus, np.zeros((2, n_total), dtype=np.float32))
            bus_in[bus] += x * db2a(lvl)
        tinfo.update({"fader_db": tr.get("fader_db", 0.0), "pan": tr.get("pan", 0.0), "sends": tr.get("sends", {}),
                      "role": tr.get("role"), "peak_dbfs": round(a2db(float(np.max(np.abs(x)))), 2)})
        log["tracks"][name] = tinfo

    mix = np.zeros((2, n_total), dtype=np.float32)
    for x in tracks_out.values():
        mix += x
    bus_out = {}
    for bus in cfg.get("buses", []):
        bname = bus["name"]
        x = bus_in.get(bname, np.zeros((2, n_total), dtype=np.float32))
        binfo = {"inserts": []}
        pdir = os.path.join(out, "presets", "bus_" + safe(bname))
        for i, spec in enumerate(bus.get("inserts", [])):
            if spec.get("bypass"):
                continue
            ins = Insert(spec, sr, base)
            x = ins.process(x, keys)
            entry = {"slot": i + 1, "type": ins.type, "name": ins.label, "params": ins.changed_params()}
            if ins.info:
                entry["info"] = ins.info
            if ins.type == "vst3":
                os.makedirs(pdir, exist_ok=True)
                entry["preset"] = ins.save_preset(os.path.join(pdir, f"{i + 1:02d}_{safe(ins.label)}.vstpreset"))
            binfo["inserts"].append(entry)
        x = x * db2a(bus.get("return_db", 0.0))
        bus_out[bname] = x
        mix += x
        binfo["return_db"] = bus.get("return_db", 0.0)
        log["buses"][bname] = binfo

    m = cfg.get("master", {})
    minfo = {"inserts": []}
    pdir = os.path.join(out, "presets", "master")
    for i, spec in enumerate(m.get("inserts", [])):
        if spec.get("bypass"):
            continue
        ins = Insert(spec, sr, base)
        mix = ins.process(mix, keys)
        entry = {"slot": i + 1, "type": ins.type, "name": ins.label, "params": ins.changed_params()}
        if ins.type == "vst3":
            os.makedirs(pdir, exist_ok=True)
            entry["preset"] = ins.save_preset(os.path.join(pdir, f"{i + 1:02d}_{safe(ins.label)}.vstpreset"))
        minfo["inserts"].append(entry)
    mix *= db2a(m.get("fader_db", 0.0))
    # trim trailing silence of the tail
    nz = np.where(np.max(np.abs(mix), axis=0) > 1e-5)[0]
    end = int(nz[-1]) + 1 if len(nz) else mix.shape[1]
    mix = mix[:, :end]
    minfo["peak_dbfs"] = round(a2db(float(np.max(np.abs(mix)))), 2)
    log["master"] = minfo

    mix_path = os.path.join(out, "mix.wav")
    sf.write(mix_path, mix.T, sr, subtype="FLOAT")
    stem_paths = {}
    for name, x in list(tracks_out.items()) + [("bus_" + b, x) for b, x in bus_out.items()]:
        p = os.path.join(out, "stems", safe(name) + ".wav")
        sf.write(p, x[:, :end].T, sr, subtype="FLOAT")
        stem_paths[name] = p
    log["files"] = {"mix": mix_path, "stems": stem_paths}
    with open(os.path.join(out, "render_log.json"), "w", encoding="utf-8") as fh:
        json.dump(log, fh, ensure_ascii=False, indent=1, default=str)
    write_recipe(log, cfg, os.path.join(out, "cubase_recipe.md"))
    print(f"[render] {mix_path}  (peak {minfo['peak_dbfs']} dBFS)")

    if do_analyze:
        sys.path.insert(0, HERE)
        import mix_analyzer as ma  # noqa: E402

        an = cfg.get("analysis", {})
        args = argparse.Namespace(
            vocal=[stem_paths[t["name"]] for t in cfg["tracks"] if t.get("role") == "vocal" and t["name"] in stem_paths],
            guitar=[stem_paths[t["name"]] for t in cfg["tracks"] if t.get("role") == "guitar" and t["name"] in stem_paths],
            mix=[mix_path], master=[], file=[],
            ref=[P(p) for p in an.get("ref", [])], ref_vocal=[P(p) for p in an.get("ref_vocal", [])],
            ref_guitar=[P(p) for p in an.get("ref_guitar", [])], stage="mix",
            target_lufs=an.get("target_lufs", -14.0), previous=previous, label=label,
            out=os.path.join(out, "analysis"), no_plots=False, spectrogram=False)
        ma.cmd_analyze(args)
    return out


# ----------------------------------------------------------------- recipe
BUILTIN_HOWTO = {
    "hpf": lambda s: f"Low Cut {s.get('freq', 80)} Гц, {s.get('slope_db_oct', 12)} dB/окт (Pro-Q / Frequency 2 / "
                     "EQ канала)",
    "lpf": lambda s: f"High Cut {s.get('freq', 16000)} Гц, {s.get('slope_db_oct', 12)} dB/окт",
    "peak": lambda s: f"Bell {s['freq']} Гц, {s.get('gain_db', 0):+} dB, Q {s.get('q', 1.0)} (Pro-Q / Frequency 2)",
    "lowshelf": lambda s: f"Low Shelf {s['freq']} Гц, {s.get('gain_db', 0):+} dB",
    "highshelf": lambda s: f"High Shelf {s['freq']} Гц, {s.get('gain_db', 0):+} dB",
    "comp": lambda s: (f"Компрессор: threshold {s.get('threshold_db', -18)} dB, ratio {s.get('ratio', 3)}:1, "
                       f"attack {s.get('attack_ms', 10)} мс, release {s.get('release_ms', 120)} мс, "
                       f"makeup {s.get('makeup_db', 0)} dB (Pro-C 2 / Cubase Compressor)"),
    "limiter": lambda s: f"Лимитер: потолок {s.get('threshold_db', -1)} dB (Pro-L 2 / Brickwall Limiter)",
    "gain": lambda s: f"Gain {s.get('gain_db', 0):+} dB",
    "reverb": lambda s: (f"Ревер (на FX-канале 100% wet): room {s.get('room_size', 0.5)}, damping "
                         f"{s.get('damping', 0.5)} - в Cubase заменить на Valhalla / REVerence"),
    "delay": lambda s: (f"Дилей (на FX-канале 100% wet): {s.get('note', '')} @ {s.get('bpm', '')} BPM, feedback "
                        f"{s.get('feedback', 0.25)} (Valhalla Delay / H-Delay / Timeless 3)"),
    "ir": lambda s: f"Свёртка с IR {s.get('file')} (REVerence / любой IR-лоадер), mix {s.get('mix', 1.0)}",
    "deesser": lambda s: (f"Де-эссер split-band от {s.get('freq', 5500)} Гц, цель: эсы не выше "
                          f"{s.get('relative_db', -6)} dB от гласных, максимум {s.get('range_db', 8)} dB подавления "
                          "(Pro-DS: Single Vocal, Split band; ориентир по графику подавления)"),
    "spectral_duck": lambda s: (f"Динамическое 'расчищение' под {s.get('key')}: {s.get('f_lo', 300)}-"
                                f"{s.get('f_hi', 5000)} Гц до -{s.get('depth_db', 3)} dB (TrackSpacer или Pro-Q: "
                                "динамические полосы + внешний сайдчейн от вокала)"),
    "saturate": lambda s: f"Сатурация drive {s.get('drive_db', 6)} dB, mix {int(100 * s.get('mix', 0.3))}% "
                          "(Saturn 2 / Decapitator / Magneto II)",
}


def write_recipe(log, cfg, path):
    L = [f"# Рецепт для Cubase: {log['label']}", "",
         "Порядок: слоты инсертов сверху вниз. Для VST3 рядом лежит .vstpreset с точным состоянием - "
         "загрузи его в плагине (меню пресетов -> Load Preset / Import) или положи в папку VST3 Presets.", ""]

    def block(title, info):
        L.append(f"## {title}")
        for e in info.get("inserts", []):
            if e["type"] == "vst3":
                ps = ", ".join(f"{k}={v}" for k, v in e["params"].items())
                L.append(f"{e['slot']}. **{e['name']}** - пресет `{e.get('preset')}`  \n   параметры: {ps}")
            else:
                howto = BUILTIN_HOWTO.get(e["type"], lambda s: str(s))(e["params"])
                extra = f" _(факт: {e['info']})_" if e.get("info") else ""
                L.append(f"{e['slot']}. {howto}{extra}")
        rest = []
        if "fader_db" in info:
            rest.append(f"фейдер {info['fader_db']:+} dB")
        if "pan" in info:
            rest.append(f"панорама {info['pan']:+.2f}")
        if info.get("sends"):
            rest.append("посылы: " + ", ".join(f"{b} {v:+} dB (post-fader)" for b, v in info["sends"].items()))
        if "return_db" in info:
            rest.append(f"возврат FX-канала {info['return_db']:+} dB")
        if rest:
            L.append("- " + "; ".join(rest))
        L.append("")

    clip = {t["name"]: t for t in cfg["tracks"]}
    for name, info in log["tracks"].items():
        t = clip[name]
        pre = []
        if t.get("clip_gain_db"):
            pre.append(f"Clip Gain {t['clip_gain_db']:+} dB")
        if t.get("delay_samples") or t.get("delay_ms"):
            pre.append(f"сдвиг {t.get('delay_samples', 0)} сэмплов + {t.get('delay_ms', 0)} мс")
        if t.get("invert"):
            pre.append("инверсия фазы")
        if pre:
            L.append(f"_{name}: перед плагинами - {', '.join(pre)}_")
        block(f"Трек {name}", info)
    for name, info in log["buses"].items():
        block(f"FX-канал {name}", info)
    block("Stereo Out (мастер-шина)", log["master"])
    with open(path, "w", encoding="utf-8") as fh:
        fh.write("\n".join(L) + "\n")


# --------------------------------------------------------- plugin discovery
def cmd_list_plugins(a):
    need_pb()
    dirs = a.dirs or DEFAULT_VST3_DIRS
    found = []
    for d in dirs:
        if os.path.isdir(d):
            found += glob.glob(os.path.join(d, "**", "*.vst3"), recursive=True)
    from pathlib import Path
    # a .vst3 can be a bundle folder that contains another *.vst3 binary: keep only the outer one
    found = sorted(set(p for p in found if not any(part.endswith(".vst3") for part in Path(p).parent.parts)))
    for p in found:
        try:
            names = pb.VST3Plugin.get_plugin_names_for_file(p)
        except Exception as e:  # noqa: BLE001
            names = [f"(не удалось просканировать: {e})"]
        if len(names) > 1:
            print(f"{p}  [контейнер: {len(names)} плагинов, указывай plugin_name]")
            if a.verbose:
                for n in names:
                    print(f"    - {n}")
        else:
            print(f"{p}  -> {names[0] if names else '?'}")
    if not found:
        print("VST3 не найдены в: " + "; ".join(dirs))


def cmd_params(a):
    need_pb()
    kw = {"plugin_name": a.name} if a.name else {}
    p = pb.load_plugin(a.path, **kw)
    print(f"# {p.name} ({getattr(p, 'manufacturer_name', '')}) - {len(p.parameters)} параметров")
    for k, prm in p.parameters.items():
        try:
            cur = getattr(p, k)
        except Exception:  # noqa: BLE001
            cur = "?"
        rng = ""
        if getattr(prm, "valid_values", None) and getattr(prm, "type", None) is str:
            vv = list(prm.valid_values)
            rng = f"варианты: {vv[:12]}{' ...' if len(vv) > 12 else ''}"
        elif getattr(prm, "min_value", None) is not None:
            rng = f"{prm.min_value} .. {prm.max_value}"
        units = getattr(prm, "units", None) or ""
        if a.filter and a.filter.lower() not in k.lower():
            continue
        print(f"{k:40s} = {cur!s:14s} {units:6s} {rng}")


def main():
    ap = argparse.ArgumentParser(description="Офлайн-сведение по JSON-конфигу (VST3 через pedalboard)")
    sub = ap.add_subparsers(dest="cmd", required=True)
    r_ = sub.add_parser("render", help="свести по конфигу")
    r_.add_argument("config")
    r_.add_argument("--label", help="имя версии (папка в renders/)")
    r_.add_argument("--analyze", action="store_true", help="сразу прогнать mix_analyzer")
    r_.add_argument("--previous", help="report.json прошлой версии для сравнения")
    r_.add_argument("--out", help="папка результата (по умолчанию renders/<label>)")
    lp = sub.add_parser("list-plugins", help="найти VST3")
    lp.add_argument("dirs", nargs="*")
    lp.add_argument("-v", "--verbose", action="store_true", help="показать плагины внутри контейнеров (WaveShell)")
    pp = sub.add_parser("params", help="параметры плагина")
    pp.add_argument("path")
    pp.add_argument("--name", help="имя плагина внутри контейнера (WaveShell)")
    pp.add_argument("--filter", help="показать только параметры, содержащие текст")
    a = ap.parse_args()
    if a.cmd == "render":
        render(a.config, a.label, a.analyze, a.previous, a.out)
    elif a.cmd == "list-plugins":
        cmd_list_plugins(a)
    else:
        cmd_params(a)


if __name__ == "__main__":
    main()
