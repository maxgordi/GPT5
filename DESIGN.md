---
name: SCMA-KVK
description: Capacity and facts stencilled onto the painted steel flank of a weighbridge.
colors:
  primer-steel: "#16181b"
  steel-raised: "#25292d"
  plate-seam: "#33383d"
  stencil-white: "#ecebe5"
  weathered-paint: "#b4b8bc"
  guard-orange: "#E58D00"
  guard-orange-hot: "#ffa31a"
  foundation-concrete: "#c2bfb8"
  plate-cream: "#e2dfd8"
  marking-ink: "#141618"
  ink-dim: "#3e4246"
  rule-line: "#4a4f55"
  bolt-head: "#5b6167"
  amber-deep: "#9a5d00"
typography:
  display:
    fontFamily: "'Sofia Sans Extra Condensed', 'Arial Narrow', sans-serif"
    fontSize: "clamp(46px, 5.9vw, 116px)"
    fontWeight: 900
    lineHeight: 0.92
    letterSpacing: "0.01em"
  numeral:
    fontFamily: "'Sofia Sans Extra Condensed', 'Arial Narrow', sans-serif"
    fontSize: "clamp(150px, 22vw, 380px)"
    fontWeight: 900
    lineHeight: 0.78
    letterSpacing: "0.01em"
  headline:
    fontFamily: "'Sofia Sans Extra Condensed', 'Arial Narrow', sans-serif"
    fontSize: "clamp(44px, 5.4vw, 96px)"
    fontWeight: 900
    lineHeight: 0.92
    letterSpacing: "0.01em"
  title:
    fontFamily: "'Sofia Sans Extra Condensed', 'Arial Narrow', sans-serif"
    fontSize: "clamp(24px, 1.8vw, 32px)"
    fontWeight: 800
    lineHeight: 1
    letterSpacing: "0.02em"
  data:
    fontFamily: "'Sofia Sans Extra Condensed', 'Arial Narrow', sans-serif"
    fontSize: "clamp(22px, 1.7vw, 32px)"
    fontWeight: 800
    lineHeight: 1.1
    letterSpacing: "0.02em"
    fontFeature: "\"tnum\" 1"
  button:
    fontFamily: "'Sofia Sans Extra Condensed', 'Arial Narrow', sans-serif"
    fontSize: "clamp(20px, 1.4vw, 26px)"
    fontWeight: 800
    lineHeight: 1
    letterSpacing: "0.03em"
  lead:
    fontFamily: "'Onest', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif"
    fontSize: "clamp(18px, 1.15vw, 22px)"
    fontWeight: 400
    lineHeight: 1.6
  body:
    fontFamily: "'Onest', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif"
    fontSize: "clamp(16px, 0.35vw + 14.6px, 19px)"
    fontWeight: 400
    lineHeight: 1.6
  label:
    fontFamily: "'Onest', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif"
    fontSize: "13px"
    fontWeight: 600
    lineHeight: 1.3
    letterSpacing: "0.1em"
rounded:
  none: "0px"
spacing:
  seam: "2px"
  gap-sm: "14px"
  gap-md: "22px"
  block: "clamp(28px, 3vw, 48px)"
  section: "clamp(72px, 8vw, 136px)"
  container: "min(1360px, 92vw)"
components:
  button-primary:
    backgroundColor: "{colors.guard-orange}"
    textColor: "{colors.marking-ink}"
    typography: "{typography.button}"
    rounded: "{rounded.none}"
    padding: "0.62em 1.1em 0.58em"
  button-primary-hover:
    backgroundColor: "{colors.guard-orange-hot}"
    textColor: "{colors.marking-ink}"
  button-outline:
    backgroundColor: "transparent"
    textColor: "{colors.stencil-white}"
    typography: "{typography.button}"
    rounded: "{rounded.none}"
    padding: "0.62em 1.1em 0.58em"
  button-outline-hover:
    backgroundColor: "{colors.stencil-white}"
    textColor: "{colors.marking-ink}"
  button-on-orange:
    backgroundColor: "{colors.marking-ink}"
    textColor: "{colors.stencil-white}"
    typography: "{typography.button}"
    rounded: "{rounded.none}"
    padding: "0.62em 1.1em 0.58em"
  guard-row:
    backgroundColor: "{colors.marking-ink}"
    height: "22px"
    width: "100%"
  data-plate-row:
    textColor: "{colors.stencil-white}"
    typography: "{typography.data}"
    padding: "0.34em 0"
  plate-cell:
    backgroundColor: "{colors.plate-cream}"
    textColor: "{colors.marking-ink}"
    rounded: "{rounded.none}"
    padding: "14px 16px 18px"
  plate-cell-hover:
    backgroundColor: "#ffffff"
  bolted-hatch:
    backgroundColor: "#000000"
    rounded: "{rounded.none}"
    padding: "0"
  underline-link:
    textColor: "{colors.stencil-white}"
    typography: "{typography.body}"
  contact-form-panel:
    backgroundColor: "{colors.stencil-white}"
    textColor: "{colors.marking-ink}"
    rounded: "{rounded.none}"
    padding: "clamp(20px, 2.4vw, 36px)"
