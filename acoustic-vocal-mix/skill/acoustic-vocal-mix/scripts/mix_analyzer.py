#!/usr/bin/env python3
"""
mix_analyzer.py - "ears" for Claude: turns audio files into numbers, tables and plots.

Claude cannot hear. This script measures what an engineer listens for and writes
a report Claude can read (report.md / report.json / PNG plots):

  * loudness (BS.1770 / EBU R128): integrated LUFS, LRA, max momentary / short-term
  * sample peak, true peak (4x oversampling), RMS, crest factor, PLR
  * recording health: clipping, DC offset, noise floor, mains hum
  * tonal balance: 1/3-octave profile + named bands (mud, honk, presence, air ...)
  * resonance candidates (narrow peaks that ring: piezo quack, room modes, harshness)
  * vocal: sibilance hot-spots, plosives, level consistency (how even the vocal is)
  * stereo: correlation, width, low-end mono-ness, L/R balance
  * vocal vs guitar: how far the vocal sits above the guitar, which bands mask it
  * reference comparison (your mix vs commercial references) and run-to-run deltas

Sub-commands:
  analyze   full report for stems / mix / master (+ references, + previous run)
  align     time offset and polarity between two recordings (piezo DI vs mic)
  make-ir   build a "piezo -> microphone" impulse response from a DI+mic recording
  selftest  check the loudness meter against the EBU reference value

Requires: numpy, scipy, soundfile (matplotlib optional for plots).
"""
from __future__ import annotations

import argparse
import datetime as _dt
import json
import math
import os
import sys

import numpy as np
import soundfile as sf
from scipy import signal
from scipy.ndimage import uniform_filter1d

EPS = 1e-20
VERSION = "1.0"

# 31 third-octave bands, ~20 Hz .. 20 kHz (base-2 centres around 1 kHz)
THIRD_OCT_CENTERS = 1000.0 * 2.0 ** (np.arange(-17, 14) / 3.0)

# Named broad bands an engineer talks about (lo, hi, meaning)
BROAD_BANDS = [
    ("sub", 20, 60, "саб"),
    ("low", 60, 120, "низ / фундамент"),
    ("boom", 120, 250, "бубнение / тело"),
    ("mud", 250, 500, "грязь / коробка"),
    ("lowmid", 500, 1000, "картон / деревянность"),
    ("honk", 1000, 2500, "гнусавость / пьезо-кряк"),
    ("presence", 2500, 5000, "присутствие / разборчивость / резкость"),
    ("sibilance", 5000, 10000, "сибилянты / яркость"),
    ("air", 10000, 20000, "воздух"),
]

# What to do when a broad band is too loud (+) or too quiet (-) compared to the reference.
BAND_ACTIONS = {
    "sub": ("срезать HPF (обрезной фильтр) выше / -2..-4 dB полкой ниже 60 Гц",
            "редко нужно поднимать; проверь, не слишком ли высокий HPF"),
    "low": ("HPF чуть выше или низкая полка -1.5..-3 dB около 80-100 Гц",
            "опусти HPF ниже или +1..+2 dB полкой ~100 Гц (тело)"),
    "boom": ("колокол -2..-4 dB, Q 1-1.5 в 120-250 Гц (или динамическая полоса)",
             "+1..+2 dB колоколом 150-200 Гц (теплота/тело)"),
    "mud": ("колокол -2..-4 dB, Q 1-2 в 250-500 Гц (ищи точную частоту свипом)",
            "не срезай так много 250-500 Гц, звук станет тонким"),
    "lowmid": ("колокол -1.5..-3 dB в 500-1000 Гц (картонность/коробка)",
               "+1 dB широким колоколом 700-900 Гц для плотности"),
    "honk": ("колокол/динамическая полоса -2..-5 dB, Q 1.5-3 в 1-2.5 кГц (пьезо-кряк, нос) или soothe2",
             "+1..+2 dB широким колоколом 1.5-2 кГц для читаемости"),
    "presence": ("динамическая полоса -2..-3 dB в 2.5-5 кГц или soothe2 (резкость)",
                 "+1..+3 dB колоколом 3-5 кГц (разборчивость, присутствие)"),
    "sibilance": ("де-эссер (Pro-DS / R-DeEsser) 5-9 кГц, 3-6 dB подавления",
                  "+1..+2 dB полкой от 6-8 кГц или эксайтер (Saturn 2, малый mix)"),
    "air": ("полка -1..-2 dB от 10-12 кГц или LPF 16-18 кГц",
            "+1.5..+3 dB полкой 10-12 кГц (Pultec/Pro-Q, 'воздух')"),
}


# --------------------------------------------------------------------------- utils
def pdb(p):
    """power -> dB"""
    return 10.0 * np.log10(np.maximum(p, EPS))


def adb(a):
    """amplitude -> dB"""
    return 20.0 * np.log10(np.maximum(np.abs(a), 1e-10))


def r(x, nd=1):
    """round for JSON (handles -inf/nan)"""
    if x is None:
        return None
    try:
        xf = float(x)
    except (TypeError, ValueError):
        return x
    if math.isnan(xf):
        return None
    if math.isinf(xf):
        return -999.0 if xf < 0 else 999.0
    return round(xf, nd)


