# Claude сводит «голос + акустику» в Cubase

Скилл и инструменты, которые дают Claude «уши» в виде замеров и позволяют сводить вокал и электроакустику (Cort AD810E: пьезо DI + микрофон) по канонам студийного сведения с проверкой каждого шага цифрами.

## Идея

Claude не слышит, а скриншоты метров каждую секунду ему почти ничего не дают: картинка сжимается, движение между кадрами теряется, мгновенный спектр случаен. Поэтому работа разделена так:

```
           ┌──────────── слух (замеры) ─────────────┐
 Cubase ──► экспорт стемов ──► mix_analyzer.py ──► report.md / графики ──► Claude решает
   ▲                                                                       │
   │                                                                       ▼
   │      .vstpreset + cubase_recipe.md  ◄── mix_engine.py (реальные VST3) ◄── JSON-конфиг
   │                                                                       │
   └──── computer use: перенос настроек, чтение интегральных метров ◄──────┘
```

1. **mix_analyzer.py** измеряет то, что слушает звукорежиссёр: LUFS, true peak, динамику, тональный баланс по полосам, резонансы, сибилянты (с таймкодами), плозивы, ровность вокала, маскировку вокала гитарой, фазу DI/микрофона, стерео. Сравнивает с референсами и с прошлой версией.
2. **mix_engine.py** сводит офлайн по JSON-конфигу с **вашими** VST3 (FabFilter, Valhalla, Waves через pedalboard) и встроенными блоками (де-эссер, расчистка гитары под голос как TrackSpacer, сатурация, IR). Рендер занимает секунды. Claude меняет конфиг, рендерит и меряет снова.
3. **Перенос в Cubase:** `.vstpreset` каждого плагина + пошаговый `cubase_recipe.md`. Переносите вы или Claude через computer use.
4. **SKILL.md** + справочники: цепочки вокала и гитары, цифры-ориентиры, настройка Cubase под скриншоты, карта плагинов, библиотека приёмов топ-инженеров с протоколом пополнения.

## Установка

```bash
# Python 3.10+
pip install -r skill/acoustic-vocal-mix/scripts/requirements.txt
python skill/acoustic-vocal-mix/scripts/mix_analyzer.py selftest
```
Скилл:
- **Claude Code / Claude Desktop (Code):** скопируйте папку `skill/acoustic-vocal-mix` в `~/.claude/skills/` (или в `.claude/skills/` проекта).
- **Приложение Claude:** заархивируйте папку `acoustic-vocal-mix` в zip и загрузите в настройках скиллов.

Claude должен работать **на том же компьютере, что и Cubase** (Claude Desktop с computer use), чтобы запускать Python и видеть экран.

## Быстрый старт

```bash
cd my-song/
# 1) сырые стемы из Cubase (Channel Batch Export) -> stems/
python .../mix_analyzer.py analyze --stage raw --vocal stems/vocal.wav \
    --guitar stems/guitar_di.wav --guitar stems/guitar_mic.wav \
    --ref refs/song1.wav --ref refs/song2.wav --out analysis/raw --spectrogram

# 2) фаза DI <-> микрофон и свой IR "пьезо -> мик"
python .../mix_analyzer.py align stems/guitar_di.wav stems/guitar_mic.wav
python .../mix_analyzer.py make-ir --di stems/guitar_di.wav --mic stems/guitar_mic.wav --out ad810e_ir.wav

# 3) сведение и итерации
cp .../mix_config.example.json mix.json      # правим
python .../mix_engine.py render mix.json --label v1 --analyze
python .../mix_engine.py render mix.json --label v2 --analyze --previous renders/v1/analysis/report.json
```
Или просто скажите Claude: «Сведи вокал и гитару из папки my-song, референсы в refs/». Скилл ведёт его по этому пайплайну.

## Запись (коротко)

- 24 бит / 48 кГц, пики −12…−6 dBFS, без клипов.
- Гитару пишите **одновременно DI и микрофоном**: конденсатор в 15–30 см на 12–14 лад, не в розетку. Потом выравнивание фазы (`align`) и свой IR (`make-ir`).
- EQ на гитаре (CE304T) в ноль, громкость почти на максимум, свежие струны.
- Вокал: поп-фильтр, 15–20 см, ровная дистанция; 3–5 дублей для компинга.
- Модели **AT1010** у Audio-Technica нет. Вероятно, это AT2010/AT2020/AT2035, советы те же.

Подробности: `skill/acoustic-vocal-mix/references/`.

## Что проверено, а что нет

- Измеритель громкости совпадает с эталоном EBU (синус 1 кГц −23 dBFS → −23.0 LUFS) и с `pyloudnorm` до сотых.
- Анализ, выравнивание, `make-ir`, встроенные процессоры движка, рецепт и сравнение версий проверены на синтетических дорожках (нашли сдвиг 42 сэмпла, плозивы с точными таймкодами, кряк 1.6 кГц; де-эссер снизил пики эсов с +7.4 до −5 dB относительно гласных).
- **Загрузка ваших VST3 через pedalboard здесь не проверялась** (в облачной Linux-среде нет плагинов). Первым делом на своей машине запустите `list-plugins -v` и `params`.
- Пороги в правилах (сибилянты, ровность, «вокал над гитарой») служат стартовыми эвристиками. Точнее всего работают с референсами, разделёнными на стемы (`--ref-vocal`, `--ref-guitar`).
