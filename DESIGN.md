---
name: Pitcar Academy
description: The "night garage" world. A rotatable 3D car and a running AC loop on a diagnostic stage, set on a flat service-manual page. Covers the homepage, the online-class catalogue and the AC class page; other sales pages still follow docs/design-system.md.
colors:
  accent-fill: "#CC0000"
  accent-fill-hover: "#AA0000"
  accent: "#FF6464"
  accent-light: "#CC0000"
  ground: "#0A0A0A"
  ground-alt: "#121212"
  surface: "#161616"
  line: "#2A2A2A"
  body: "#FAFAF9"
  muted: "#A8A29E"
  faint: "#57534E"
  ground-light: "#FAFAF9"
  ground-alt-light: "#F3F3F1"
  surface-light: "#FFFFFF"
  line-light: "#E7E5E4"
  body-light: "#0A0A0A"
  muted-light: "#57534E"
  faint-light: "#A8A29E"
  on-accent-fill: "#FFFFFF"
typography:
  display:
    fontFamily: "'Barlow Condensed', ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(2.875rem, 8.2vw, 6rem)"
    fontWeight: 700
    lineHeight: 0.92
    letterSpacing: "-0.01em"
  headline:
    fontFamily: "'Barlow Condensed', ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(2.125rem, 5vw, 3.75rem)"
    fontWeight: 700
    lineHeight: 0.98
    letterSpacing: "-0.005em"
  title:
    fontFamily: "'Barlow Condensed', ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(1.375rem, 2.4vw, 1.75rem)"
    fontWeight: 600
    lineHeight: 1.1
  numeral:
    fontFamily: "'Barlow Condensed', ui-sans-serif, system-ui, sans-serif"
    fontWeight: 700
    lineHeight: 0.95
    fontFeature: "'tnum'"
  lead:
    fontFamily: "'Inter Variable', ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.1875rem"
    fontWeight: 400
    lineHeight: 1.7
  body:
    fontFamily: "'Inter Variable', ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.65
  label:
    fontFamily: "'Barlow Condensed', ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 600
    lineHeight: 1.1
    letterSpacing: "0.03em"
  readout:
    fontFamily: "ui-monospace, 'SF Mono', Menlo, Consolas, monospace"
    fontSize: "0.75rem"
    lineHeight: 1.4
    letterSpacing: "0.14em"
rounded:
  none: "0"
spacing:
  shell-inline: "1.25rem"
  shell-inline-sm: "2rem"
  shell-inline-lg: "2.5rem"
  section: "5rem"
  section-lg: "8.5rem"
  section-tight: "3rem"
  section-tight-lg: "4rem"
  column-gap: "2.5rem"
  column-gap-lg: "4rem"
  node-indent: "1.75rem"
  chip-gap: "0.5rem"
  hud-pad: "0.875rem"
  hud-pad-lg: "1.25rem"
  stage-grid: "3rem"
components:
  button-primary:
    backgroundColor: "{colors.accent-fill}"
    textColor: "{colors.on-accent-fill}"
    rounded: "{rounded.none}"
    padding: "0.875rem 1.75rem"
    height: "3rem"
  button-primary-hover:
    backgroundColor: "{colors.accent-fill-hover}"
  button-ghost:
    backgroundColor: "transparent"
    textColor: "{colors.body}"
    rounded: "{rounded.none}"
    padding: "0.875rem 1.75rem"
    height: "3rem"
  chip:
    backgroundColor: "{colors.ground}"
    textColor: "{colors.body}"
    typography: "{typography.label}"
    rounded: "{rounded.none}"
    padding: "0.5rem 0.875rem"
    height: "2.75rem"
  chip-selected:
    backgroundColor: "{colors.accent-fill}"
    textColor: "{colors.on-accent-fill}"
  hud-button:
    backgroundColor: "{colors.ground}"
    textColor: "{colors.body}"
    typography: "{typography.readout}"
    rounded: "{rounded.none}"
    padding: "0.5rem 0.875rem"
    height: "2.75rem"
  hud-button-pressed:
    textColor: "{colors.accent}"
  tooltip:
    backgroundColor: "{colors.ground}"
    textColor: "{colors.body}"
    typography: "{typography.readout}"
    rounded: "{rounded.none}"
    padding: "0.3rem 0.55rem"
  pause-control:
    textColor: "{colors.muted}"
    typography: "{typography.label}"
    rounded: "{rounded.none}"
    padding: "0.5rem 0.75rem"
    height: "2.75rem"
  tinted-block:
    backgroundColor: "{colors.ground-alt}"
    textColor: "{colors.body}"
    rounded: "{rounded.none}"
    padding: "2.25rem"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.body}"
    rounded: "{rounded.none}"
    padding: "0.75rem 1rem"
    height: "3rem"
