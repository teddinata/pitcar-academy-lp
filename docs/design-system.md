# Design system — Pitcar Academy

Positioning: a serious place to start an automotive career. Not a bootcamp, not
a workshop's website. Everything below follows from that and from the price:
at Rp5 juta+ the page has to look like an institution, and every claim on it
has to be one the business can defend.

## Base style: Swiss modernism

Strict grid, high contrast, one accent, no decoration. Chosen because it does
three things this page needs at once: it reads as institutional rather than
promotional, it survives a very long page without becoming tiring, and it is
cheap to render (WCAG AAA baseline, no shadows or blurs to paint).

Rejected: gradients, glows, glassmorphism, and the display-serif direction the
tooling first suggested — a high-fashion serif on a mechanic training page
reads as costume, not credibility.

## Colour and theme

One uniform ground per theme, switched by a toggle. **Light is the default**;
`.dark` on `<html>` flips every token. There are no `dark:` utilities anywhere
in the components — a colour is defined once, so it cannot end up flipped in
one place and not another. That failure mode is not hypothetical: the pre-
revamp code had `darkMode: 'media'` firing `dark:` utilities while the
background stayed white, giving every dark-mode phone user 1.48:1 body text.

| Token | Light | Dark | Use |
| --- | --- | --- | --- |
| `ground` | `#FAFAF9` | `#0A0A0A` | Page and section background |
| `ground-alt` | `#F3F3F1` | `#121212` | Alternate section, 2% step |
| `surface` | `#FFFFFF` | `#161616` | Cards, form |
| `line` | `#E7E5E4` | `#2A2A2A` | Hairlines |
| `body` | `#0A0A0A` | `#FAFAF9` | Primary text — 18.96:1 both ways |
| `muted` | `#57534E` | `#A8A29E` | Secondary text — 7.30 / 7.85 |
| `faint` | `#A8A29E` | `#57534E` | **Decoration only, never text** |
| `accent` | `#CC0000` | `#FF6464` | Accent text — 5.64 / 6.84 |
| `accent-fill` | `#CC0000` | `#CC0000` | CTA background, white on it 5.89 |

**The rule that matters:** `#CC0000` is only **3.36:1** on near-black, so the
accent *text* token flips to `#FF6464` in dark. The accent *fill* does not
flip, because white on `#CC0000` passes in both themes. Two tokens, because
they solve two different problems.

`faint` exists for decorative marks only. A migration that mapped the footer
legal line onto it dropped that text to 2.41:1 — caught by the contrast sweep,
not by eye.

Red is reserved for **actions and emphasis only**. The announcement bar carries
a small red marker rather than a red band, because a red bar above a red button
spends the accent twice and weakens the button.

### Separating sections without dark bands

With one ground, alternating dark/light bands are gone, so sections separate by
a 2% `ground-alt` tint plus a hairline. Strict alternation, every boundary
ruled. Emphasis that previously relied on inverting a surface — the flagship
package card, the consultation form — now uses an accent edge (`.card-featured`)
instead, because an inverted card disappears on a uniform ground.

## Typography

| Role | Face | Why |
| --- | --- | --- |
| Display | Barlow Condensed 600/700 | Condensed industrial grotesk — workshop signage, not startup |
| Body | Inter Variable | Neutral grotesk, already self-hosted, pairs cleanly |

Only two Barlow weights are loaded (latin subset, 2 woff2, ~44KB total). All
nine `@font-face` rules use `font-display: swap`, so no invisible text.

Scale is fluid via `clamp()`: `.display-1` (hero), `.display-2` (section),
`.display-3` (card), `.lead` (intro copy). Body copy is capped at `68ch`.

## Layout

`.shell` — max 80rem, responsive gutters.
`.band` — vertical rhythm, identical everywhere so sections read as one system.
`.band-alt` — the tinted alternate ground.

The theme is applied by an inline script in `<head>` before first paint, so a
dark-mode visitor never sees a white flash. The choice is stored in
`localStorage` under `pitcar-theme`; with nothing stored the page is light,
regardless of the OS setting.

## Rules held throughout

- SVG icons only — no emoji.
- Hairlines and grounds instead of shadows; radius stays at 2–3px.
- Every interactive element clears 44px and shows a visible focus ring.
- `prefers-reduced-motion` disables animation and smooth scrolling.
- Transitions 200ms, on colour only — nothing that shifts layout on hover.
- Images carry `width`/`height`; only the hero is eager.
- The hero image is preloaded **only at ≥1024px**, because below that the H1 is
  the LCP element and a high-priority image fetch competes with the font it
  needs.
- Stylesheets are inlined (`inlineStylesheets: 'always'`): one 20KB sheet on a
  two-page site is worth more as a saved round trip than as a cached file.

## Form

Six fields on one page: three typed (nama, WhatsApp, domisili), three tapped
(program, tujuan, kesiapan). Single submit — with only three things to type,
splitting it into steps adds a click without removing any work.

The three choice questions use radio pills, not `<select>`. A native select on
mobile opens a picker and costs two extra interactions per question; a pill is
one tap. The real radio stays in the DOM, visually hidden, so keyboard
navigation and screen-reader announcement are unchanged.

The readiness question is worded around readiness, not budget. "Kesiapan
mengikuti program" gets the same signal as asking whether someone can afford
Rp5 juta, without asking a school leaver about their finances on a landing
page.

## Honesty constraints

These are design rules here because breaking them creates business risk:

- **No invented people.** Trainer cards render a typographic placeholder until a
  real photograph exists. A stock portrait beside a named instructor is a lie
  about who teaches the program.
- **No unverifiable badges.** "Program Paling Lengkap" is objectively true;
  "Paling Diminati" on a first batch is not.
- **Price comes last** in every card: value, then outcome, then facts, then
  investment. Leading with a discount makes the program look like it is
  struggling to sell.
- **Certificate wording** says "Sertifikat Kelulusan", never "Sertifikat
  Kompetensi", because there is no BNSP/LSP accreditation behind it.
- **Career wording** describes an opportunity to enter a selection process,
  never employment.
- Content flagged `!! VERIFY !!` in `src/content.config.ts` — trust numbers,
  mess availability, schedule details — must be confirmed before publishing,
  and can be hidden by setting `publish: false` without touching a component.

## Measured

Local production build, Lighthouse: mobile 99/100/100/100, desktop
100/100/100/100. CLS 0. No horizontal overflow and no sub-44px targets at
320/375/414/768/1024/1280/1440/1920px.

Every text node on both pages was swept for contrast **in both themes** — a
Lighthouse run only measures the theme it happens to load, so the dark palette
needs its own check. `scripts` for that sweep live with the E2E harness.

Production numbers will differ — network and hosting are not in this measurement.