def tstr(sec):
    m = int(sec // 60)
    return f"{m}:{sec - 60 * m:04.1f}"


def load_audio(path):
    x, sr = sf.read(path, always_2d=True, dtype="float64")
    if x.shape[1] > 2:  # keep it simple: fold surround to stereo
        x = x[:, :2]
    return x, sr


def mono(x):
    return x.mean(axis=1)


def active_mask(levels_db, rng_db=25.0, floor_db=-70.0):
    """Frames where the source is actually playing/singing.

    Default: within rng_db of the 95th percentile. If the level histogram is clearly bimodal
    (pauses with noise vs. performance, e.g. after compression lifted the noise), the gap between the
    two clusters (Otsu threshold) is used instead so that pauses never count as 'quiet singing'."""
    lv = np.asarray(levels_db, dtype=float)
    fin = np.isfinite(lv) & (lv > floor_db)
    if fin.sum() < 10:
        return fin
    v = lv[fin]
    thr = np.percentile(v, 95) - rng_db
    hist, edges = np.histogram(v, bins=64)
    c = (edges[:-1] + edges[1:]) / 2
    w0 = np.cumsum(hist)
    w1 = w0[-1] - w0
    s0 = np.cumsum(hist * c)
    m0 = s0 / np.maximum(w0, 1)
    m1 = (s0[-1] - s0) / np.maximum(w1, 1)
    between = w0 * w1 * (m0 - m1) ** 2
    i = int(np.argmax(between[:-1]))
    if m1[i] - m0[i] > 15 and w0[i] > 0.05 * w0[-1]:
        thr = max(thr, edges[i + 1])
    return fin & (lv > thr)


def frames_rms_db(xm, sr, win_s=0.05, hop_s=0.025):
    w = max(1, int(win_s * sr))
    h = max(1, int(hop_s * sr))
    if len(xm) < w:
        return np.array([pdb(np.mean(xm ** 2))]), h
    cs = np.concatenate([[0.0], np.cumsum(xm ** 2)])
    st = np.arange(0, len(xm) - w + 1, h)
    return pdb((cs[st + w] - cs[st]) / w), h


# ------------------------------------------------------------------- loudness
def _rbj_high_shelf(fs, gain_db, q, fc):
    A = 10 ** (gain_db / 40.0)
    w0 = 2 * math.pi * fc / fs
    alpha = math.sin(w0) / (2 * q)
    c = math.cos(w0)
    sa = 2 * math.sqrt(A) * alpha
    b = [A * ((A + 1) + (A - 1) * c + sa), -2 * A * ((A - 1) + (A + 1) * c), A * ((A + 1) + (A - 1) * c - sa)]
    a = [(A + 1) - (A - 1) * c + sa, 2 * ((A - 1) - (A + 1) * c), (A + 1) - (A - 1) * c - sa]
    return np.array(b) / a[0], np.array(a) / a[0]


def _rbj_high_pass(fs, q, fc):
    w0 = 2 * math.pi * fc / fs
    alpha = math.sin(w0) / (2 * q)
    c = math.cos(w0)
    b = [(1 + c) / 2, -(1 + c), (1 + c) / 2]
    a = [1 + alpha, -2 * c, 1 - alpha]
    return np.array(b) / a[0], np.array(a) / a[0]


def k_weight(x, sr):
    b1, a1 = _rbj_high_shelf(sr, 4.0, 1 / math.sqrt(2), 1500.0)
    b2, a2 = _rbj_high_pass(sr, 0.5, 38.0)
    y = signal.lfilter(b1, a1, x, axis=0)
    return signal.lfilter(b2, a2, y, axis=0)


def block_power(y, sr, win_s, hop_s=0.1):
    """sum over channels of mean-square per block (K-weighted input)"""
    n = y.shape[0]
    w = int(round(win_s * sr))
    h = int(round(hop_s * sr))
    if n == 0:
        return np.array([0.0]), h
    if n < w:
        return np.array([np.sum(np.mean(y ** 2, axis=0))]), h
    cs = np.vstack([np.zeros((1, y.shape[1])), np.cumsum(y ** 2, axis=0)])
    st = np.arange(0, n - w + 1, h)
    return ((cs[st + w] - cs[st]) / w).sum(axis=1), h


def lufs(p):
    return -0.691 + pdb(p)


def integrated_lufs(p_m):
    l = lufs(p_m)
    g = l > -70
    if not g.any():
        return -math.inf
    rel = lufs(p_m[g].mean()) - 10
    g2 = g & (l > rel)
    return float(lufs(p_m[g2].mean())) if g2.any() else -math.inf


def loudness_range(p_s):
    l = lufs(p_s)
    g = l > -70
    if g.sum() < 2:
        return 0.0
    rel = lufs(p_s[g].mean()) - 20
    v = l[g & (l > rel)]
    if len(v) < 2:
        return 0.0
    return float(np.percentile(v, 95) - np.percentile(v, 10))


def true_peak_db(x, chunk=1 << 18, pad=128):
    peak = 0.0
    n = x.shape[0]
    for ch in range(x.shape[1]):
        s = x[:, ch]
        for start in range(0, n, chunk):
            a = max(0, start - pad)
            b = min(n, start + chunk + pad)
            up = signal.resample_poly(s[a:b], 4, 1)
            lo = (start - a) * 4
            hi = lo + (min(start + chunk, n) - start) * 4
            peak = max(peak, float(np.max(np.abs(up[lo:hi]))) if hi > lo else 0.0)
    return float(adb(peak))


def loudness_metrics(x, sr):
    y = k_weight(x, sr)
    p_m, hop = block_power(y, sr, 0.4, 0.1)
    p_s, _ = block_power(y, sr, 3.0, 0.1)
    lm, ls = lufs(p_m), lufs(p_s)
    return {
        "lufs_i": integrated_lufs(p_m),
        "lra": loudness_range(p_s),
        "max_momentary": float(np.max(lm)),
        "max_short_term": float(np.max(ls)),
        "_momentary": lm,
        "_short_term": ls,
        "_hop_s": hop / sr,
    }


# ------------------------------------------------------------------ spectrum
def stft_bands(xm, sr, nfft, hop, edges_hz, keep_ltas=False, frame_mask_fn=None):
    """Return per-frame band powers [frames, bands] and optionally the long-term spectrum.

    Processes in batches so long songs do not need gigabytes of RAM.
    """
    if len(xm) < nfft:
        xm = np.pad(xm, (0, nfft - len(xm)))
    win = signal.windows.hann(nfft, sym=False)
    wnorm = np.sum(win ** 2)
    freqs = np.fft.rfftfreq(nfft, 1.0 / sr)
    nb = len(edges_hz) - 1
    # bin -> band matrix
    M = np.zeros((len(freqs), nb))
    for i in range(nb):
        M[(freqs >= edges_hz[i]) & (freqs < edges_hz[i + 1]), i] = 1.0
    nfr = 1 + (len(xm) - nfft) // hop
    bands = np.zeros((nfr, nb))
    ltas = np.zeros(len(freqs)) if keep_ltas else None
    batch = 256
    for b0 in range(0, nfr, batch):
        b1 = min(nfr, b0 + batch)
        idx = (np.arange(b0, b1) * hop)[:, None] + np.arange(nfft)[None, :]
        fr = xm[idx] * win
        P = np.abs(np.fft.rfft(fr, axis=1)) ** 2 / wnorm
        P[:, 1:-1] *= 2.0
        bands[b0:b1] = P @ M
        if keep_ltas:
            ltas += P.sum(axis=0)
    times = (np.arange(nfr) * hop + nfft / 2) / sr
    if keep_ltas:
        ltas /= max(nfr, 1)
    return times, bands, freqs, ltas


def third_oct_edges(sr):
    lo = THIRD_OCT_CENTERS * 2 ** (-1 / 6)
    hi = THIRD_OCT_CENTERS * 2 ** (1 / 6)
    keep = hi <= sr / 2
    return THIRD_OCT_CENTERS[keep], np.concatenate([lo[keep], [hi[keep][-1]]])


def band_power_from_psd(freqs, psd, lo, hi):
    m = (freqs >= lo) & (freqs < hi)
    return float(psd[m].sum()) if m.any() else 0.0


def spectral_profile(freqs, ltas, sr):
    centers, edges = third_oct_edges(sr)
    total = band_power_from_psd(freqs, ltas, 20, min(20000, sr / 2))
    prof = [pdb(band_power_from_psd(freqs, ltas, edges[i], edges[i + 1])) - pdb(total)
            for i in range(len(centers))]
    broad = {}
    for name, lo, hi, _ in BROAD_BANDS:
        if lo < sr / 2:
            broad[name] = float(pdb(band_power_from_psd(freqs, ltas, lo, min(hi, sr / 2))) - pdb(total))
    # tilt: slope of 1/3-oct profile between 100 Hz and 10 kHz (dB per octave)
    c = np.array(centers)
    pv = np.array(prof)
    sel = (c >= 100) & (c <= min(10000, sr / 2))
    tilt = float(np.polyfit(np.log2(c[sel]), pv[sel], 1)[0]) if sel.sum() > 3 else 0.0
    m = (freqs >= 20) & (freqs <= min(20000, sr / 2))
    centroid = float(np.sum(freqs[m] * ltas[m]) / max(np.sum(ltas[m]), EPS))
    return {"third_oct_hz": [r(v, 1) for v in centers], "third_oct_db": [r(v, 2) for v in prof],
            "broad_db": {k: r(v, 2) for k, v in broad.items()}, "tilt_db_per_oct": tilt,
            "centroid_hz": centroid}


def find_resonances(freqs, ltas, sr, fmin=70.0, fmax=12000.0, top=8, min_excess=3.0):
    fmax = min(fmax, sr / 2 * 0.9)
    ppo = 48  # points per octave
    grid = np.geomspace(fmin, fmax, int(np.log2(fmax / fmin) * ppo))
    ldb = np.interp(grid, freqs, pdb(ltas))
    fine = uniform_filter1d(ldb, size=max(1, ppo // 12), mode="nearest")
    coarse = uniform_filter1d(ldb, size=ppo, mode="nearest")
    diff = fine - coarse
    pk, props = signal.find_peaks(diff, height=min_excess, distance=ppo // 3)
    order = np.argsort(props["peak_heights"])[::-1][:top]
    out = []
    for i in sorted(pk[order]):
        # Q estimate from the -3 dB width of the bump in the fine curve
        h = diff[i]
        lft, rgt = i, i
        while lft > 0 and diff[lft] > h - 3:
            lft -= 1
        while rgt < len(diff) - 1 and diff[rgt] > h - 3:
            rgt += 1
        bw_oct = max((rgt - lft) / ppo, 1 / 24)
        q = 1.0 / (2 ** (bw_oct / 2) - 2 ** (-bw_oct / 2))
        out.append({"hz": r(grid[i], 0), "excess_db": r(h, 1), "q_est": r(min(q, 8), 1), "harmonic": False})
    # peaks that form a harmonic series are sustained notes (pitch), not resonances of the instrument/room
    hzs = [o["hz"] for o in out]
    for i, f0 in enumerate(hzs):
        series = [j for j, f in enumerate(hzs) if j != i and f > f0 and abs(f / f0 - round(f / f0)) < 0.03
                  and round(f / f0) <= 8]
        if len(series) >= 2:
            for j in [i] + series:
                out[j]["harmonic"] = True
    return out


def detect_hum(freqs, ltas_quiet):
    if ltas_quiet is None or not np.any(ltas_quiet > 0):
        return None
    ldb = pdb(ltas_quiet)
    best = None
    for f0 in (50.0, 60.0):
        ex = []
        for k in range(1, 5):
            f = f0 * k
            pk = (freqs > f - 2) & (freqs < f + 2)
            ring = ((freqs > f - 15) & (freqs < f - 5)) | ((freqs > f + 5) & (freqs < f + 15))
            if pk.any() and ring.any():
                ex.append(float(ldb[pk].max() - np.median(ldb[ring])))
        strong = [e for e in ex if e > 10]
        if len(strong) >= 2 and (best is None or np.mean(strong) > best[1]):
            best = (f0, float(np.mean(strong)))
    return None if best is None else {"mains_hz": best[0], "excess_db": r(best[1], 1)}


# ------------------------------------------------------------- health checks
def clipping_runs(x, thr=0.9995, min_run=3):
    count = 0
    times = []
    for ch in range(x.shape[1]):
        c = np.abs(x[:, ch]) >= thr
        if not c.any():
            continue
        d = np.diff(np.concatenate([[0], c.astype(np.int8), [0]]))
        starts = np.where(d == 1)[0]
        ends = np.where(d == -1)[0]
        long_ = (ends - starts) >= min_run
        count += int(long_.sum())
        times += list(starts[long_])
    return count, sorted(set(times))


def stereo_metrics(x, sr):
    if x.shape[1] < 2:
        return None
    L, R = x[:, 0], x[:, 1]
    eL, eR = np.sum(L ** 2), np.sum(R ** 2)
    if eL + eR < EPS:
        return None
    corr = float(np.sum(L * R) / math.sqrt(max(eL * eR, EPS)))
    M, S = (L + R) / 2, (L - R) / 2
    side_mid = float(pdb(np.sum(S ** 2)) - pdb(np.sum(M ** 2)))
    sos = signal.butter(4, 150, "lowpass", fs=sr, output="sos")
    Ml, Sl = signal.sosfilt(sos, M), signal.sosfilt(sos, S)
    low_side_mid = float(pdb(np.sum(Sl ** 2)) - pdb(np.sum(Ml ** 2)))
    w = int(0.4 * sr)
    h = int(0.1 * sr)
    neg = 0
    tot = 0
    if len(L) >= w:
        cLR = np.concatenate([[0.0], np.cumsum(L * R)])
        cLL = np.concatenate([[0.0], np.cumsum(L * L)])
        cRR = np.concatenate([[0.0], np.cumsum(R * R)])
        st = np.arange(0, len(L) - w + 1, h)
        lr = cLR[st + w] - cLR[st]
        ll = cLL[st + w] - cLL[st]
        rr = cRR[st + w] - cRR[st]
        act = (ll + rr) / (2 * w) > 1e-6  # ignore silence (< -60 dBFS)
        c = lr[act] / np.sqrt(np.maximum(ll[act] * rr[act], EPS))
        tot = int(act.sum())
        neg = int(np.sum(c < 0))
    return {
        "correlation": corr,
        "pct_time_negative_corr": 100.0 * neg / tot if tot else 0.0,
        "side_to_mid_db": side_mid,
        "low_side_to_mid_db": low_side_mid,
        "balance_l_minus_r_db": float(pdb(eL) - pdb(eR)),
        "is_dual_mono": bool(side_mid < -60),
    }


# ---------------------------------------------------------------- vocal-only
def vocal_details(xm, sr):
    nfft = 1 << int(round(math.log2(0.021 * sr)))  # ~21 ms
    hop = nfft // 4
    edges = [20, 100, 200, 4000, 5000, min(10000, sr / 2 - 1)]
    times, B, _, _ = stft_bands(xm, sr, nfft, hop, edges)
    lf, body, sib = pdb(B[:, 0]), pdb(B[:, 2]), pdb(B[:, 4])
    total = pdb(B.sum(axis=1))
    if len(total) == 0:
        return {}
    act = active_mask(total, 30)
    if act.sum() < 10:
        return {}
    sib_frames = act & (sib > body)
    voiced = act & ~sib_frames
    body_ref = np.percentile(body[voiced], 75) if voiced.any() else np.percentile(body[act], 75)
    s_v = float(np.percentile(sib[sib_frames], 95) - body_ref) if sib_frames.sum() >= 5 else None
    # hottest sibilants (distinct, >= 0.25 s apart)
    hot = []
    if sib_frames.any():
        order = np.argsort(sib[sib_frames])[::-1]
        cand_t = times[sib_frames][order]
        cand_v = sib[sib_frames][order] - body_ref
        for t, v in zip(cand_t, cand_v):
            if all(abs(t - u) > 0.25 for u, _ in hot):
                hot.append((float(t), float(v)))
            if len(hot) >= 8:
                break
    plos = act & (lf - body > 3) & (lf > np.percentile(lf[act], 90))
    pl_t = []
    for t in times[plos]:
        if all(abs(t - u) > 0.3 for u in pl_t):
            pl_t.append(float(t))
    return {
        "sibilance_peak_vs_body_db": s_v,
        "sibilant_frames_pct": 100.0 * sib_frames.sum() / act.sum(),
        "sibilance_hotspots": [{"t": tstr(t), "sec": r(t, 2), "rel_db": r(v, 1)} for t, v in sorted(hot)],
        "plosive_count": len(pl_t),
        "plosive_times": [tstr(t) for t in pl_t[:12]],
    }


def level_consistency(lm):
    """spread of momentary loudness while the source is active"""
    act = active_mask(lm, 25)
    if act.sum() < 5:
        return None
    v = lm[act]
    return {"p10": float(np.percentile(v, 10)), "p50": float(np.percentile(v, 50)),
            "p90": float(np.percentile(v, 90)), "spread_p90_p10": float(np.percentile(v, 90) - np.percentile(v, 10)),
            "std": float(np.std(v))}


# ------------------------------------------------------------ single track
def analyze_track(path, role, want_plots=None):
    x, sr = load_audio(path)
    xm = mono(x)
    res = {"file": os.path.abspath(path), "role": role, "sr": sr, "channels": x.shape[1],
           "duration_s": r(len(xm) / sr, 2)}
    peak = float(adb(np.max(np.abs(x)))) if len(x) else -999
    rms = float(pdb(np.mean(x ** 2))) if len(x) else -999
    lm = loudness_metrics(x, sr)
    tp = true_peak_db(x)
    res["levels"] = {
        "sample_peak_dbfs": r(peak), "true_peak_dbtp": r(tp), "rms_dbfs": r(rms),
        "crest_factor_db": r(peak - rms), "lufs_i": r(lm["lufs_i"]), "lra_lu": r(lm["lra"]),
        "max_momentary_lufs": r(lm["max_momentary"]), "max_short_term_lufs": r(lm["max_short_term"]),
        "plr_db": r(tp - lm["lufs_i"]) if math.isfinite(lm["lufs_i"]) else None,
    }
    fr_db, fr_hop = frames_rms_db(xm, sr)
    live = fr_db[fr_db > -120]
    clip_n, clip_pos = clipping_runs(x)
    res["health"] = {
        "clipped_runs": clip_n,
        "clip_times": [tstr(p / sr) for p in clip_pos[:10]],
        "dc_offset": r(float(np.mean(xm)), 5),
        "noise_floor_dbfs": (r(np.percentile(live, 3)) if len(live) and
                             np.percentile(live, 95) - np.percentile(live, 3) > 25 else None),
        "active_level_p95_dbfs": r(np.percentile(live, 95)) if len(live) else None,
    }
    # spectrum (8192-point at 44.1/48k; scaled for higher rates)
    nfft = 1 << int(round(math.log2(0.17 * sr)))
    hop = nfft // 2
    times, Bt, freqs, ltas = stft_bands(xm, sr, nfft, hop, [20, sr / 2], keep_ltas=True)
    # quiet-frame spectrum for hum detection
    tot = pdb(Bt[:, 0])
    quiet = (tot < np.percentile(tot, 15)) & (tot > -110)
    ltas_q = None
    if quiet.sum() >= 3:
        _, _, _, ltas_q = _ltas_subset(xm, sr, nfft, hop, quiet)
    res["health"]["hum"] = detect_hum(freqs, ltas_q)
    prof = spectral_profile(freqs, ltas, sr)
    res["spectrum"] = {"tilt_db_per_oct": r(prof["tilt_db_per_oct"], 2),
                       "centroid_hz": r(prof["centroid_hz"], 0),
                       "broad_db": prof["broad_db"],
                       "third_oct_hz": prof["third_oct_hz"], "third_oct_db": prof["third_oct_db"]}
    res["resonances"] = find_resonances(freqs, ltas, sr)
    st = stereo_metrics(x, sr)
    if st:
        res["stereo"] = {k: (r(v, 2) if isinstance(v, float) else v) for k, v in st.items()}
    lc = level_consistency(lm["_momentary"])
    if lc:
        res["consistency"] = {k: r(v, 1) for k, v in lc.items()}
    if role == "vocal":
        vd = vocal_details(xm, sr)
        if vd:
            vd["sibilance_peak_vs_body_db"] = r(vd.get("sibilance_peak_vs_body_db"), 1)
            vd["sibilant_frames_pct"] = r(vd.get("sibilant_frames_pct"), 1)
            res["vocal"] = vd
    res["_series"] = {"momentary": lm["_momentary"], "short_term": lm["_short_term"], "hop_s": lm["_hop_s"]}
    res["_audio"] = (x, sr)
    return res


def _ltas_subset(xm, sr, nfft, hop, mask):
    win = signal.windows.hann(nfft, sym=False)
    wnorm = np.sum(win ** 2)
    idxs = np.where(mask)[0]
    acc = np.zeros(nfft // 2 + 1)
    for i in idxs:
        fr = xm[i * hop:i * hop + nfft]
        if len(fr) < nfft:
            continue
        P = np.abs(np.fft.rfft(fr * win)) ** 2 / wnorm
        P[1:-1] *= 2
        acc += P
    return None, None, None, acc / max(len(idxs), 1)


# ----------------------------------------------------- vocal vs guitar
def _align_len(*arrs):
    n = min(len(a) for a in arrs)
    return [a[:n] for a in arrs]


def session_metrics(vocal, guitars):
    """vocal: (x, sr); guitars: list of (x, sr) post-fader exports from the same start point."""
    xv, sr = vocal
    gs = [g for g, s in guitars if s == sr]
    if not gs:
        return {"error": "частота дискретизации вокала и гитар не совпадает"}
    n = min([len(xv)] + [len(g) for g in gs])
    # accompaniment = sum of guitars, stereo-summed as exported
    acc = np.zeros((n, 2))
    for g in gs:
        gg = g[:n]
        acc += gg if gg.shape[1] == 2 else np.repeat(gg, 2, axis=1)
    v = xv[:n]
    v2 = v if v.shape[1] == 2 else np.repeat(v, 2, axis=1)
    pv, _ = block_power(k_weight(v2, sr), sr, 0.4, 0.1)
    pg, _ = block_power(k_weight(acc, sr), sr, 0.4, 0.1)
    lv, lg = lufs(pv), lufs(pg)
    act = active_mask(lv, 20)
    out = {}
    if act.sum() >= 5:
        d = lv[act] - lg[act]
        out["vocal_over_guitar_lu"] = {"median": r(np.median(d)), "p10": r(np.percentile(d, 10)),
                                       "p90": r(np.percentile(d, 90))}
    # masking per third-octave band while singing
    nfft = 1 << int(round(math.log2(0.043 * sr)))
    hop = nfft // 4
    centers, edges = third_oct_edges(sr)
    sel = (centers >= 125) & (centers <= 8000)
    c_sel = centers[sel]
    idx = np.where(sel)[0]
    e_sel = np.concatenate([edges[idx], [edges[idx[-1] + 1]]])
    _, Bv, _, _ = stft_bands(mono(v), sr, nfft, hop, e_sel)
    _, Bg, _, _ = stft_bands(mono(acc), sr, nfft, hop, e_sel)
    tv = pdb(Bv.sum(axis=1))
    a2 = active_mask(tv, 25)
    if a2.sum() >= 10:
        vdb = pdb(Bv[a2])
        gdb = pdb(Bg[a2])
        g_minus_v = np.median(gdb - vdb, axis=0)
        v_med = np.median(vdb, axis=0)
        importance = v_med - v_med.max()
        bands = []
        for f, gv, imp in zip(c_sel, g_minus_v, importance):
            bands.append({"hz": r(f, 0), "guitar_minus_vocal_db": r(gv, 1), "vocal_importance_db": r(imp, 1),
                          "masking": bool(imp > -18 and gv > -3)})
        out["masking_bands"] = bands
        zone = (c_sel >= 1000) & (c_sel <= 4000)
        out["intelligibility_zone_1_4k_guitar_minus_vocal_db"] = r(
            float(pdb(np.median(Bg[a2][:, zone].sum(axis=1))) - pdb(np.median(Bv[a2][:, zone].sum(axis=1)))))
    if len(gs) >= 2:
        out["guitar_alignment"] = align_signals(mono(gs[0][:n]), mono(gs[1][:n]), sr)
    return out


def align_signals(a, b, sr, max_ms=20.0, seconds=60.0):
    """lag of b relative to a (positive = b is late) and polarity, via band-limited xcorr"""
    n = int(min(len(a), len(b), seconds * sr))
    sos = signal.butter(4, [100, 2000], "bandpass", fs=sr, output="sos")
    aa = signal.sosfilt(sos, a[:n])
    bb = signal.sosfilt(sos, b[:n])
    maxlag = int(max_ms / 1000 * sr)
    cc = signal.correlate(bb, aa, mode="full", method="fft")
    lags = np.arange(-n + 1, n)
    m = np.abs(lags) <= maxlag
    cc, lags = cc[m], lags[m]
    i = int(np.argmax(np.abs(cc)))
    norm = math.sqrt(max(np.sum(aa ** 2) * np.sum(bb ** 2), EPS))
    return {"lag_samples": int(lags[i]), "lag_ms": r(lags[i] / sr * 1000, 3),
            "polarity": "inverted" if cc[i] < 0 else "normal", "peak_correlation": r(abs(cc[i]) / norm, 3),
            "hint": (f"второй файл опаздывает: сдвинь его раньше на {lags[i]} сэмплов "
                     f"(в движке \"delay_samples\": {-lags[i]})" if lags[i] > 0 else
                     f"второй файл опережает: сдвинь его позже на {-lags[i]} сэмплов "
                     f"(в движке \"delay_samples\": {-lags[i]})" if lags[i] < 0 else "уже выровнено")}


# -------------------------------------------------------------- references
def reference_profile(paths):
    profs, lv = [], []
    for p in paths:
        t = analyze_track(p, "ref")
        profs.append(np.array([np.nan if v is None else v for v in t["spectrum"]["third_oct_db"]], dtype=float))
        lv.append(t)
    nmin = min(len(p) for p in profs)
    mean = np.nanmean(np.vstack([p[:nmin] for p in profs]), axis=0)
    hz = lv[0]["spectrum"]["third_oct_hz"][:nmin]
    broad = {}
    for k in lv[0]["spectrum"]["broad_db"]:
        vals = [t["spectrum"]["broad_db"].get(k) for t in lv if t["spectrum"]["broad_db"].get(k) is not None]
        if vals:
            broad[k] = float(np.mean(vals))
    agg = lambda key, sub: float(np.mean([t[key][sub] for t in lv if t[key].get(sub) is not None]))  # noqa: E731
    res = {"files": [os.path.basename(p) for p in paths], "third_oct_hz": hz,
           "third_oct_db": [r(v, 2) for v in mean], "broad_db": {k: r(v, 2) for k, v in broad.items()},
           "lufs_i": r(agg("levels", "lufs_i")), "lra_lu": r(agg("levels", "lra_lu")),
           "crest_factor_db": r(agg("levels", "crest_factor_db")), "plr_db": r(agg("levels", "plr_db")),
           "tilt_db_per_oct": r(np.mean([t["spectrum"]["tilt_db_per_oct"] for t in lv]), 2)}
    st = [t["stereo"] for t in lv if "stereo" in t]
    if st:
        res["stereo"] = {k: r(np.mean([s[k] for s in st]), 2) for k in ("correlation", "side_to_mid_db",
                                                                          "low_side_to_mid_db")}
    cons = [t["consistency"]["spread_p90_p10"] for t in lv if "consistency" in t]
    if cons:
        res["consistency_spread"] = r(np.mean(cons))
    vs = [t["vocal"]["sibilance_peak_vs_body_db"] for t in lv if t.get("vocal", {}).get("sibilance_peak_vs_body_db")
          is not None]
    if vs:
        res["sibilance_peak_vs_body_db"] = r(np.mean(vs))
    return res


def compare_to_ref(track, ref):
    out = {"broad_delta_db": {}, "third_oct_delta_db": []}
    floor = -50.0  # below this a band is "empty"; avoids absurd +60 dB deltas against silent bands
    for k, v in track["spectrum"]["broad_db"].items():
        if k in ref["broad_db"] and v is not None and ref["broad_db"][k] is not None:
            out["broad_delta_db"][k] = r(max(v, floor) - max(ref["broad_db"][k], floor), 1)
    n = min(len(track["spectrum"]["third_oct_db"]), len(ref["third_oct_db"]))
    for i in range(n):
        a, b = track["spectrum"]["third_oct_db"][i], ref["third_oct_db"][i]
        out["third_oct_delta_db"].append(None if a is None or b is None else r(max(a, floor) - max(b, floor), 1))
    out["tilt_delta"] = r(track["spectrum"]["tilt_db_per_oct"] - ref["tilt_db_per_oct"], 2)
    for k in ("lufs_i", "crest_factor_db", "lra_lu", "plr_db"):
        a = track["levels"].get(k)
        b = ref.get(k)
        if a is not None and b is not None:
            out[k + "_delta"] = r(a - b, 1)
    return out


# ----------------------------------------------------------------- findings
def findings_for(name, t, stage, target_lufs, ref_cmp):
    F = []

    def add(sev, msg, action, metric=None):
        F.append({"severity": sev, "track": name, "metric": metric, "message": msg, "action": action})

    L, H = t["levels"], t["health"]
    role = t["role"]
    if H["clipped_runs"] > 0:
        add("high", f"цифровой клиппинг: {H['clipped_runs']} мест (первые: {', '.join(H['clip_times'][:5])})",
            "перезаписать с меньшим гейном или RX De-clip; в миксе - убрать перегруз на канале/шине", "clipped_runs")
    if abs(H["dc_offset"] or 0) > 0.002:
        add("low", f"DC-смещение {H['dc_offset']}", "HPF 20-30 Гц или DC-фильтр", "dc_offset")
    if H.get("hum"):
        add("med", f"фон сети {H['hum']['mains_hz']:.0f} Гц (+{H['hum']['excess_db']} dB над шумом)",
            "RX De-hum или узкие вырезы 50/100/150 Гц; проверить заземление и кабель DI", "hum")
    nf = H.get("noise_floor_dbfs")
    if role in ("vocal", "guitar") and nf is not None and nf > -55 and stage in ("raw", "mix"):
        add("med", f"высокий шумовой пол {nf} dBFS (паузы/тишина)",
            "RX Spectral De-noise / Voice De-noise, экспандер или ручная чистка пауз", "noise_floor_dbfs")

    if stage == "raw" and role in ("vocal", "guitar"):
        if L["sample_peak_dbfs"] > -3:
            add("med", f"запись горячая: пик {L['sample_peak_dbfs']} dBFS",
                "в 24 бит пишем с пиками -12..-6 dBFS; сейчас - Clip Gain вниз", "sample_peak_dbfs")
        if L["rms_dbfs"] < -30:
            add("low", f"запись тихая: RMS {L['rms_dbfs']} dBFS",
                f"Clip Gain +{-18 - L['rms_dbfs']:.0f} dB перед плагинами (ориентир RMS около -18 dBFS)",
                "rms_dbfs")
        elif L["rms_dbfs"] > -12:
            add("low", f"очень плотный уровень: RMS {L['rms_dbfs']} dBFS",
                f"Clip Gain {-18 - L['rms_dbfs']:.0f} dB: аналоговые эмуляции (CLA-76, CLA-2A, Pultec) "
                "рассчитаны на ~-18 dBFS", "rms_dbfs")

    if role == "vocal":
        v = t.get("vocal", {})
        sv = v.get("sibilance_peak_vs_body_db")
        if sv is not None:
            hs = ", ".join(h["t"] for h in v.get("sibilance_hotspots", [])[:5])
            if sv > -2:
                add("high", f"резкие сибилянты: пики 'с/ш' на {sv} dB относительно тела голоса (горячие места: {hs})",
                    "де-эссер 4-8 dB подавления 5-9 кГц (Pro-DS, Single Vocal); второй де-эссер после "
                    "компрессии/эксайтера; на ревер-посыл - отдельный де-эссер", "sibilance_peak_vs_body_db")
            elif sv > -6:
                add("med", f"заметные сибилянты: {sv} dB относительно тела (горячие места: {hs})",
                    "де-эссер 2-4 dB подавления 5-9 кГц", "sibilance_peak_vs_body_db")
        if v.get("plosive_count", 0) > 0:
            add("med", f"плозивы (удары 'п/б' в низах): {v['plosive_count']} шт., {', '.join(v['plosive_times'][:6])}",
                "HPF 80-100 Гц; на конкретных местах - клип-гейн/фейд или RX De-plosive; "
                "динамическая полоса Pro-Q ниже 150 Гц", "plosive_count")
        c = t.get("consistency")
        if c and stage != "raw":
            sp = c["spread_p90_p10"]
            if sp > 10:
                add("high", f"вокал неровный: разброс громкости {sp} LU (10-90 процентиль)",
                    "ручной клип-гейн по фразам +-3-6 dB, затем серия компрессоров (CLA-76 3-6 dB + CLA-2A 2-4 dB), "
                    "Vocal Rider; ориентир для готового вокала около 4-8 LU", "consistency")
            elif sp > 8:
                add("med", f"вокал слегка гуляет: разброс {sp} LU",
                    "больше выравнивания: Vocal Rider или автоматизация +-1-3 dB, добавить 1-2 dB компрессии",
                    "consistency")

    st = t.get("stereo")
    if st and role in ("mix", "master"):
        if st["correlation"] < 0.2 or st["pct_time_negative_corr"] > 5:
            add("high", f"фазовые проблемы: корреляция {st['correlation']}, отрицательная "
                        f"{st['pct_time_negative_corr']}% времени",
                "проверить полярность DI/микрофона (команда align), стерео-расширители, моно-совместимость", "stereo")
        if st["low_side_to_mid_db"] > -15:
            add("med", f"низ слишком широкий (side/mid < 150 Гц = {st['low_side_to_mid_db']} dB)",
                "сделать низ моно: Pro-Q M/S - срез Side ниже 120-150 Гц", "stereo")
        if abs(st["balance_l_minus_r_db"]) > 1.5:
            add("low", f"перекос L/R {st['balance_l_minus_r_db']} dB", "проверить панораму", "stereo")

    if role == "master" or stage == "master":
        if L["true_peak_dbtp"] > -1.0:
            add("high", f"true peak {L['true_peak_dbtp']} dBTP > -1", "потолок лимитера -1.0 dBTP, True Peak режим",
                "true_peak_dbtp")
        if L["lufs_i"] is not None and abs(L["lufs_i"] - target_lufs) > 1.0:
            add("med", f"громкость {L['lufs_i']} LUFS, цель {target_lufs}",
                f"изменить вход лимитера на {target_lufs - L['lufs_i']:+.1f} dB", "lufs_i")
        if L["plr_db"] is not None and L["plr_db"] < 8:
            add("med", f"пережато: PLR {L['plr_db']} dB", "акустике нужен воздух: PLR 9-14 dB; ослабить лимитер",
                "plr_db")
    elif role == "mix":
        if L["sample_peak_dbfs"] > -1.0:
            add("med", f"микс без запаса: пик {L['sample_peak_dbfs']} dBFS",
                "опустить все фейдеры одинаково, оставить -6..-3 dBFS запаса для мастеринга", "sample_peak_dbfs")

    if ref_cmp:
        bd = ref_cmp["broad_delta_db"]
        for band, d in sorted(bd.items(), key=lambda kv: -abs(kv[1] or 0)):
            if d is None or abs(d) < 2.0:
                continue
            tv = t["spectrum"]["broad_db"].get(band)
            if tv is not None and max(tv, tv - d) < -35:  # both track and reference have ~no energy here
                continue
            meaning = next(m for n_, lo, hi, m in BROAD_BANDS if n_ == band)
            lohi = next((lo, hi) for n_, lo, hi, m in BROAD_BANDS if n_ == band)
            sev = "high" if abs(d) >= 4 else "med"
            action = BAND_ACTIONS[band][0 if d > 0 else 1]
            add(sev, f"{band} ({lohi[0]}-{lohi[1]} Гц, {meaning}): {d:+.1f} dB относительно референса",
                action, f"band:{band}")
        if ref_cmp.get("tilt_delta") is not None and abs(ref_cmp["tilt_delta"]) > 0.8:
            d = ref_cmp["tilt_delta"]
            add("med", f"общий наклон спектра {d:+.2f} dB/окт относительно референса "
                       f"({'ярче/тоньше' if d > 0 else 'темнее/толще'})",
                "широкий наклон: Pro-Q tilt / Baxandall: " + ("-1..-2 dB вверху или +1 dB внизу" if d > 0 else
                                                              "+1..+2 dB полкой вверху"), "tilt")

    if role in ("vocal", "guitar"):
        for rz in [z for z in t.get("resonances", []) if not z.get("harmonic")][:3]:
            if rz["excess_db"] >= 5 and 150 <= rz["hz"] <= 8000:
                add("low", f"кандидат в резонанс {rz['hz']:.0f} Гц (+{rz['excess_db']} dB, Q~{rz['q_est']})",
                    f"проверить свипом; если звенит - динамическая полоса -2..-4 dB, Q {rz['q_est']}", "resonance")
    return F


def session_findings(sess, ref_sess):
    F = []
    vg = sess.get("vocal_over_guitar_lu")
    if vg:
        target = ref_sess.get("vocal_over_guitar_lu", {}).get("median") if ref_sess else None
        med = vg["median"]
        if target is not None:
            if med < target - 1.5:
                F.append({"severity": "high", "track": "session", "metric": "vocal_over_guitar",
                          "message": f"вокал тише, чем в референсе: +{med} LU над гитарой против +{target} LU",
                          "action": f"поднять вокал на ~{target - med:.1f} dB или опустить гитару"})
            elif med > target + 1.5:
                F.append({"severity": "med", "track": "session", "metric": "vocal_over_guitar",
                          "message": f"вокал громче, чем в референсе: +{med} LU против +{target} LU",
                          "action": f"опустить вокал на ~{med - target:.1f} dB (вокал 'отрывается' от гитары)"})
        elif med < 1.0:
            F.append({"severity": "high", "track": "session", "metric": "vocal_over_guitar",
                      "message": f"вокал тонет: всего {med:+.1f} LU над гитарой во время пения",
                      "action": "в песнях 'голос+гитара' вокал обычно заметно выше гитары; поднять вокал 2-4 dB, "
                                "затем сверить с референсом"})
    mb = [b for b in sess.get("masking_bands", []) if b["masking"]]
    if mb:
        worst = sorted(mb, key=lambda b: -b["guitar_minus_vocal_db"])[:4]
        bands = ", ".join(f"{b['hz']:.0f} Гц ({b['guitar_minus_vocal_db']:+.1f})" for b in worst)
        F.append({"severity": "med", "track": "session", "metric": "masking",
                  "message": f"гитара маскирует вокал в полосах: {bands} (гитара минус вокал, dB)",
                  "action": "на гитаре динамическая полоса -2..-4 dB на этих частотах с внешним сайдчейном от вокала "
                            "(Pro-Q dynamic + sidechain / TrackSpacer / Neutron Unmask); либо статично -1..-2 dB"})
    zone = sess.get("intelligibility_zone_1_4k_guitar_minus_vocal_db")
    if zone is not None and zone > -3:
        F.append({"severity": "med", "track": "session", "metric": "intelligibility",
                  "message": f"в зоне разборчивости 1-4 кГц гитара почти равна вокалу ({zone:+.1f} dB)",
                  "action": "вырезать гитару 2-4 кГц на 2-3 dB (динамически от вокала) или +1..2 dB присутствия вокалу"})
    al = sess.get("guitar_alignment")
    if al and (abs(al["lag_samples"]) > 2 or al["polarity"] == "inverted"):
        F.append({"severity": "high", "track": "session", "metric": "alignment",
                  "message": f"гитары (DI и микрофон) не выровнены: {al['lag_ms']} мс ({al['lag_samples']} сэмплов), "
                             f"полярность {al['polarity']}",
                  "action": al["hint"] + ("; инвертировать фазу второго" if al["polarity"] == "inverted" else "")})
    return F


# -------------------------------------------------------------------- plots
def make_plots(tracks, refs_by_role, sess, outdir):
    try:
        import matplotlib
        matplotlib.use("Agg")
        import matplotlib.pyplot as plt
    except ImportError:
        return []
    files = []
    for name, t in tracks.items():
        hz = np.array(t["spectrum"]["third_oct_hz"], dtype=float)
        db = np.array([np.nan if v is None else v for v in t["spectrum"]["third_oct_db"]], dtype=float)
        ref = refs_by_role.get(t["role"])
        fig, axes = plt.subplots(2 if ref else 1, 1, figsize=(11, 7 if ref else 4.2), squeeze=False)
        ax = axes[0][0]
        ax.semilogx(hz, db, "-o", ms=3, lw=2, label=name)
        if ref:
            rhz = np.array(ref["third_oct_hz"], dtype=float)
            rdb = np.array([np.nan if v is None else v for v in ref["third_oct_db"]], dtype=float)
            ax.semilogx(rhz, rdb, "--", lw=2, label="референс (среднее)")
        for _, lo, hi, _m in BROAD_BANDS:
            ax.axvline(lo, color="0.85", lw=0.8)
        ax.set_xlim(20, 20000)
        ax.set_ylabel("dB отн. общей энергии")
        ax.set_title(f"Тональный баланс 1/3 октавы: {name}")
        ax.grid(True, which="both", alpha=0.3)
        ax.legend()
        if ref:
            ax2 = axes[1][0]
            n = min(len(db), len(rdb))
            d = db[:n] - rdb[:n]
            colors = ["#c0392b" if v > 2 else "#2471a3" if v < -2 else "#7f8c8d" for v in np.nan_to_num(d)]
            ax2.bar(range(n), d, color=colors)
            ax2.set_xticks(range(n))
            ax2.set_xticklabels([f"{h:.0f}" if h < 1000 else f"{h / 1000:.1f}k" for h in hz[:n]], rotation=90,
                                fontsize=7)
            ax2.axhline(2, color="#c0392b", ls=":")
            ax2.axhline(-2, color="#2471a3", ls=":")
            ax2.axhline(0, color="k", lw=0.8)
            ax2.set_ylabel("трек - референс, dB")
            ax2.set_title("Отличие от референса (красный: лишнее, синий: не хватает)")
        fig.tight_layout()
        p = os.path.join(outdir, f"profile_{name}.png")
        fig.savefig(p, dpi=110)
        plt.close(fig)
        files.append(p)

        s = t["_series"]
        tm = np.arange(len(s["momentary"])) * s["hop_s"]
        fig, ax = plt.subplots(figsize=(11, 3.2))
        ax.plot(tm, s["momentary"], lw=0.6, alpha=0.6, label="Momentary (400 мс)")
        ax.plot(np.arange(len(s["short_term"])) * s["hop_s"], s["short_term"], lw=1.6, label="Short-term (3 с)")
        if t["levels"]["lufs_i"] is not None and t["levels"]["lufs_i"] > -100:
            ax.axhline(t["levels"]["lufs_i"], color="k", ls="--", lw=1, label=f"Integrated {t['levels']['lufs_i']}")
        fin = s["short_term"][np.isfinite(s["short_term"])]
        ax.set_ylim(max(-60.0, float(fin.min()) - 5) if len(fin) else -60.0, 0)
        ax.set_xlabel("секунды")
        ax.set_ylabel("LUFS")
        ax.set_title(f"Громкость во времени: {name}")
        ax.grid(alpha=0.3)
        ax.legend(loc="lower right", fontsize=8)
        fig.tight_layout()
        p = os.path.join(outdir, f"loudness_{name}.png")
        fig.savefig(p, dpi=110)
        plt.close(fig)
        files.append(p)

    if sess and sess.get("masking_bands"):
        mb = sess["masking_bands"]
        fig, ax = plt.subplots(figsize=(11, 3.6))
        vals = [b["guitar_minus_vocal_db"] for b in mb]
        cols = ["#c0392b" if b["masking"] else "#7f8c8d" for b in mb]
        ax.bar(range(len(mb)), vals, color=cols)
        ax.axhline(-3, color="#c0392b", ls=":", label="порог маскировки (-3 dB)")
        ax.set_xticks(range(len(mb)))
        ax.set_xticklabels([f"{b['hz']:.0f}" if b["hz"] < 1000 else f"{b['hz'] / 1000:.1f}k" for b in mb],
                           rotation=90, fontsize=8)
        ax.set_ylabel("гитара - вокал, dB")
        ax.set_title("Маскировка вокала гитарой во время пения (красный = гитара закрывает важную полосу голоса)")
        ax.legend()
        ax.grid(alpha=0.3)
        fig.tight_layout()
        p = os.path.join(outdir, "masking.png")
        fig.savefig(p, dpi=110)
        plt.close(fig)
        files.append(p)
    return files


def spectrogram_plot(name, x, sr, outdir):
    try:
        import matplotlib
        matplotlib.use("Agg")
        import matplotlib.pyplot as plt
    except ImportError:
        return None
    xm = mono(x)
    f, tt, S = signal.spectrogram(xm, fs=sr, nperseg=2048, noverlap=1536, scaling="spectrum")
    fig, ax = plt.subplots(figsize=(12, 4))
    ax.pcolormesh(tt, f, pdb(S), shading="auto", vmin=-110, vmax=-20, cmap="magma")
    ax.set_yscale("symlog", linthresh=500)
    ax.set_ylim(40, min(20000, sr / 2))
    ax.set_xlabel("секунды")
    ax.set_ylabel("Гц")
    ax.set_title(f"Спектрограмма: {name}")
    fig.tight_layout()
    p = os.path.join(outdir, f"spectrogram_{name}.png")
    fig.savefig(p, dpi=100)
    plt.close(fig)
    return p


# ------------------------------------------------------------------- report
SEV_ORDER = {"high": 0, "med": 1, "low": 2}
SEV_RU = {"high": "СИЛЬНО", "med": "средне", "low": "мелочь"}


def prev_deltas(cur, prev):
    rows = []
    for name, t in cur["tracks"].items():
        p = prev.get("tracks", {}).get(name)
        if not p:
            continue
        for k in ("lufs_i", "true_peak_dbtp", "crest_factor_db", "lra_lu", "plr_db"):
            a, b = t["levels"].get(k), p["levels"].get(k)
            if a is not None and b is not None and abs(a - b) >= 0.1:
                rows.append((name, k, b, a))
        for k, a in t["spectrum"]["broad_db"].items():
            b = p["spectrum"]["broad_db"].get(k)
            if a is not None and b is not None and abs(a - b) >= 0.3:
                rows.append((name, f"band:{k}", b, a))
        if "consistency" in t and "consistency" in p:
            rows.append((name, "spread_p90_p10", p["consistency"]["spread_p90_p10"], t["consistency"]["spread_p90_p10"]))
        a = t.get("vocal", {}).get("sibilance_peak_vs_body_db")
        b = p.get("vocal", {}).get("sibilance_peak_vs_body_db")
        if a is not None and b is not None:
            rows.append((name, "sibilance_peak_vs_body_db", b, a))
    s, ps = cur.get("session") or {}, prev.get("session") or {}
    if s.get("vocal_over_guitar_lu") and ps.get("vocal_over_guitar_lu"):
        rows.append(("session", "vocal_over_guitar_lu", ps["vocal_over_guitar_lu"]["median"],
                     s["vocal_over_guitar_lu"]["median"]))
    if "masking_bands" in s and "masking_bands" in ps:
        rows.append(("session", "masking_band_count", sum(b["masking"] for b in ps["masking_bands"]),
                     sum(b["masking"] for b in s["masking_bands"])))
    nf_prev = len(prev.get("findings", []))
    rows.append(("all", "findings_count", nf_prev, len(cur.get("findings", []))))
    return rows


def write_markdown(rep, path):
    L = []
    L.append(f"# Отчёт анализа микса: {rep['label']}")
    L.append(f"_{rep['created']} · stage={rep['stage']} · цель громкости {rep['target_lufs']} LUFS_\n")
    L.append("## Что исправить (по приоритету)\n")
    if not rep["findings"]:
        L.append("Явных проблем не найдено. Дальше решают уши: сравни с референсом на одной громкости.\n")
    for f in sorted(rep["findings"], key=lambda f: SEV_ORDER[f["severity"]]):
        L.append(f"- **[{SEV_RU[f['severity']]}] {f['track']}** - {f['message']}  \n  -> {f['action']}")
    L.append("")
    L.append("## Уровни\n")
    L.append("| трек | роль | LUFS-I | LRA | TP dBTP | пик dBFS | RMS dBFS | crest dB | PLR | шумовой пол |")
    L.append("|---|---|---|---|---|---|---|---|---|---|")
    for n, t in rep["tracks"].items():
        lv = t["levels"]
        L.append(f"| {n} | {t['role']} | {lv['lufs_i']} | {lv['lra_lu']} | {lv['true_peak_dbtp']} | "
                 f"{lv['sample_peak_dbfs']} | {lv['rms_dbfs']} | {lv['crest_factor_db']} | {lv['plr_db']} | "
                 f"{t['health']['noise_floor_dbfs']} |")
    L.append("")
    L.append("## Тональный баланс (dB относительно общей энергии; в скобках - отличие от референса)\n")
    names = [b[0] for b in BROAD_BANDS]
    L.append("| трек | " + " | ".join(f"{b[0]} {b[1]}-{b[2]}" for b in BROAD_BANDS) + " | наклон dB/окт |")
    L.append("|---|" + "---|" * (len(names) + 1))
    for n, t in rep["tracks"].items():
        rc = rep.get("ref_compare", {}).get(n, {}).get("broad_delta_db", {})
        cells = []
        for b in names:
            v = t["spectrum"]["broad_db"].get(b)
            d = rc.get(b)
            cells.append("-" if v is None else (f"{v}" + (f" ({d:+.1f})" if d is not None else "")))
        L.append(f"| {n} | " + " | ".join(cells) + f" | {t['spectrum']['tilt_db_per_oct']} |")
    L.append("")
    for n, t in rep["tracks"].items():
        extra = []
        if t.get("resonances"):
            extra.append("резонансы-кандидаты: " + ", ".join(
                f"{x['hz']:.0f} Гц +{x['excess_db']} (Q~{x['q_est']}{', гармоника ноты' if x.get('harmonic') else ''})"
                for x in t["resonances"]))
        if t.get("consistency"):
            c = t["consistency"]
            extra.append(f"ровность громкости: разброс {c['spread_p90_p10']} LU (p10 {c['p10']}, p90 {c['p90']})")
        if t.get("vocal"):
            v = t["vocal"]
            extra.append(f"сибилянты: пик {v.get('sibilance_peak_vs_body_db')} dB отн. тела, "
                         f"{v.get('sibilant_frames_pct')}% кадров; плозивов {v.get('plosive_count')}")
        if t.get("stereo"):
            s = t["stereo"]
            extra.append(f"стерео: корреляция {s['correlation']}, side/mid {s['side_to_mid_db']} dB, "
                         f"низ side/mid {s['low_side_to_mid_db']} dB, L-R {s['balance_l_minus_r_db']} dB")
        if t["health"].get("hum"):
            extra.append(f"фон {t['health']['hum']}")
        if extra:
            L.append(f"**{n}**: " + "; ".join(extra) + "\n")
    s = rep.get("session")
    if s:
        L.append("## Вокал против гитары\n")
        vg = s.get("vocal_over_guitar_lu")
        if vg:
            tgt = (rep.get("ref_session") or {}).get("vocal_over_guitar_lu", {}).get("median")
            L.append(f"- вокал над гитарой во время пения: медиана **{vg['median']:+} LU** (p10 {vg['p10']}, "
                     f"p90 {vg['p90']})" + (f"; в референсе {tgt:+} LU" if tgt is not None else ""))
        if s.get("intelligibility_zone_1_4k_guitar_minus_vocal_db") is not None:
            L.append(f"- зона разборчивости 1-4 кГц, гитара минус вокал: "
                     f"{s['intelligibility_zone_1_4k_guitar_minus_vocal_db']:+} dB (хорошо, когда < -3)")
        mb = [b for b in s.get("masking_bands", []) if b["masking"]]
        if mb:
            L.append("- маскировка: " + ", ".join(f"{b['hz']:.0f} Гц ({b['guitar_minus_vocal_db']:+})" for b in mb))
        if s.get("guitar_alignment"):
            a = s["guitar_alignment"]
            L.append(f"- выравнивание гитар: {a['lag_ms']} мс / {a['lag_samples']} сэмплов, полярность "
                     f"{a['polarity']}, корреляция {a['peak_correlation']}")
        L.append("")
    if rep.get("previous_deltas"):
        L.append("## Что изменилось с прошлого прогона\n")
        L.append("| трек | метрика | было | стало | Δ |")
        L.append("|---|---|---|---|---|")
        for n, k, b, a in rep["previous_deltas"]:
            try:
                d = f"{a - b:+.1f}"
            except TypeError:
                d = ""
            L.append(f"| {n} | {k} | {b} | {a} | {d} |")
        L.append("")
    if rep.get("plots"):
        L.append("## Графики\n")
        for p in rep["plots"]:
            L.append(f"- {os.path.basename(p)}")
    with open(path, "w", encoding="utf-8") as fh:
        fh.write("\n".join(L) + "\n")


def strip_private(t):
    return {k: v for k, v in t.items() if not k.startswith("_")}


def unique_name(base, used):
    n, i = base, 2
    while n in used:
        n = f"{base}_{i}"
        i += 1
    used.add(n)
    return n


def cmd_analyze(a):
    os.makedirs(a.out, exist_ok=True)
    jobs = [(p, "vocal") for p in a.vocal] + [(p, "guitar") for p in a.guitar] + \
           [(p, "mix") for p in a.mix] + [(p, "master") for p in a.master] + [(p, "other") for p in a.file]
    if not jobs:
        sys.exit("нужно хотя бы один файл: --vocal/--guitar/--mix/--master/--file")
    used = set()
    tracks = {}
    for p, role in jobs:
        name = unique_name(os.path.splitext(os.path.basename(p))[0].replace(" ", "_"), used)
        print(f"[analyze] {name} ({role}) ...", file=sys.stderr)
        tracks[name] = analyze_track(p, role)

    refs_by_role = {}
    for role, lst in (("mix", a.ref), ("master", a.ref), ("vocal", a.ref_vocal), ("guitar", a.ref_guitar)):
        if lst and role not in refs_by_role:
            print(f"[ref] {role}: {len(lst)} файлов ...", file=sys.stderr)
            refs_by_role[role] = reference_profile(lst)
    if "mix" in refs_by_role:
        refs_by_role["master"] = refs_by_role["mix"]

    ref_cmp = {}
    findings = []
    for name, t in tracks.items():
        rp = refs_by_role.get(t["role"])
        rc = compare_to_ref(t, rp) if rp else None
        if rc:
            ref_cmp[name] = rc
        findings += findings_for(name, t, a.stage, a.target_lufs, rc)

    sess = None
    ref_sess = None
    vocals = [t for t in tracks.values() if t["role"] == "vocal"]
    guitars = [t for t in tracks.values() if t["role"] == "guitar"]
    if vocals and guitars:
        sess = session_metrics(vocals[0]["_audio"], [g["_audio"] for g in guitars])
        if a.ref_vocal and a.ref_guitar and len(a.ref_vocal) == len(a.ref_guitar):
            vals = []
            for rv, rg in zip(a.ref_vocal, a.ref_guitar):
                rs = session_metrics(load_audio(rv), [load_audio(rg)])
                if rs.get("vocal_over_guitar_lu"):
                    vals.append(rs["vocal_over_guitar_lu"]["median"])
            if vals:
                ref_sess = {"vocal_over_guitar_lu": {"median": r(np.mean(vals))}}
        findings += session_findings(sess, ref_sess)

    plots = []
    if not a.no_plots:
        plots = make_plots(tracks, refs_by_role, sess, a.out)
        if a.spectrogram:
            for name, t in tracks.items():
                if t["role"] in ("vocal", "guitar"):
                    p = spectrogram_plot(name, *t["_audio"], a.out)
                    if p:
                        plots.append(p)

    rep = {"tool": "mix_analyzer", "version": VERSION, "created": _dt.datetime.now().isoformat(timespec="seconds"),
           "label": a.label or os.path.basename(os.path.abspath(a.out)), "stage": a.stage,
           "target_lufs": a.target_lufs, "tracks": {n: strip_private(t) for n, t in tracks.items()},
           "references": refs_by_role, "ref_compare": ref_cmp, "session": sess, "ref_session": ref_sess,
           "findings": sorted(findings, key=lambda f: SEV_ORDER[f["severity"]]), "plots": plots}
    if a.previous:
        with open(a.previous, encoding="utf-8") as fh:
            rep["previous_deltas"] = prev_deltas(rep, json.load(fh))
    jp = os.path.join(a.out, "report.json")
    with open(jp, "w", encoding="utf-8") as fh:
        json.dump(rep, fh, ensure_ascii=False, indent=1)
    mp = os.path.join(a.out, "report.md")
    write_markdown(rep, mp)
    print(mp)
    print(jp)
    for p in plots:
        print(p)


# --------------------------------------------------------------------- align
def cmd_align(a):
    xa, sra = load_audio(a.a)
    xb, srb = load_audio(a.b)
    if sra != srb:
        sys.exit("разная частота дискретизации")
    res = align_signals(mono(xa), mono(xb), sra, max_ms=a.max_ms)
    print(json.dumps(res, ensure_ascii=False, indent=1))


# ------------------------------------------------------------------- make-ir
def cmd_make_ir(a):
    """Estimate H = mic / DI (Welch H1 estimator), smooth it, build a minimum-phase FIR."""
    xd, sr = load_audio(a.di)
    xm_, sr2 = load_audio(a.mic)
    if sr != sr2:
        sys.exit("разная частота дискретизации DI и микрофона")
    d, m = _align_len(mono(xd), mono(xm_))
    al = align_signals(d, m, sr, max_ms=30)
    lag = al["lag_samples"]
    if lag > 0:
        m = m[lag:]
        d = d[:len(m)]
    elif lag < 0:
        d = d[-lag:]
        m = m[:len(d)]
    if al["polarity"] == "inverted":
        m = -m
    nfft = 1 << int(round(math.log2(0.34 * sr)))  # ~16384 @48k
    f, Pxy = signal.csd(d, m, fs=sr, nperseg=nfft)
    _, Pxx = signal.welch(d, fs=sr, nperseg=nfft)
    _, Pyy = signal.welch(m, fs=sr, nperseg=nfft)
    coh = np.abs(Pxy) ** 2 / np.maximum(Pxx * Pyy, EPS)
    H = np.abs(Pxy) / np.maximum(Pxx, EPS)
    Hdb = adb(H)
    # smooth in fractional octaves (default 1/6) - we want the body, not individual notes
    ppo = 48
    fmin, fmax = 30.0, min(20000.0, sr / 2 * 0.95)
    grid = np.geomspace(fmin, fmax, int(np.log2(fmax / fmin) * ppo))
    gdb = np.interp(grid, f, Hdb)
    gco = np.interp(grid, f, coh)
    gdb = uniform_filter1d(gdb, size=max(1, int(ppo * a.smoothing)), mode="nearest")
    # where the measurement is unreliable (low coherence) pull towards the median correction
    med = float(np.median(gdb[(grid > 200) & (grid < 5000)]))
    w = np.clip((gco - 0.3) / 0.4, 0, 1)
    gdb = w * gdb + (1 - w) * med
    gdb -= med  # unity overall gain
    gdb = np.clip(gdb, -a.max_db, a.max_db)
    # back to linear FFT grid, minimum-phase via real cepstrum
    N = 1 << int(math.ceil(math.log2(max(4096, a.length_ms / 1000 * sr * 4))))
    fl = np.fft.rfftfreq(N, 1 / sr)
    mag_db = np.interp(fl, grid, gdb, left=gdb[0], right=gdb[-1])
    mag = 10 ** (mag_db / 20)
    full = np.concatenate([mag, mag[-2:0:-1]])
    cep = np.fft.ifft(np.log(np.maximum(full, 1e-6))).real
    fold = np.zeros(N)
    fold[0] = cep[0]
    fold[1:N // 2] = 2 * cep[1:N // 2]
    fold[N // 2] = cep[N // 2]
    h = np.fft.ifft(np.exp(np.fft.fft(fold))).real
    L = int(a.length_ms / 1000 * sr)
    h = h[:L]
    fade = int(L * 0.3)
    h[-fade:] *= np.hanning(2 * fade)[fade:]
    h /= max(np.max(np.abs(h)), EPS) / 0.9
    # loudness neutral: normalise so that broadband energy gain ~ 0 dB on the DI
    test = signal.fftconvolve(d[: sr * 20], h)[: len(d[: sr * 20])]
    g = math.sqrt(np.mean(d[: sr * 20] ** 2) / max(np.mean(test ** 2), EPS))
    h *= g
    peak = np.max(np.abs(h))
    if peak > 0.99:
        h *= 0.99 / peak
    sf.write(a.out, h.astype(np.float32), sr, subtype="FLOAT")
    info = {"ir": os.path.abspath(a.out), "sr": sr, "length_ms": a.length_ms, "alignment": al,
            "median_coherence_200_5k": r(float(np.median(gco[(grid > 200) & (grid < 5000)])), 2),
            "correction_db_at": {f"{hz}": r(float(np.interp(hz, grid, gdb)), 1)
                                 for hz in (80, 150, 250, 500, 1000, 1500, 2500, 4000, 8000, 12000) if hz < fmax}}
    print(json.dumps(info, ensure_ascii=False, indent=1))
    try:
        import matplotlib
        matplotlib.use("Agg")
        import matplotlib.pyplot as plt
        fig, ax = plt.subplots(figsize=(10, 3.5))
        ax.semilogx(grid, gdb)
        ax.set_xlim(30, fmax)
        ax.grid(True, which="both", alpha=0.3)
        ax.set_title("Коррекция 'пьезо -> микрофон' (АЧХ импульса)")
        ax.set_ylabel("dB")
        fig.tight_layout()
        fig.savefig(os.path.splitext(a.out)[0] + "_curve.png", dpi=110)
        plt.close(fig)
    except ImportError:
        pass


# ------------------------------------------------------------------ selftest
def cmd_selftest(_a):
    sr = 48000
    t = np.arange(sr * 20) / sr
    amp = 10 ** (-23 / 20)
    x = np.stack([amp * np.sin(2 * np.pi * 1000 * t)] * 2, axis=1)
    lm = loudness_metrics(x, sr)
    ok = abs(lm["lufs_i"] + 23.0) < 0.1
    print(f"EBU: стерео синус 1 кГц -23 dBFS -> {lm['lufs_i']:.2f} LUFS (ожидается -23.0): {'OK' if ok else 'FAIL'}")
    tp = true_peak_db(x)
    ok2 = abs(tp + 23.0) < 0.1
    print(f"True peak: {tp:.2f} dBTP (ожидается -23.0): {'OK' if ok2 else 'FAIL'}")
    sys.exit(0 if ok and ok2 else 1)


def main():
    ap = argparse.ArgumentParser(description="Уши для Claude: анализ стемов, микса и мастера")
    sub = ap.add_subparsers(dest="cmd", required=True)
    an = sub.add_parser("analyze", help="полный отчёт")
    an.add_argument("--vocal", action="append", default=[], help="стем вокала (WAV/FLAC/AIFF)")
    an.add_argument("--guitar", action="append", default=[], help="стем гитары (можно несколько: DI, mic)")
    an.add_argument("--mix", action="append", default=[], help="сведённый микс (до мастеринга)")
    an.add_argument("--master", action="append", default=[], help="мастер")
    an.add_argument("--file", action="append", default=[], help="любой другой файл")
    an.add_argument("--ref", action="append", default=[], help="референс-трек (готовая песня жанра)")
    an.add_argument("--ref-vocal", action="append", default=[], help="вокальный стем референса")
    an.add_argument("--ref-guitar", action="append", default=[], help="гитарный стем референса")
    an.add_argument("--stage", choices=["raw", "mix", "master"], default="mix")
    an.add_argument("--target-lufs", type=float, default=-14.0)
    an.add_argument("--previous", help="report.json прошлого прогона - покажет, что изменилось")
    an.add_argument("--label", help="подпись прогона (например 'v3: +de-esser')")
    an.add_argument("--out", default="analysis", help="папка для отчёта")
    an.add_argument("--no-plots", action="store_true")
    an.add_argument("--spectrogram", action="store_true", help="добавить спектрограммы вокала/гитары")
    an.set_defaults(func=cmd_analyze)
    al = sub.add_parser("align", help="сдвиг и полярность между двумя записями (DI vs mic)")
    al.add_argument("a")
    al.add_argument("b")
    al.add_argument("--max-ms", type=float, default=20.0)
    al.set_defaults(func=cmd_align)
    ir = sub.add_parser("make-ir", help="IR 'пьезо -> микрофон' из одновременной записи DI и микрофона")
    ir.add_argument("--di", required=True)
    ir.add_argument("--mic", required=True)
    ir.add_argument("--out", required=True, help="куда сохранить IR (.wav)")
    ir.add_argument("--length-ms", type=float, default=60.0)
    ir.add_argument("--smoothing", type=float, default=1 / 6, help="сглаживание в октавах (1/6 по умолчанию)")
    ir.add_argument("--max-db", type=float, default=15.0, help="ограничение коррекции, dB")
    ir.set_defaults(func=cmd_make_ir)
    st = sub.add_parser("selftest", help="проверка измерителя громкости")
    st.set_defaults(func=cmd_selftest)
    a = ap.parse_args()
    a.func(a)


if __name__ == "__main__":
    main()