---

# Design System: Pitcar Academy

## Overview

**Creative North Star: "The Diagnostic Bay"**

A machine sits on a diagnostic stage and the visitor operates it. On the homepage it is a car in real 3D: drag to turn it, switch between a painted body and a see-through shell, choose fuel, system and gearbox, pick a component, then put that component on the bench and pull it apart with a slider. On the AC class page it is a complete refrigerant loop that runs, and that can be broken six ways. The stage is dressed as a scanner display: a faint grid, four corner brackets, small monospace readouts, and controls that sit on the picture like instrument buttons.

Everything around the stage is a service manual. One ground colour, uppercase condensed headings, hairline rules, numbered steps, a small square node on a short leader in front of list items. That part of the page is flat on purpose, so the only thing on screen with light, depth and shadow is the machine.

Dark is the default: near black ground, near white ink, one red. In the dark theme the stage also carries scanlines, a red wash, a red rim light and a red light under the car. The light theme is the same page on paper: off-white ground, black ink, the same red, and every one of those light effects switched off. The header toggle switches theme and a stored choice always wins over the page default.

**Scope.** This document describes the pages whose root element carries the `garage` class, set by `world="garage"` on the base layout. Today that is `/` (`src/pages/index.astro`), `/kelas-online/` and `/kelas-online-ac/`, with the components in `src/components/garage/`, the scene code in `src/scripts/garage/` and the styles in `src/styles/garage.css`. The other sales pages (`kelas-online-inspeksi`, `kelas-online-fundamental`, `kelas/`, `faq`) are not in this world and follow `docs/design-system.md`. Both worlds share the colour tokens, the two typefaces, the page shell, the header, the footer, the lead form and the floating action.

**Key Characteristics:**
- One 3D stage per page, operated by the visitor. A 2D line drawing stands in only while the model loads or where WebGL is unavailable.
- The stage is a display: grid, corner brackets, monospace readouts, instrument buttons. The rest of the page is a manual: hairlines, square corners, no cards.
- One red, with a fixed meaning. It marks the primary action, the selected or suspected part, and heat or high pressure. Cold and normal are drawn in ink.
- Light is a night-only material. Scanlines, red wash, rim light, underglow and glow on controls exist in the dark theme and are off on paper.
- Motion shows how a mechanism works. Anything that loops can be stopped, and reduced-motion visitors start with the machine stopped.

## Colors

A two-ink palette, near black and near white, with a single red split into a fill and a text colour so both pass contrast in both themes. The frontmatter lists the dark (default) value under the plain token name and the light value under the `-light` suffix. In code the tokens are RGB triplets in `src/styles/global.css` that flip under the `dark` class, so a component names a token once and never writes a theme variant. The 3D scene reads the same tokens at runtime and re-reads them when the theme changes.

### Primary
- **Workshop Red, fill** (`accent-fill`): the primary button, the selected chip, the corner marks on photographs, the top edge of the tinted block, text selection, and the red parts inside the 3D model (the high-pressure pipes, the tail light, the selected-part material). It does not change between themes, because white on it passes in both.
- **Workshop Red, pressed** (`accent-fill-hover`): the hover state of the primary button only.
- **Signal Red, text and line** (`accent` in dark, `accent-light` in light): red that sits directly on the ground. Stage corner brackets, the border of instrument buttons and the tooltip, the pressed instrument button, the high-side value in a readout, the high-side gauge needle, the grid on the stage floor, square nodes, step numerals in the inspector, savings notes, link hover and the focus outline. It is lighter in the dark theme because the fill red is too dim to read on near black.

