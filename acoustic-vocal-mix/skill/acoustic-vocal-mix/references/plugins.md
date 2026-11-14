# Плагины: задача → чем делать

Точный список того, что установлено, даёт `mix_engine.py list-plugins -v`. Здесь карта по задачам: что обычно есть у пользователя (FabFilter / Valhalla / Waves) и штатные инструменты Cubase.

## Карта задач

| задача | основной | альтернатива | штатный Cubase |
|---|---|---|---|
| EQ корректирующий / динамический | FabFilter Pro-Q 3/4 | Waves F6 | Frequency 2 |
| резонансы, резкость, пьезо-кряк | soothe2 (oeksound) | Pro-Q dynamic bands | — |
| компрессор быстрый (вокал) | Waves CLA-76 | Pro-C 2 (Vocal/Punch) | Compressor, Vintage Compressor |
| компрессор плавный (вокал, гитара) | Waves CLA-2A | Pro-C 2 (Opto) | Tube Compressor |
| де-эссер | FabFilter Pro-DS | Waves R-DeEsser, Sibilance | DeEsser |
| многополосная динамика | FabFilter Pro-MB | Waves C4/C6 | Multiband Compressor |
| сатурация | FabFilter Saturn 2 | Soundtoys Decapitator, Waves J37/Kramer Tape | Magneto II, Quadrafuzz |
| «воздух» / тон | Waves PuigTec EQP-1A | Pro-Q high shelf | — |
| ревер plate | Valhalla Plate / VintageVerb | Waves Abbey Road Plates, Pro-R 2 | REVerence, RoomWorks |
| ревер room | Valhalla Room / VintageVerb | Pro-R 2 | REVerence |
| дилей | Valhalla Delay / FabFilter Timeless 3 | Waves H-Delay | PingPongDelay, MonoDelay |
| райдер громкости вокала | Waves Vocal Rider | автоматизация | — |
| расчистка гитары под голос | Pro-Q dynamic + sidechain | Wavesfactory TrackSpacer, Neutron Unmask | Frequency 2 (dynamic + side-chain) |
| дабл / ширина | Waves Doubler | Soundtoys MicroShift | MultiTap Delay |
| шина (клей) | Waves SSL G-Master Buss Comp | Pro-C 2 (Bus) | Bus Compressor |
| лимитер мастера | FabFilter Pro-L 2 | Waves L2 | Brickwall Limiter, Maximizer |
| свёртка IR (пьезо → мик) | REVerence (импорт IR) | любой IR-лоадер | REVerence |

## Измерители для скриншотов

| плагин | что Claude видит на скриншоте |
|---|---|
| **Youlean Loudness Meter 2** (free/Pro) | крупные цифры Integrated / Short-Term / LRA / True Peak + график истории |
| **SuperVision** (Cubase) | модульный: громкость, спектр, фаза, корреляция, уровни |
| **iZotope Tonal Balance Control 2** | коридор нормального спектра жанра, сразу видно выход за коридор |
| **FabFilter Pro-Q 3/4** | спектр до/после, столкновения с другим экземпляром (вокал ↔ гитара) |
| **ADPTR Metric AB** / **Mastering The Mix REFERENCE 2** | сравнение с референсом на одной громкости, разница спектра и динамики |
| **Voxengo SPAN** (free) | усреднённый спектр с удержанием пиков |

## Что докупить под эту задачу (по полезности)

1. **soothe2**: главный инструмент против пьезо-кряка и резкости вокала.
2. **iZotope RX (Standard)**: шум, фон, щелчки рта, плозивы, дыхание. Чистый исходник важнее любого EQ.
3. **Melodyne 5** (если мало VariAudio): естественный тюнинг.
4. **Youlean Loudness Meter 2 Pro + Tonal Balance Control 2**: наглядные измерители для computer use.
5. **Metric AB или REFERENCE 2**: референс прямо в Cubase.
6. **TrackSpacer**: расчистка под голос в одну кнопку (можно и через Pro-Q).
7. **Sound Radix Auto-Align 2**: фаза DI ↔ мик (бесплатная альтернатива: `mix_analyzer.py align`).
8. **IR акустики** (Nembrini Acoustic Voice / 3 Sigma Audio): если не хочется делать свой IR через `make-ir`.
9. **Опционально: «ассистенты»** iZotope Nectar (вокал), Neutron (микс), Ozone (мастер). Дают стартовую точку за минуту, а Claude проверяет её замерами и доводит.

## Подключение реальных плагинов к движку

```bash
python scripts/mix_engine.py list-plugins -v
python scripts/mix_engine.py params "C:/Program Files/Common Files/VST3/FabFilter Pro-Q 3.vst3" --filter "band 1"
python scripts/mix_engine.py params "C:/Program Files/Common Files/VST3/WaveShell1-VST3 15.0_x64.vst3" --name "CLA-76 Mono"
```
```json
{"type": "vst3", "path": "C:/Program Files/Common Files/VST3/ValhallaVintageVerb.vst3",
 "params": {"mix": 100, "decay": 1.8, "predelay": 40}}
```
Имена параметров (`mix`, `decay`…) условные: настоящие возьми из вывода `params`. Значения задаются в единицах, которые показывает плагин. После загрузки движок сохраняет `.vstpreset` с точным состоянием, его можно открыть в Cubase.

Ограничения pedalboard: некоторые плагины с защитой или оболочкой (часть Waves, Antares, UAD) могут не загрузиться или потребовать `--name`. Тогда используй встроенный аналог в движке и перенеси значения в Cubase руками.