---

# Design System: SCMA-KVK

## Overview

**Creative North Star: "The Painted Weighbridge Flank"**

The page is the side of a steel truck scale: graphite primer plate with a centre seam and grain, facts painted on it in a condensed heavy marking face, and the orange wheel-guard row bolted along every edge where one section meets the next. Light sections are the concrete foundation the platform sits on. Nothing floats, nothing is rounded, nothing is decorative that is not also a material of the trade: seams, bolt heads, inspection hatches, painted capacity markings.

Density is industrial and direct. Sections alternate between primer steel and foundation concrete, joined by the guard row, with one full orange section reserved for the state compensation. Facts are set as a wagon data plate (label left, painted value right) rather than as stat tiles or cards. Photographs sit in bolted hatches or thick steel frames, never in soft cards. Grids of products, logos, photos and documents are a single plate divided by 2px ink seams, not separated cards.

The marking face is painted plain, without stencil bridges: the owner chose legibility first. Over-spray is suggested only by a faint halo filter under the crisp letterform, never by breaking the glyphs.

**Key Characteristics:**
- Graphite primer steel and foundation concrete grounds, both with a fine painted grain.
- Orange trapezoid wheel-guard row as the only section divider.
- Condensed 800-900 uppercase marking face for headlines, values, numerals and buttons; Onest for reading.
- Square corners everywhere (0px radius).
- Plates divided by 2px ink seams; data set as ruled label/value rows.
- One choreographed hero sequence; everything else is still or nearly still.

## Colors

A two-ground industrial palette (primer steel and concrete) with one paint colour, the guard orange, that marks the most important fact or action on each ground.

### Primary
- **Guard Orange** (guard-orange): the wheel-guard paint. Primary buttons, the guard row, the second hero line (brand name), the compensation value in the data plate, step numerals, the "50+" numeral, underline-link rules, focus outlines and text selection. On the compensation section it becomes the whole ground.
- **Hot Guard Orange** (guard-orange-hot): hover state of orange plates and the video play tile only.
- **Deep Amber** (amber-deep): underline-link hover text on concrete, where guard orange would lack contrast.

### Neutral
- **Primer Steel** (primer-steel): the dark ground, with a centre plate seam and painted grain.
- **Raised Steel** (steel-raised): the thick frame around hatches, framed photos and video tiles.
- **Plate Seam** (plate-seam): the 1px outer outline around steel frames.
- **Stencil White** (stencil-white): all painted text and rules on steel; the contact form panel.
- **Weathered Paint** (weathered-paint): secondary text on steel (leads, data labels, step descriptions, captions).
- **Foundation Concrete** (foundation-concrete): the light ground, with the same grain.
- **Plate Cream** (plate-cream): cells inside plate grids on concrete (product, logo, news).
- **Marking Ink** (marking-ink): text on concrete and orange, the guard-row backing, the 2px plate seams.
- **Ink Dim** (ink-dim): secondary text on concrete and orange.
- **Rule Line** (rule-line): 1px rules between data rows, steps and columns on steel.
- **Bolt Head** (bolt-head): the bolt heads at hatch corners.

### Named Rules
**The One Paint Rule.** Guard orange is the only chromatic colour. It marks the action, the brand line, and the single most valuable fact on a ground; it never tints a surface except the one compensation section.

**The Two Grounds Rule.** Every section is either primer steel or foundation concrete (the compensation section is the sole orange ground), and consecutive grounds are joined by the guard row.

## Typography

**Display Font:** Sofia Sans Extra Condensed 800/900 (with 'Arial Narrow', sans-serif)
**Body Font:** Onest 400/500/600 (with system-ui stack)

**Character:** A tall, heavy cargo-marking face painted uppercase against a calm, modern grotesque for reading. The marking face carries every number the buyer needs; Onest carries every sentence.