### Neutral
- **Bay Black / Manual Paper** (`ground`): the page background. One ground for the whole page. At 72% it backs instrument buttons and at 90% the tooltip, so the model shows through.
- **Bench Tint** (`ground-alt`): the tinted block and the pressed state of chips, instrument buttons and the pause control. It is not used to alternate sections.
- **Panel** (`surface`): the lead form container and its fields.
- **Hairline** (`line`): section rules, row dividers, chip and field borders, and the stage grid at 45% to 55%.
- **Ink** (`body`): reading text, headings, the stronger rule that opens a list, strong readout lines and readout values, the gauge needle and its normal zone.
- **Graphite** (`muted`): secondary text, readouts at rest, picker row names, large inactive numerals, the see-through body shell and its edge lines, the gauge arc and ticks, the dashed road.
- **Faint** (`faint`): decoration only. It is never used for text and this world does not currently use it.

Inside the 3D scene the parts are lit materials, not page tokens: steel, dark cast metal, aluminium, rubber, brass, glass, and a neutral metallic body paint. They are part of the model. Do not lift them into page UI.

### Named Rules
**The Red Means Something Rule.** Red is the primary action, the selected or suspected part, or heat and high pressure. Nothing else. On the AC page only the high-pressure side is red: its pipes, its beads of refrigerant, its gauge needle and its value in the readout. The low-pressure side and a normal reading are ink (pale at night, graphite on paper). When the compressor is off the high-side pipe stops being red, because it is no longer hot.

**The Two Reds Rule.** Red as a fill behind white text is always `accent-fill`. Red as text or a line on the ground is always `accent`. Swapping them breaks contrast in one theme.

**The Ink Opens, Hairline Divides Rule.** A list, table or row of steps opens with a 1px rule in `body`; the rows inside it are divided by 1px rules in `line`. Sections open with a `line` rule.

**The Night-Only Light Rule.** Anything that reads as emitted light is declared under the dark theme and is absent on paper: stage scanlines and red wash, the glow on the status dot, the inner glow of a pressed instrument button, the rim light and underglow in the scene.

## Typography

**Display Font:** Barlow Condensed, weights 600 and 700 (with ui-sans-serif, system-ui, sans-serif)
**Body Font:** Inter Variable (with ui-sans-serif, system-ui, sans-serif)
**Label Font:** Barlow Condensed 600, uppercase
**Readout Font:** the system monospace stack (ui-monospace, SF Mono, Menlo, Consolas), uppercase. No webfont is loaded for it, so its letterforms differ by platform.

**Character:** Condensed uppercase capitals read like stamped panel lettering and let long Indonesian headlines stay large on a phone. Inter does all the reading in sentence case. Monospace appears only inside the stage, where the display "reads out" a state, so it signals instrument text and never competes with the headings.

### Hierarchy
- **Display** (700, clamp(2.875rem, 8.2vw, 6rem), line-height 0.92, uppercase): the hero headline on the homepage and catalogue, held to about 16 characters per line, and the closing statement on the AC page.
- **Headline** (700, clamp(2.125rem, 5vw, 3.75rem), line-height 0.98, uppercase): one per section. Also the hero headline of the AC page, where the stage shares the first screen (floor raised to 2.5rem), course names in the catalogue (reduced to clamp(1.75rem, 3vw, 2.5rem)), and a single pull statement beside a photograph.
- **Title** (600, clamp(1.375rem, 2.4vw, 1.75rem), line-height 1.1, uppercase): item headings, the selected part's name, the question above the case chips, the gauge reading, course and trainer names.
- **Numeral** (700, tabular figures, line-height 0.95): prices, proof figures and step numbers. Size is set at the point of use, from 1.125rem for inspector steps up to 4.5rem for method steps and the main price.
- **Lead** (400, 1.0625rem rising to 1.1875rem from 768px, line-height 1.7, muted): the paragraph under a headline.
- **Body** (400, 1rem, line-height 1.65; most paragraphs use 1.625 in muted): reading text. Maximum line length is 68ch.
- **Label** (600, 1rem, letter-spacing 0.03em, uppercase): chips, picker row names, the pause control, terms in fact tables and the example-cars row, small sub-headings inside a panel, the summary of the component list.
- **Readout** (0.75rem, line-height 1.4, letter-spacing 0.14em, uppercase, muted; 0.6875rem and 0.1em below 640px): stage title and status lines, hint, tooltip, slider caption and end labels, instrument buttons. Fields inside a line are joined with a double slash. The explode value is zero-padded to three digits.
- **Button text** (600, 1.125rem, letter-spacing 0.03em, uppercase): Barlow Condensed on every `.btn` in this world.

