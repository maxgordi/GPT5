"""Общие функции: поиск ТВ, подключение по SSAP (ws://IP:3000), хранение ключа."""
import asyncio
import ipaddress
import json
import socket
from pathlib import Path

from bscpylgtv import WebOsClient

CONFIG = Path(__file__).with_name("lgtv_config.json")

# Что оставляем всегда (не закрываем и не удаляем)
KEEP_APPS = {
    "youtube.leanback.v4",      # YouTube
    "com.webos.app.livetv",     # Эфирное/кабельное ТВ
    "com.webos.app.hdmi1", "com.webos.app.hdmi2", "com.webos.app.hdmi3",
    "com.webos.app.home", "com.webos.app.launcher",
}

# Системные приложения, которые безопасно закрывать (не удалять!), если висят в фоне
CLOSABLE_SYSTEM = {
    "com.webos.app.browser", "com.webos.app.discovery", "com.webos.app.lgstore",
    "com.webos.app.smartshare", "com.webos.app.photovideo", "com.webos.app.music",
    "com.webos.app.notificationcenter", "com.webos.app.miracast",
    "com.webos.app.screenshare", "com.webos.app.connectionwizard",
    "com.webos.app.livemenuplayer", "com.webos.app.tvuserguide",
    "com.webos.app.recordings", "com.webos.app.customersupport",
    "com.webos.app.membership", "com.webos.app.livezoom", "com.webos.app.ibd",
    "com.webos.app.channeledit", "com.webos.app.inputcommon",
    "netflix", "amazon", "com.webos.app.facebook", "ivi", "megogo", "okko",
}


def load_config():
    if CONFIG.exists():
        return json.loads(CONFIG.read_text(encoding="utf-8"))
    return {}


def save_config(cfg):
    CONFIG.write_text(json.dumps(cfg, ensure_ascii=False, indent=2), encoding="utf-8")


# ---------- Поиск ТВ ----------

def ssdp_discover(timeout=3.0):
    """SSDP M-SEARCH: ищем LG webOS (second-screen) и вообще LG-устройства."""
    msg = ("M-SEARCH * HTTP/1.1\r\nHOST: 239.255.255.250:1900\r\n"
           'MAN: "ssdp:discover"\r\nMX: 2\r\n'
           "ST: urn:lge-com:service:webos-second-screen:1\r\n\r\n").encode()
    found = {}
    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM, socket.IPPROTO_UDP)
    s.setsockopt(socket.IPPROTO_IP, socket.IP_MULTICAST_TTL, 2)
    s.settimeout(timeout)
    try:
        for _ in range(2):
            s.sendto(msg, ("239.255.255.250", 1900))
        while True:
            data, (ip, _) = s.recvfrom(4096)
            text = data.decode(errors="ignore")
            if "lge" in text.lower() or "webos" in text.lower():
                found[ip] = text.split("\r\n")[0]
    except socket.timeout:
        pass
    finally:
        s.close()
    return found


def local_subnet():
    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    try:
        s.connect(("8.8.8.8", 80))
        ip = s.getsockname()[0]
    finally:
        s.close()
    return ipaddress.ip_network(f"{ip}/24", strict=False)


async def _probe(ip, port, timeout=0.6):
    try:
        _, w = await asyncio.wait_for(asyncio.open_connection(str(ip), port), timeout)
        w.close()
        return True
    except Exception:
        return False


async def port_scan(net=None):
    """Ищем хосты с открытыми 3000 (ws) и 3001 (wss) в локальной /24."""
    net = net or local_subnet()
    hosts = list(net.hosts())
    sem = asyncio.Semaphore(128)

    async def check(ip):
        async with sem:
            p3000 = await _probe(ip, 3000)
            p3001 = await _probe(ip, 3001) if p3000 else False
            return str(ip), p3000, p3001

    res = await asyncio.gather(*(check(ip) for ip in hosts))
    return [(ip, a, b) for ip, a, b in res if a]


# ---------- Подключение ----------

class JsonKeyStorage:
    """Хранит ключ клиента в lgtv_config.json (вместо sqlite по умолчанию)."""
    async def set_key(self, ip, key):
        cfg = load_config()
        if cfg.get("client_key") != key or cfg.get("ip") != ip:
            cfg.update(ip=ip, client_key=key)
            save_config(cfg)
            print(f"Ключ клиента сохранён в {CONFIG}")

    async def get_key(self, ip):
        cfg = load_config()
        return cfg.get("client_key") if cfg.get("ip") == ip else None

    async def list_keys(self):
        cfg = load_config()
        return {cfg["ip"]: cfg["client_key"]} if cfg.get("client_key") else {}


async def connect(ip=None):
    cfg = load_config()
    ip = ip or cfg.get("ip")
    if not ip:
        raise SystemExit("IP телевизора не известен. Сначала: python lgtv.py find")
    storage = JsonKeyStorage()
    if not await storage.get_key(ip):
        print(">>> На экране ТВ появится запрос на подключение — подтвердите его пультом (OK/«Принять»).")
    client = await WebOsClient.create(
        ip, storage=storage, without_ssl=True,   # webOS 2: ws://IP:3000
        states=[], timeout_connect=5, ping_interval=None,
    )
    await client.connect()
    return client


async def list_apps(client):
    return await client.get_apps_all() or []


async def running_apps(client, apps):
    """Публичного SSAP-метода «список запущенных» на webOS 2 нет.
    Пробуем по очереди: getForegroundAppInfo + system.launcher/getAppState по каждому app id."""
    running = {}
    try:
        fg = await client.get_current_app()
        if fg:
            running[fg] = "на экране"
    except Exception:
        pass
    state_api = True
    for a in apps:
        if not state_api:
            break
        try:
            r = await asyncio.wait_for(
                client.request("system.launcher/getAppState", {"id": a["id"]}), 3)
            if r.get("running"):
                running.setdefault(a["id"], "на экране" if r.get("visible") else "в фоне")
        except asyncio.TimeoutError:
            continue
        except Exception as e:
            msg = str(e).lower()
            if "404" in msg or "no such service" in msg or "401" in msg or "denied" in msg:
                state_api = False   # метод недоступен на этой прошивке
    return running, state_api


async def close_app(client, app_id):
    """system.launcher/close, а для веб-приложений запасной webapp/closeWebApp."""
    for uri, payload in (("system.launcher/close", {"id": app_id}),
                         ("webapp/closeWebApp", {"webAppId": app_id})):
        try:
            await asyncio.wait_for(client.request(uri, payload), 4)
            return True, uri
        except Exception as e:
            err = str(e)
    return False, err
