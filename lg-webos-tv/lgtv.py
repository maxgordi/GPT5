#!/usr/bin/env python3
"""Управление LG webOS 2 по официальному протоколу SSAP (ws://IP:3000).

  python lgtv.py find                 # найти ТВ в сети (SSDP + скан портов 3000/3001)
  python lgtv.py pair [IP]            # сопряжение, ключ -> lgtv_config.json
  python lgtv.py apps                 # все приложения + запущенные
  python lgtv.py remove               # удалить скачанные приложения (кроме YouTube) — с подтверждением
  python lgtv.py close                # закрыть фоновые приложения — с подтверждением
  python lgtv.py settings             # проверить, что из фоновых настроек видно через API
"""
import asyncio
import json
import sys

import lgtv_common as c


def ask_yes(prompt):
    return input(f"{prompt} Введите «да» для продолжения: ").strip().lower() in ("да", "yes", "y", "д")


async def cmd_find():
    print("SSDP-поиск LG webOS...")
    ssdp = c.ssdp_discover()
    for ip, line in ssdp.items():
        print(f"  SSDP: {ip}  {line}")
    print(f"Скан {c.local_subnet()} на портах 3000/3001...")
    hits = await c.port_scan()
    for ip, p3000, p3001 in hits:
        print(f"  {ip}: 3000={'открыт' if p3000 else '-'} 3001={'открыт' if p3001 else '-'}"
              + ("  <- найден и по SSDP" if ip in ssdp else ""))
    candidates = [ip for ip, *_ in hits if ip in ssdp] or list(ssdp) or [ip for ip, *_ in hits]
    if len(candidates) == 1:
        cfg = c.load_config(); cfg["ip"] = candidates[0]; c.save_config(cfg)
        print(f"\nТелевизор: {candidates[0]} (сохранён в {c.CONFIG})")
    elif not candidates:
        print("\nНе найдено. Проверьте, что ТВ включён и в той же сети; на ТВ: Настройки → Общие → "
              "«Включение ТВ с мобильного» / LG Connect Apps должно быть ВКЛ.")
    else:
        print("\nНесколько кандидатов — выполните: python lgtv.py pair <IP>")


async def cmd_pair(ip=None):
    client = await c.connect(ip)
    info = {}
    try:
        info = await client.request("com.webos.service.update/getCurrentSWInformation")
    except Exception:
        pass
    print("Подключено.", info.get("product_name", ""), info.get("model_name", ""),
          info.get("major_ver", ""), info.get("minor_ver", ""))
    await client.disconnect()


def fmt_apps(apps, running):
    rows = sorted(apps, key=lambda a: (a.get("systemApp", False), a.get("title", "")))
    print(f"{'ID':45} {'Название':28} {'Системное':9} {'Удаляемое':9} Состояние")
    print("-" * 110)
    for a in rows:
        print(f"{a['id'][:45]:45} {str(a.get('title',''))[:28]:28} "
              f"{'да' if a.get('systemApp') else 'нет':9} {'да' if a.get('removable') else 'нет':9} "
              f"{running.get(a['id'], '')}")


async def cmd_apps():
    client = await c.connect()
    apps = await c.list_apps(client)
    running, state_api = await c.running_apps(client, apps)
    fmt_apps(apps, running)
    print(f"\nВсего: {len(apps)}, системных: {sum(1 for a in apps if a.get('systemApp'))}, "
          f"удаляемых: {sum(1 for a in apps if a.get('removable'))}")
    print("Запущены:", ", ".join(f"{k} ({v})" for k, v in running.items()) or "нет данных")
    if not state_api:
        print("Примечание: system.launcher/getAppState недоступен на этой прошивке — "
              "видно только приложение на экране.")
    with open("apps.json", "w", encoding="utf-8") as f:
        json.dump(apps, f, ensure_ascii=False, indent=2)
    print("Полный ответ ТВ сохранён в apps.json")
    await client.disconnect()


async def cmd_remove():
    client = await c.connect()
    apps = await c.list_apps(client)
    targets = [a for a in apps if a.get("removable") and not a.get("systemApp")
               and a["id"] not in c.KEEP_APPS]
    if not targets:
        print("Удаляемых (скачанных) приложений, кроме нужных, нет.")
        return await client.disconnect()
    print("Будут удалены (скачанные из LG Store, не системные):")
    for a in targets:
        print(f"  {a['id']:45} {a.get('title','')}")
    if not ask_yes("Удалить эти приложения?"):
        print("Отменено."); return await client.disconnect()
    failed = []
    for a in targets:
        try:
            await asyncio.wait_for(
                client.request("com.webos.appInstallService/remove", {"id": a["id"]}), 10)
            print(f"  удалено: {a['id']}")
        except Exception as e:
            failed.append(a); print(f"  НЕ удалено: {a['id']} ({e})")
    if failed:
        print("\nПубличный SSAP не даёт удалять приложения на этой прошивке. Удалите вручную с пульта:\n"
              "  Home → наведите на приложение в ленте → долгое нажатие OK (или кнопка «карандаш»/Edit\n"
              "  в правом конце ленты) → значок корзины/«Удалить». Или: LG Content Store → Мои приложения.")
    await client.disconnect()


async def cmd_close(confirm=True):
    client = await c.connect()
    apps = await c.list_apps(client)
    running, state_api = await c.running_apps(client, apps)
    if state_api and running:
        targets = [a for a in running if a not in c.KEEP_APPS]
    else:
        # список запущенных недоступен — закрываем «вслепую» все не-нужные кандидаты
        ids = {a["id"] for a in apps}
        targets = sorted([a["id"] for a in apps if not a.get("systemApp") and a["id"] not in c.KEEP_APPS]
                         + [i for i in c.CLOSABLE_SYSTEM if i in ids and i not in c.KEEP_APPS])
    if not targets:
        print("Закрывать нечего."); return await client.disconnect()
    print("Будут закрыты (не удалены!):", *targets, sep="\n  ")
    if confirm and not ask_yes("Закрыть?"):
        print("Отменено."); return await client.disconnect()
    closed = 0
    for app_id in targets:
        ok, how = await c.close_app(client, app_id)
        if ok:
            closed += 1; print(f"  закрыто: {app_id} ({how})")
    print(f"Закрыто: {closed} из {len(targets)} (остальные, скорее всего, не были запущены).")
    await client.disconnect()


async def cmd_settings():
    client = await c.connect()
    probes = [
        ("option", ["liveplus", "livePlus", "acrOnOff", "autoUpdate", "smartServiceCountryCode2"]),
        ("general", ["liveplus", "livePlus", "acrOnOff"]),
        ("network", ["wolwowlOnOff"]),
    ]
    for cat, keys in probes:
        try:
            r = await client.get_system_settings(cat, keys)
            print(f"[{cat}] {json.dumps(r.get('settings', r), ensure_ascii=False)}")
        except Exception as e:
            print(f"[{cat}] недоступно: {e}")
    for uri in ("settings/setSystemSettings",):
        print(f"Проверка записи ({uri}) не выполняется без вашего «да» — "
              "на webOS 2 сторонним клиентам она обычно запрещена (401).")
    await client.disconnect()


def main():
    cmd, *args = sys.argv[1:] or ["help"]
    handlers = {"find": cmd_find, "pair": cmd_pair, "apps": cmd_apps,
                "remove": cmd_remove, "close": cmd_close, "settings": cmd_settings}
    if cmd not in handlers:
        print(__doc__); return
    asyncio.run(handlers[cmd](*args))


if __name__ == "__main__":
    main()