### Hierarchy
- **Display** (900, clamp(46px, 5.9vw, 116px), 0.92): the H1 only, two painted lines, the second in guard orange.
- **Numeral** (900, clamp(150px, 22vw, 380px), 0.78): one giant painted figure per section at most ("−25%", "50+"), aria-hidden when it duplicates text.
- **Headline** (900, clamp(44px, 5.4vw, 96px), 0.92): section H2s, uppercase, max 16ch, balanced wrap.
- **Title** (800, clamp(24px, 1.8vw, 32px), 1, 0.02em): H3s, product names, step titles.
- **Data** (800, clamp(22px, 1.7vw, 32px), tabular numerals): values on the data plate.
- **Button** (800, clamp(20px, 1.4vw, 26px), 0.03em, uppercase): all actions.
- **Lead** (400, clamp(18px, 1.15vw, 22px), 1.6): the paragraph under a headline, max 56ch, in weathered paint or ink dim.
- **Body** (400, clamp(16px, ... 19px), 1.6): running text.
- **Label** (600, 13px, 0.1em, uppercase): data-plate labels, hatch captions, footer link row. Labels name a value next to them; they never sit above a headline.

### Named Rules
**The Legible Marking Rule.** The marking face is painted whole: no stencil bridges, no distressed glyphs. Over-spray is a soft halo (blur 1.1px at 45% alpha) merged under the crisp letter, applied to marking-class headlines and numerals only.

**The Numbers Are Painted Rule.** Measurements and capacities (6–22 м, 30–100 т, від 3 днів, −25%, 40+, 26) are set in the marking face, not in body text and not in a monospace.

## Layout

A single container (min(1360px, 92vw)) centred on full-bleed grounds. Sections breathe at clamp(72px, 8vw, 136px) top and bottom; blocks within a section step by clamp(28px, 3vw, 48px); a second headline inside a section is pushed down clamp(72px, 7vw, 120px).

The hero is an asymmetric 1.7fr / 1fr grid: marking, data plate and actions left, bolted hatch right. Other two-up sections use 1fr / 1fr splits, or an auto / 1fr split where a giant numeral holds the left column. Service is a full-bleed 50/50 photo and text split with no container.

Plate grids (products 5 columns, logos 7, gallery 6 with a 3x2 lead image, documents 5, news 3) share one construction: an ink-backed grid with 2px gaps and a 2px ink border so the gaps read as seams. Ruled lists (steps 5 columns, "why" 4 columns, history numbers 2 columns) hang from a 2px stencil-white top rule with 1px rule-line column dividers.

Breakpoints: 1100px (products 3, logos 4, why 2, steps 3), 860px (everything single column; hatch becomes a 12/5 strip; steps become numbered rows; gallery 2 with a full-width lead), 560px (products 2, logos 3, buttons full width). Divi's fixed header is cleared by hero top padding (80px plus clamp above 980px, 104px below).

## Elevation & Depth

Depth is physical, not ambient: steel frames are thick borders with a 1px seam outline, bolts are drawn, and only objects that would actually stand proud of the plate cast a shadow. Surfaces themselves are flat; grain and the centre seam give the steel its material.

### Shadow Vocabulary
- **Hatch drop** (`box-shadow: inset 0 0 0 1px #000, 0 30px 60px rgba(0,0,0,.45)`): the hero inspection hatch.
- **Framed photo** (`box-shadow: 0 24px 48px rgba(0,0,0,.4)`): steel-framed photos on steel.
- **Orange plate** (`box-shadow: 0 6px 16px rgba(0,0,0,.28)`, hover `0 10px 24px rgba(0,0,0,.34)`): the primary button only; outline buttons cast none.

### Named Rules
**The Bolted, Not Floating Rule.** A raised object is framed in raised steel (6-10px) with a 1px seam outline; shadows are soft and downward. No hard offset shadows, no glows.

## Shapes

Every corner is square (0px). The only non-rectangular form is the guard trapezoid (an orange trapezoid with a lighter top bevel, 120x22 repeated) and the round bolt heads at the four hatch corners. Rules are 2px for the top of a ruled block or a plate border and 1px for dividers inside it. Media keep fixed aspect ratios: hatch 4/5 (12/5 on mobile), product cells 16/10, video 16/9, logos 3/2, documents 400/516.

## Components

