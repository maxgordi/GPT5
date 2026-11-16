#!/bin/bash
# =====================================================================
#  Весь звук MacBook -> телевизор LG по Wi-Fi, без картинки, без потерь.
#  Схема: macOS (AirPlay, ALAC без потерь) -> AirConnect на этом Mac
#         -> телевизор по DLNA (FLAC без потерь).
#
#  Запуск:   bash ~/Downloads/tv-audio.command        (первый раз)
#            двойной клик по «TV Audio» на рабочем столе (потом)
#  Если звука нет:  bash ~/Desktop/tv-audio.command wav
#                   bash ~/Desktop/tv-audio.command pcm
# =====================================================================
set -u

# --- Настройки (можно не трогать) ---
WIFI_SSID=""          # имя домашней Wi-Fi; пусто = оставить текущую сеть
WIFI_PASS=""          # пароль; пусто = взять из Связки ключей macOS
CODEC="${1:-flac:5}"  # flac:5 (без потерь) | wav | pcm (тоже без потерь, если ТВ не берёт FLAC)
LATENCY="1000:2000"   # буфер, мс: защищает от заиканий по Wi-Fi (звук отстаёт ~2 с)
AC_VER="1.12.4"
DIR="$HOME/TVAudio"
BIN="$DIR/airupnp-macos-static"
LOG="$DIR/airupnp.log"
PIDF="$DIR/airupnp.pid"

say() { printf '\n\033[1m%s\033[0m\n' "$*"; }
die() { printf '\n\033[31mОшибка: %s\033[0m\n' "$*"; read -r -p "Нажмите Enter, чтобы закрыть..."; exit 1; }

mkdir -p "$DIR"

# --- 0. Ярлык на рабочем столе ---
SELF="$(cd "$(dirname "$0")" && pwd)/$(basename "$0")"
if [ "$SELF" != "$HOME/Desktop/tv-audio.command" ]; then
  cp "$SELF" "$HOME/Desktop/tv-audio.command" 2>/dev/null && \
    echo "Скрипт скопирован на рабочий стол: ~/Desktop/tv-audio.command"
fi
chmod +x "$HOME/Desktop/tv-audio.command" 2>/dev/null
xattr -d com.apple.quarantine "$HOME/Desktop/tv-audio.command" 2>/dev/null

# --- 1. Wi-Fi / роутер ---
say "1/4 Проверяю Wi-Fi..."
WIFI_DEV=$(networksetup -listallhardwareports | awk '/Hardware Port: (Wi-Fi|AirPort)/{getline; print $2; exit}')
[ -n "$WIFI_DEV" ] || die "Не найден Wi-Fi адаптер."
networksetup -setairportpower "$WIFI_DEV" on
if [ -n "$WIFI_SSID" ]; then
  echo "Подключаюсь к сети «$WIFI_SSID»..."
  if [ -n "$WIFI_PASS" ]; then networksetup -setairportnetwork "$WIFI_DEV" "$WIFI_SSID" "$WIFI_PASS"
  else networksetup -setairportnetwork "$WIFI_DEV" "$WIFI_SSID"; fi
fi
MYIP=""
for _ in $(seq 1 20); do
  MYIP=$(ipconfig getifaddr "$WIFI_DEV" 2>/dev/null) && [ -n "$MYIP" ] && break
  sleep 1
done
[ -n "$MYIP" ] || die "Mac не подключён к Wi-Fi. Подключитесь к домашней сети и запустите снова."
GW=$(route -n get default 2>/dev/null | awk '/gateway/{print $2}')
echo "Wi-Fi: $WIFI_DEV, IP Mac: $MYIP, роутер: ${GW:-?}"
[ -n "$GW" ] && ! ping -c1 -t2 "$GW" >/dev/null 2>&1 && echo "Внимание: роутер не отвечает на ping (не всегда проблема)."

# --- 2. AirConnect (мост AirPlay -> DLNA) ---
say "2/4 Готовлю AirConnect $AC_VER..."
if [ ! -x "$BIN" ]; then
  TMP=$(mktemp -d)
  curl -fL --progress-bar -o "$TMP/ac.zip" \
    "https://raw.githubusercontent.com/philippe44/AirConnect/master/AirConnect-$AC_VER.zip" \
    || die "Не удалось скачать AirConnect (нужен интернет)."
  unzip -o -j -q "$TMP/ac.zip" "airupnp-macos-static" -d "$DIR" || die "В архиве нет airupnp-macos-static."
  rm -rf "$TMP"
  chmod +x "$BIN"
  xattr -d com.apple.quarantine "$BIN" 2>/dev/null
fi
echo "OK: $BIN"

# --- 3. Запуск моста ---
say "3/4 Ищу телевизор в сети (он должен быть ВКЛЮЧЁН)..."
pkill -f "airupnp-macos" 2>/dev/null; sleep 1
: > "$LOG"
"$BIN" -b "$WIFI_DEV" -c "$CODEC" -l "$LATENCY" -Z -k -d all=info -f "$LOG" -p "$PIDF" -x "$DIR/config.xml" &
AC_PID=$!
trap 'kill $AC_PID 2>/dev/null; echo; echo "Трансляция остановлена."' EXIT
echo "(Если macOS спросит «Разрешить входящие подключения для airupnp» — нажмите «Разрешить».)"

FOUND=""
for _ in $(seq 1 45); do
  kill -0 $AC_PID 2>/dev/null || die "AirConnect завершился. Лог: $LOG"
  FOUND=$(grep -o 'adding renderer ([^)]*)' "$LOG" | sed 's/adding renderer (//; s/)$//' | sort -u)
  [ -n "$FOUND" ] && break
  sleep 1
done
if [ -z "$FOUND" ]; then
  echo "Телевизор пока не найден. Проверьте: ТВ включён, в той же Wi-Fi сети."
  echo "Мост продолжит искать (поиск каждые 30 с)."
else
  echo "Найдено:"; echo "$FOUND" | sed 's/^/   • /; s/$/+/'
fi

# --- 4. Вывод звука ---
say "4/4 Выберите вывод звука"
echo "Пункт управления (значок ⌃ справа вверху) → Звук → выберите телевизор (имя с «+» на конце)."
echo "Или: Системные настройки → Звук → Выход."
open "x-apple.systempreferences:com.apple.Sound-Settings.extension" 2>/dev/null || \
  open /System/Library/PreferencePanes/Sound.prefPane 2>/dev/null
echo
echo "Кодек: $CODEC  (если звука нет: bash ~/Desktop/tv-audio.command wav)"
echo "Громкость регулируется и на Mac, и пультом ТВ."
read -r -p "Трансляция идёт. Нажмите Enter, чтобы остановить..."