### Named Rules
**The Capitals Are Condensed Rule.** Outside the stage, uppercase belongs to Barlow Condensed. Inter is never set in uppercase or tracked out.

**The Readout Stays On Stage Rule.** Monospace is used only for text that sits on the 3D stage. It is never used for page headings, body text, prices or chips.

**The Two Weights Rule.** Only Barlow Condensed 600 and 700 are loaded. Do not ask for other weights.

## Layout

The page shell is 80rem wide at most, with side padding of 1.25rem on a phone, 2rem from 640px and 2.5rem from 1024px. Inside it, sections use a 12 column grid from 1024px with a 2.5rem to 4rem gap. The usual split is a headline column of 4, 5 or 7 with the content in the remainder; the headline column sticks while a long list scrolls beside it.

Each section opens with a hairline and has 5rem of vertical padding, 8.5rem from 1024px. The proof strip uses the tight variant, 3rem and 4rem. Sections do not change background.

**The stage** is one fixed box that holds the poster and the canvas, so nothing shifts when the model takes over. Its height is clamp(15rem, 70vw, 25rem), and clamp(24rem, 62vh, 38rem) from 1024px. Readouts and controls are pinned to its corners at 0.875rem (1.25rem from 1024px): title and status top left, mode switch top right, hint or slider bottom left, action bottom right, zoom on the right edge.

Below 640px the stage grows to clamp(26rem, 122vw, 31rem) and the model is inset from the top and bottom, so readouts get a row above it and controls a row below it and a thumb on a button never covers the machine. The zoom buttons are hidden on phones. A vertical swipe on the canvas scrolls the page; a sideways drag turns the model. Wheel zoom needs Ctrl or a pinch, so ordinary scrolling passes through.

**Homepage hero.** Headline in 7 columns, lead and both actions in 5, aligned to the bottom of the headline. Below, full width: the picker rows, the example-cars row under an ink rule, the stage, the component chips, then the inspector under an ink rule in a 4, 5, 3 split (diagram, how it works, what goes wrong and which program teaches it).

**AC page hero.** A 5 and 7 split: headline, price and actions on the left, the stage on the right, vertically centred. Under both, full width: the case chips, then a panel under an ink rule with the gauges in 4 columns and the case explanation in 8. The page is "bare": no site header or footer, only a brand line at the top.

On a phone the order is headline, lead, actions, controls, stage, chips, panel. Case chips on the AC page sit in one row that scrolls sideways and bleeds to the screen edges, so the machine and the gauges stay on screen while a case is chosen; the chosen chip is scrolled into view. Other chip groups wrap. Step grids collapse to one column with the numeral beside the text.

Breakpoints in use are 640px, 768px and 1024px. Touch targets are at least 2.75rem; buttons are 3rem.

Photographs are cropped to fixed ratios (4:3, 3:4, 16:9, 4:5) and laid out with a deliberate offset: one image in a group drops by 3rem to 6rem so the group does not read as a uniform grid.

## Elevation & Depth

Two regimes, kept apart.

**On the page: flat.** The world's own surfaces have no box shadows and no raised cards. Depth comes from hairline rules and the one-step tint of the tinted block.