### Buttons
Painted plates, heavy and literal.
- **Shape:** square (0px), 3px border in the fill colour.
- **Primary:** guard-orange plate, marking-ink text, marking face uppercase, padding 0.62em 1.1em 0.58em, optional 0.9em phone icon; soft drop shadow.
- **Hover / Active / Focus:** hot guard orange with a deeper shadow; presses 1px down on active; 3px guard-orange outline offset 4px on focus-visible.
- **Outline:** transparent with a 3px stencil-white border on steel (marking-ink on concrete and orange); hover fills with the border colour and inverts the text.
- **On orange ground:** the primary becomes a marking-ink plate with stencil-white text.
- Actions sit in a wrapping row with 14px gaps, primary first.

### Guard Row
The signature seam. A 22px marking-ink band repeating the orange trapezoid guard every 120px, placed between consecutive sections, presentation-only. It is the only section divider.

### Data Plate
The wagon data panel. A definition list under a 2px stencil-white top rule, max 640px; each row is label left (label style, weathered paint) and value right (data style, stencil white), baseline-aligned, separated by 1px rule lines. The single most valuable value is painted guard orange.

### Bolted Hatch
The hero photo housing. A 10px raised-steel frame (6px on mobile) with a 1px seam outline, a bolt head drawn in each corner, the hatch-drop shadow, cover-cropped image, and a caption tab in primer steel at the bottom-left corner in label style.

### Plate Grid
Products, logos, gallery, documents and news are cells of one plate: ink-backed grid, 2px seams, 2px ink border. Product cells are plate cream with a 16/10 image, a title-style name (whole cell clickable) and a 14px type line; hover lightens the cell to white and scales the image to 1.05; focus-within draws a 3px guard-orange inset outline. Logos are greyscale, multiplied onto plate cream. Document cells are white and turn guard orange on hover.

### Ruled Steps
Numbered process list: each step hangs from the 2px top rule, the counter painted large (56px, 44px on mobile) in guard orange, title in title style, description in weathered paint. On mobile it becomes rows with the numeral in a 56px left column.

### Underline Link
Tertiary navigation (all news, partnership, email): weight 600 text over a 3px guard-orange bottom rule; hover turns the text guard orange on steel, deep amber on concrete.

### Video Tile
A 16/9 steel-framed button showing the YouTube poster under a bottom shade, with a 58px guard-orange play plate and a marking-face label tab. The iframe is injected only on click.

### Inputs / Fields (Divi contact form)
Stencil-white panel with a 4px guard-orange border; inputs are white, 2px marking-ink border, square, Onest 16px; focus turns the border guard orange. Submit follows the primary button.

### Motion
One focal sequence on the hero after fonts load: the H1 lines are revealed left to right by a sliding mask (0.95s, cubic-bezier(.45,.05,.25,1), brand line delayed 0.38s); the hatch opens top-down by clip-path (0.8s, cubic-bezier(.16,1,.3,1), 0.25s delay) while the photo settles from 1.05 scale; the sub-line fades in at 0.7s; data rows are stamped in from 10px left at 0.1s intervals from 0.85s; actions rise 8px at 0.9s. Two supporting moments on scroll: steps rise 12px in order (0.09s stagger), and the "−25%" numeral is stamped (from scale 1.12, rotate −2deg, overshoot ease). Hover: image zoom 0.6s cubic-bezier(.2,.7,.2,1); button colour 0.15s, press 0.12s. Below 860px the hatch fades instead of clipping. Everything is gated on a `kv-js` class, so without JS all content renders static.

**The Reduced Motion Rule.** Under prefers-reduced-motion every mask, clip, scale, translate and stagger is removed; elements only fade (0.3s, no delay) and hover zooms are off.

## Do's and Don'ts

### Do:
- **Do** join every pair of sections with the guard row and nothing else.
- **Do** set every measurement, capacity and key figure in the marking face, painted plain and legible.
- **Do** present facts as a data plate (label left, value right, ruled rows), with at most one value painted guard orange.
- **Do** build grids of items as one plate with 2px ink seams.
- **Do** house hero or key photos in raised-steel frames; the hero photo in the bolted hatch.
- **Do** keep every corner square (0px).
- **Do** keep all content readable without JS and honour reduced motion by collapsing to fades.

### Don't:
- **Don't** add stencil bridges or distressed glyphs to the marking face; legibility comes first.
- **Don't** introduce a second accent colour or blue; guard orange is the only paint.
- **Don't** separate items into rounded or floating cards; they are cells of one plate.
- **Don't** use hard offset shadows or glows; raised objects cast soft downward shadows only.
- **Don't** put small uppercase labels above headlines; labels belong beside the value they name.
- **Don't** use more than one giant numeral per section.
