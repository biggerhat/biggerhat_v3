# Design system notes

Conventions behind the site's look and navigation. Prefer these over one-off styling; extend them rather than
adding a parallel pattern. (Locked-down user-facing strings live in [terminology.md](terminology.md).)

## Surfaces and tokens

- Colours are CSS variables in `resources/css/app.css` (`--background`, `--card`, `--muted`, …). The **dark** theme
  lifts `--card`/`--popover` above `--background` so cards read as layers, not just outlines. Dark is the default.
- Faction hues (`--arcanists`, `--bayou`, …) are theme-independent. Never put white text straight on a faction
  colour (Outcasts, Abyssinia, Explorer's Society, Resurrectionists fail AA); either use `factionForeground()` /
  `factionForegroundColor()` or — better — don't fill with the colour at all (below).
- Dim things with a colour (`text-muted-foreground`), never with `opacity-*` on text: it drops contrast under AA.

## Faction identity: spine, not fill

`faction-spine` (block) / `faction-spine-row` (`<tr>`) + `faction-<slug>` give a neutral card a 4px faction-coloured
spine and a faint tint. Build the class with `factionSpine(faction)` (`composables/useFactionColor.ts`) and pair it
with `border bg-card`. Text stays on the normal foreground, so no contrast flips, and the colour is always backed by
a logo or label (never colour alone). Used by the crew list, tournament standings and crew references.

The **game tracker** keeps white text on its crew rows, so it uses `faction-deep` (`factionDeep(faction)`): a dark
faction-hued wash (faction colour mixed 55/45 with near-black) plus the same 4px spine. White text clears AA on every
faction (lowest is Outcasts at ~5.8:1), so no per-faction text flip is needed. Don't use `factionBackground()` on rows
with white text — it's a saturated fill and the light factions need the dark-text flip.

## Cards

`feature-card` (+ `group`) is the one style for landing/hub call-to-action cards: neutral surface, single 1px border,
colour only on the icon chip (or an optional `border-l-4 border-l-<colour>` spine). No per-card gradients, 2px
borders or hover lift.

## Navigation

- **Sidebar** is grouped by task (Play, Community, Tools, Factions, References), built from `lib/navData.ts`, which
  also feeds the Cmd-K palette — add a link there once.
- **Breadcrumbs** are derived, not passed: `lib/breadcrumbs.ts` maps an Inertia component + its props to a trail
  (`Home › Bayou › Bayou Gremlin`). Add a builder when you add a detail page. `PageBreadcrumbs` renders the trail
  (parent-only "‹ Back" on phones), emits `BreadcrumbList` JSON-LD, and replaces per-page "Back to …" links
  (keep a back link only where the destination differs, e.g. "Back to Editor" in previews).
- **Phone tab bar** (`MobileTabBar.vue`): Browse · Build · Play · Search · Menu (TOS swaps Play for Units). It is
  hidden on `Games/Show` and the crew editor (`HIDDEN_ON`). Fixed bottom bars on other pages must sit above it:
  use `bottom-[var(--tabbar-h,0px)]` (the variable is only set while the tab bar is mounted, phone widths).
- The Cmd-K palette's open state is shared (`composables/useCommandPalette.ts`) so any control can open it.

## Accessibility defaults

- **Focus**: a global `:focus-visible` 2px outline is the fallback; components that draw their own ring set
  `focus-visible:outline-none` + a ring (shadcn pattern) and keep it.
- **Touch targets**: `Button`, `SelectTrigger` and `TabsTrigger` grow to ~40–44px on `(pointer: coarse)` via
  `[@media(pointer:coarse)]:min-h-*`. Do the same for new tap targets; don't add a desktop-wide size bump.
- **Motion**: `prefers-reduced-motion` shortens all animations/transitions to ~0 (global rule). Entry animations
  must end in their visible state (`animation-fill-mode: forwards`) so this doesn't hide content.
- **Text size**: Settings → Appearance sets `data-text-size="large|xlarge"` on `<html>` (cookie `text_size` for first
  paint, localStorage for the client). It scales the root font size, so size things in `rem`/Tailwind scale; fixed
  `text-[10px]` literals won't follow.
- **Headings**: one `h1` per page; card titles under it are `h2` (`CardTitle` defaults to `h2`, pass `as="h1"` only
  for the page's main title). `HeadingEyebrow` takes `as`.
- Landmark/skip link: `AppShell` renders "Skip to main content" targeting `#main-content`.

## Type scale (use these, don't invent sizes)

| Role | Classes |
|---|---|
| Page title | `PageBanner` (`text-lg font-bold tracking-tight sm:text-xl md:text-2xl`) |
| Section title | `text-lg font-semibold` |
| Card title | `text-base font-semibold leading-tight` |
| Body | `text-sm` (`text-base` for long-form reading) |
| Secondary / meta | `text-xs text-muted-foreground` |
| Eyebrow / label | `HeadingEyebrow` (`text-xs uppercase tracking-wider`) |
| Dense data (tables, tracker) | `text-2xs` (11px) is the floor for UI text. Printed/physical-card renders (Bonanza cards, print sheets) are exempt |