**On the stage: real light.** The 3D scene has a white key light that casts soft shadows from the model onto a shadow-catching floor (2048px map), image-based ambient light, filmic tone mapping, and a grid drawn on the floor that fades out toward its edge. In the dark theme the floor grid is stronger (42% against 22%), the cast shadow is deeper (60% against 28%), a red rim light edges the metal from behind, and a red point light sits under the car. In the light theme the rim light and underglow are off. The selected part swaps to a red emissive material in both themes; it is how selection is shown in 3D.

The stage background is CSS, behind the transparent canvas: a 3rem grid in hairline at about half strength, and in the dark theme a layer of 1px scanlines every 3px at under 2% white plus a radial red wash centred low (Workshop Red at 20%).

### Shadow Vocabulary
- **Status dot glow** (`box-shadow: 0 0 8px 1px rgb(255 60 60 / 0.8)`): the dot before the stage title, dark theme only.
- **Pressed instrument glow** (`box-shadow: inset 0 0 12px rgb(204 0 0 / 0.35)`): an instrument button in its on state, dark theme only.
- **Poster part glow** (`filter: drop-shadow(0 0 6px rgb(255 60 60 / 0.75))`): the selected part on the 2D loading poster, dark theme only.

### Named Rules
**The Flat Page, Lit Stage Rule.** Cast shadows, glow and coloured light belong to the stage and the controls that sit on it. A section, list, panel or block on the page gets an ink rule, a hairline, a tint step or red, never a shadow.

## Shapes

Corners are square. Buttons, chips, instrument buttons, the tooltip, form fields, choice pills, the tinted block and photographs have a radius of 0 in this world, overriding the 2px and 3px radii the shared atoms carry on the sales pages. Two round marks exist and are meant: the status dot on the stage and the radio mark inside a choice pill.

Borders are 1px. The exceptions are the 2px button border, the 2px red top edge on the tinted block, and the 2px corner marks on photographs.

**Corner brackets.** The stage is framed by four 1.25rem L-shaped brackets, 1px, in Signal Red, one in each corner. They frame the stage only.

**The square node.** A small filled square is the recurring mark: a 0.5rem red node with a 0.75rem graphite leader in front of list items (text indented 1.75rem), a 0.5rem red square in the capabilities list, a 0.625rem ink square at each phase on the road, and the square hub of the gauge needle. Use a square wherever a bullet would otherwise go.

**Line drawings.** Mechanism diagrams, the gauges and the loading poster are line art with strokes that keep their width at any size: 1.5px in the current ink, with red reserved for the working element. The gauge is a 1.5px graphite arc with ticks, a 5px ink band for the normal range, a 2.5px square-capped needle and a square hub.

**Icons** are inline SVG strokes with square caps (the plus that turns into a cross on disclosure rows, the zoom controls on the AC stage, play and pause).

Lead photographs carry two red corner marks, top left and bottom right, 0.875rem long, like a print clipped to the wall. They are also used on course covers in the catalogue.

## Components

### Buttons
Blunt and legible, like a stamped label.
- **Shape:** square, at least 3rem tall, padding 0.875rem by 1.75rem, 2px border.
- **Primary:** Workshop Red fill with white Barlow Condensed uppercase text. Hover darkens the fill. One primary action per group. It is also the one page-style button allowed on the stage: the bottom-right action that sends the selected component to the bench.
- **Ghost:** transparent with a graphite border and ink text. Hover changes the border to ink and keeps the background transparent.
- **Pressed:** every button moves down 1px.
- **Focus:** a 3px outline in Signal Red, offset 3px, on all focusable elements, including the canvas.
- **Transitions:** 200ms ease on colour, border and background.

### Chips
One chip serves every choice on the page: picker options, components, pieces on the bench, AC cases, suspects.
- **Style:** square, 2.75rem tall, 1px hairline border on the ground colour, label type, no wrapping, 0.5rem gap.
- **State:** hover turns the border to ink; pressing tints the background; the selected chip is filled Workshop Red with white text and reports `aria-pressed`.
- **Rows:** wrap by default. The AC case row scrolls sideways below 640px.

### Picker rows and example cars
Three groups in one wrapping line above the homepage stage: fuel, system, gearbox. Each is a graphite label followed by its chips, with 2.5rem between groups. Choosing a fuel swaps which system chips are shown. Directly under it, beneath an ink rule, a two-column fact row names a modern and a classic car that use the chosen combination: graphite label above, sentence-case text below, capped at 60ch.

