---
name: devlab-design-language
description: DevLab's visual and interaction direction — dark-first, terminal-inspired, playful, fast — plus its accessibility and responsive requirements. Use when designing or building any UI, choosing colour, type, motion or layout, writing interface copy, or reviewing a screen. Triggers on "design", "UI", "layout", "styling", "theme", "landing page", "component look", "animation", "copy", "accessibility", "responsive".
---

# DevLab Design Language

The interface has one job: make a bored developer curious enough to click.

## What it must feel like (§46)

Technical · playful · modern · slightly experimental · fast · visually interesting.

It must **not** look like a corporate LMS, a generic admin dashboard, or a school platform. The
whole surface should say: *you can mess around here, and nothing you break matters.*

## Visual language

- **Dark-first.** Design the dark theme first and properly; a light theme is a port, not the
  baseline.
- **Terminal-inspired, not terminal-cosplay.** Monospace for code, identifiers, metrics and
  results; a sharp sans for prose. Borrow the register — prompts, cursors, exit codes, log lines —
  without making the whole app a fake TTY.
- **Cards and panels** for the experience catalogue. Each experience should look like a different
  toy, sharing a chassis.
- **Strong typography over decoration.** Type hierarchy carries the design; do not lean on
  gradients and glow to make it interesting.
- **One accent colour that means "go"**, plus a small semantic set: success, failure, warning,
  info, XP/reward. Colour is never the only carrier of state — pair it with an icon or label.
- **Interactive diagrams and code blocks are first-class content**, not decorations.
- **Motion is feedback.** Reveal a result, celebrate an unlock, show a state change. Motion that
  only exists to look expensive gets cut. All of it respects `prefers-reduced-motion`.

## Copy

Short, dry, developer-native. Humour is welcome and must never punch down or shame a wrong
answer — a failed attempt is data, not a verdict. Levels and titles (Junior, Senior, Staff,
Principal) are explicitly gamification; the UI should say so rather than imply a qualification
(§9.10).

Error states explain what happened and what to do. "Something went wrong" is not copy.

## Accessibility is a requirement (§44)

- Every interaction reachable by keyboard, with a visible focus ring. Terminal and canvas-style
  experiences need a documented keyboard path — not a mouse-only puzzle.
- Semantic HTML first; ARIA only where semantics run out.
- Contrast at least 4.5:1 for text, 3:1 for meaningful non-text. **Check the dark theme
  specifically** — dim grey on near-black is the standard failure.
- Forms have labels; errors are associated with their field and announced.
- Live regions announce results, score changes and achievement unlocks.
- `prefers-reduced-motion` disables non-essential animation, and the experience must remain fully
  understandable without it.
- Timed challenges offer a way to see the content without the clock, where the clock is not the
  point.

## Responsive (§45)

Targets: desktop, laptop, tablet, mobile web. Mobile-first CSS, verified from 320px up.

Complex experiences (System Design Lab, terminal simulations, Git graph) may be
desktop-optimised — but they must **degrade gracefully**, not break. Acceptable: a simplified
mobile view, or an honest "this one is better on a larger screen" with a working read-only mode.
Not acceptable: horizontal scroll, clipped controls, or a blank canvas.

Touch targets at least 44×44px. No hover-only affordances.

## Performance is design (§47)

The landing page and the "I'm Bored" transition are the product's first impression. Fast initial
load, efficient Inertia navigation, lazy-loaded experience modules, no layout shift when a result
arrives. Measure before optimising anything else.

## Before shipping a screen

- [ ] Keyboard-only pass completed
- [ ] Dark and light both checked for contrast
- [ ] 320px, 768px, 1280px, 1920px checked — no horizontal scroll
- [ ] Reduced-motion pass
- [ ] Loading, empty, error and success states all designed
- [ ] Copy reads as DevLab, not as enterprise software
