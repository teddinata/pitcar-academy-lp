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

## Colour

Two grounds, one accent. Sections alternate so a 15-section page keeps a
rhythm without spending more colour.

| Token | Value | Use |
| --- | --- | --- |
| `ink` | `#0A0A0A` | Dark section ground |
| `ink-soft` | `#141414` | Raised card on dark |
| `ink-line` | `#262626` | Hairline on dark |
| `paper` | `#FAFAF9` | Light section ground |
| `paper-soft` | `#F5F5F4` | Raised card on light |
| `paper-line` | `#E7E5E4` | Hairline on light |
| `brand-600` | `#CC0000` | CTA fill; accent text on paper |
| `brand-700` | `#AA0000` | CTA hover |
| `brand-400` | `#FF6464` | Accent text on ink |

**The rule that matters:** `#CC0000` is only **3.36:1** on `#0A0A0A`, which
fails AA for normal text. Accent text on a dark section must use `brand-400`
(6.9:1). `#CC0000` stays valid as a CTA fill — white on it is 5.89:1 — and as
accent text on paper (5.64:1). Helper classes `.text-accent-ink` and
`.text-accent-paper` encode this so it is not decided per component.

Verified pairs: ink on paper 18.96 · muted on paper 7.30 · muted on ink 7.85 ·
white on brand-600 5.89.

Red is reserved for **actions and emphasis only**. The announcement bar is dark
with a small red marker rather than a red band, because a red bar above a red
button spends the accent twice and weakens the button.

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
`.band-ink` / `.band-paper` — the two grounds.

Section order alternates: hero and trust (ink), problem/method/capabilities
(paper), workshop (ink), trainers/journey/programs (paper), founding batch and
career (ink), parents/facts/FAQ (paper), consultation (ink).

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
100/100/100/100, FAQ 99/100/100/100. CLS 0. No horizontal overflow and no
sub-44px targets at 320/375/414/768/1024/1280/1440/1920px.

Production numbers will differ — network and hosting are not in this measurement.