### The 3D stage (signature)
A transparent WebGL canvas over the stage background, loaded after first paint. Until it is ready, and wherever WebGL fails, a line-drawn poster fills the same box.
- **Car view (homepage).** The car arrives painted, then after about 1.3 seconds turns to a see-through shell so the systems inside show. A top-right instrument button switches between painted body and see-through; its label names what pressing it will do. Selecting a component drops the paint, turns that component red, and swaps the inspector. Hovering shows the component's name in the tooltip. The body is rebuilt for each fuel, system and gearbox combination.
- **Bench view (homepage).** The bottom-right primary action, or a double click on a component, replaces the car with that component alone, pulled apart. A slider reassembles or explodes it, component chips are replaced by chips for its pieces, clicking a piece lifts it out and explains it, and a run button sets the mechanism moving. A top-right instrument button returns to the car.
- **AC loop (AC page).** The whole system from compressor back to compressor, always on the bench, assembled by default. Beads of refrigerant travel along the pipes, the pulley, condenser fan and blower turn. Choosing one of seven cases (one healthy, six faults) changes the machine: flow slows, thins or stops, the clutch or fan halts, the suspects turn red, and the readout and gauges follow.
- **Controls.** Drag to turn, click to select, arrow keys to turn, plus and minus to zoom. Every component is also reachable through the chips, and the canvas carries a text description.

### Stage readouts
Monospace lines pinned top left: a strong line in ink that opens with the red status dot and names the current state with double-slash separators, then graphite lines beneath. On the AC stage the second line gives the low-side and high-side readings, values in ink with only the high-side value in red, and a third line is a legend for what red currently means. A hint sits bottom left in the same type.

### Instrument buttons
Controls that belong to the display.
- **Style:** square, at least 2.75rem in both directions, 1px Signal Red border at 55%, ground at 72% behind, ink monospace uppercase text.
- **States:** hover brings the border to full red; pressing tints the background; the on state has a red border and red text, plus the inner glow at night.
- **Use:** body toggle, back to car, zoom in and out, run and stop. Icon-only buttons carry an accessible name.

### Explode slider
A caption in readout type with a three-digit percentage, a native range input tinted red (9rem to 15rem wide, full width on phones), and a row of two end labels beneath it, "assembled" on the left and "exploded" on the right. The percentage tracks the model, not only the handle.

### Tooltip
The name of whatever is under the pointer on the stage: readout type in ink, 1px red border, ground at 90%, offset 0.875rem down and right of the pointer. Mouse only.

### Manifold gauges
Two line-drawn half-dial gauges side by side, low side and high side. The thick ink band at the top is the normal range. The needle moves over 700ms with an ease-out curve when the case changes. The high-side needle is red; the low-side needle is ink. Under each dial a graphite label names the side and the reading is set in title type. The gauges show a qualitative pattern, never numbers, and say so in a note.

### Inspector and case panel
A panel opens with an ink rule. On the homepage it holds the part name, a pause control, a line diagram, numbered steps with red numerals, the failure symptom, and the program that teaches it with a primary action. On the AC page it holds the gauges, the case explanation, an open question marked with the square node, suspect chips that highlight the piece in the model, the video that covers it, and a primary action.

Mechanism diagrams are 320 by 190 unit line drawings with literal motion: dashes travel along a pipe at a constant rate, a piston rises and falls on a 4 second four-stroke loop, brake pads close and release, a wheel rides a bump. Loops run linearly or ease in and out and never bounce. The pause control stops every diagram at once and relabels itself.

### Cards / Containers
There are no cards in this world. Content sits on the ground, grouped by rules.
- **Tinted block:** the one tinted container, used once per page for the offer that closes the argument. Bench Tint background, 2px Workshop Red top edge, square corners, padding 1.5rem rising to 2.25rem.
- **Paired columns:** two columns under one ink rule, divided by a single hairline. No box around either.
- **Fact tables and lists:** an ink rule on top, hairline between rows.
- **Disclosure rows:** questions and the component list open under a hairline, with a square-capped plus that rotates 45 degrees when open (200ms).

### Inputs / Fields
The lead form is shared with the sales pages; this world squares its fields and choice pills.
- **Style:** Panel background, 1px hairline border, at least 3rem tall, 1rem text, bold 0.875rem label above.
- **Focus:** border changes to Workshop Red, plus the global focus outline.
- **Choice pills:** a full-width tap target with a round radio mark; selected state is a red border, tinted background and filled mark.

### Navigation
The header is shared with the sales pages: a fixed bar on the ground colour with a hairline under it, a Barlow Condensed wordmark, Inter 0.875rem semibold links, the theme toggle and a primary button. Below 1024px the links move into a menu panel.

**Bare-page brand line.** A page with no header opens with one line under a hairline: the wordmark in Barlow Condensed 700 uppercase at 1.25rem with its second word in Signal Red on the left, and the page type in graphite label type on the right.

### The road
The six learning phases sit on a vertical dashed line in graphite with an ink square at each phase. Where the browser supports scroll-driven animation and the visitor has not asked for reduced motion, a solid red line fills the road as the section scrolls through the viewport.

### Motion
- **State changes** on buttons, chips and controls: 200ms ease.
- **Scene easing is time-based.** Camera, zoom and the explode amount approach their target by a fraction that depends on elapsed time, so a slow device takes the same time and not longer. The switch between painted body and see-through is slower than the camera.
- **Rendering is on demand.** The scene draws only while something is moving or running.
- **Looping motion has a switch.** The bench and the AC loop have a run and stop button whose label names what pressing it will do; diagrams have the pause control.
- **Reduced motion.** With `prefers-reduced-motion`, the machine starts stopped, scene values jump to their target, the car starts see-through with no arrival sequence, and the global rule cuts CSS animation and transition durations. The run button still works when pressed.

## Do's and Don'ts

### Do:
- **Do** scope this world with `world="garage"` on the base layout and import `src/styles/garage.css` in the page.
- **Do** use the shared tokens (`ground`, `line`, `body`, `muted`, `accent`, `accent-fill`) and let the `dark` class flip them. Check every new surface in both themes.
- **Do** give a page at most one stage, and give it a line-drawn poster in the same box for loading and for devices without WebGL.
- **Do** keep red to its three meanings: primary action, selected or suspected part, heat or high pressure. Draw cold and normal in ink.
- **Do** set stage text in the readout style and stage controls as instrument buttons, pinned to the stage corners.
- **Do** declare glow, scanlines and coloured light under the dark theme only.
- **Do** make every 3D selection reachable from a chip, and give the canvas keyboard turning and a text description.
- **Do** give looping motion a run or pause control whose label names the action, and start it stopped under reduced motion.
- **Do** mark list items with the square node and leader, open lists with an ink rule, and divide rows with hairlines.
- **Do** set headings, labels, numerals and button text in uppercase Barlow Condensed at 600 or 700, and reading text in Inter sentence case.
- **Do** keep every interactive element at least 2.75rem tall, with the 3px red focus outline.

### Don't:
- **Don't** put box shadows, blur or glow on page sections, lists, panels or blocks. Light belongs to the stage.
- **Don't** use monospace outside the stage.
- **Don't** use red for decoration, and don't colour the low-pressure side, a normal reading or an unselected part red.
- **Don't** let a control cover the model on a phone; readouts go above it and controls below it.
- **Don't** capture ordinary page scrolling on the canvas.
- **Don't** round corners on anything this world styles, apart from the status dot and the radio mark.
- **Don't** alternate section backgrounds or wrap content in bordered cards. Use a hairline and space.
- **Don't** use `faint` for text.
- **Don't** set Inter in tracked uppercase.
- **Don't** write theme variants in components; a colour is named once through its token.
- **Don't** animate for decoration. Motion here shows how a mechanism works or shows progress along the road.
- **Don't** apply these rules to the sales pages outside this world, and don't edit the shared atoms to match; the overrides live under the `garage` scope.
