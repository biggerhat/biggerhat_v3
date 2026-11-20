# Tabletop: release checklist

The tabletop (the sandbox at `/tools/tabletop`, a game's board at `/games/{uuid}/board`, the Fate deck, saved tables and their help) is pre-release. This
is what turning it on for everyone involves, what has been checked, and what has not. Written 2026-11; keep it current as items are done.

## How it is gated today

One gate, `App\Support\Tabletop\TabletopAccess::allows($user)`: the Pennant flag `experiment-tabletop` is on, **or** the user holds the
`use_tabletop_beta` permission (super admins hold every permission). Where it is applied (all answer **404**, not 403, so the feature stays hidden):

- Controllers: `TabletopController`, `TabletopTablesController`, `TabletopCharactersController`, `TabletopLayoutController` (and its form requests),
  `Game\GameBoardController`, `Game\GameFateController`, and the Fate/board events.
- Help: `HelpTopics::allows('tabletop')` (the `/help/tabletop` page; the index hides the topic).
- Links: the sidebar entry (`navData.ts`, under **Play**, needs `auth.can_use_tabletop`), `Games/Show.vue` (`tabletop_enabled`), the tournament match card
  (`MyMatchCard.vue`) and the tournament payload's `layouts`.

## Turning it on for everyone

1. Switch the flag on (Admin -> feature flags -> "Tabletop (preview)"). Nothing in the code has to change: everything above follows the one gate.
2. To go further and remove the gate entirely (later, once it is simply a feature): delete `TabletopAccess` and its calls, the `can_use_tabletop` share
   (`HandleInertiaRequests`, `types/index.d.ts`) and the `canUseTabletop` checks in the nav and `MyMatchCard`, the "Beta" badge in `navData.ts`, the flag in
   `FeatureFlagsServiceProvider`, the `use_tabletop_beta` permission, and the flag-on lines in the tests (`Feature::for(null)->activate('experiment-tabletop')`)
   and the browser seed. Keep the help topic's `allows` returning true.
3. A softer first step: give `use_tabletop_beta` to a handful of regular players (it is per user) and have them play real games on it before the flag goes on.

## What has been checked

- Server rules and payloads: Pest (the whole suite passes), including every board, Fate, turn-sequence, layout and access test.
- Browser flows (headless Chrome, desktop and phone width, light and dark): `flows35` (sandbox), `flows36` (a game's board), `flows37` (accessibility audit with axe: no serious
  findings), `flows38` (makes the help pictures). See `docs/ui-audit.md`.
- Keyboard: the table, terrain and Fate deck can be used without a pointer.
- Help: `/help/tabletop`, the board's Help button, the `?` key, 4 pictures.

## What has not been checked (needs people and devices)

These cannot be verified from a headless browser and are the real release criteria. Suggested: a small group plays 3-5 real games on the board, on their own phones, before the flag goes on.

- **Real phone touch**: drag, pinch zoom and tap-to-place on iOS Safari and Android Chrome (the harness uses emulated touch/mouse).
- **A screen reader** (VoiceOver, TalkBack, NVDA): the table's note announcements, the callout legends, the Fate sheet, the placement prompts.
- **Two real players live**: moves, terrain, Fate flips and kills appearing for the other player without a reload, over the real websocket (the harness has none).
- **A tournament round with the round clock** (no running round in the harness).
- **Replacement on death** end to end (it reuses tested code but has no browser step).
- **Many models / a long game**: performance of the SVG board with a full crew each, 5 turns.
- **Real card data**: the harness uses made-up crews; check bases, Sz/Sp, tokens and upgrades on real official models.

## Known gaps (not blockers, but say so in the release notes)

- Push is not modelled (not in the rules text used); the strategy scoring helper waits for structured strategy data.
- Injuries attach only in Campaign games (as on the play screen); a model's art is not drawn in its base (cards have no separate portrait).
- About 100 secondary phone-board controls are under the comfortable 44px size (all are over the 24px WCAG minimum).
- The Other Side has no tabletop.
- Pending replacement prompts are kept per browser, not shared between players or devices.

## Rollback

Switch the flag off (the permission still lets beta testers in). No data migration is needed to roll back: saved tables, board state and Fate decks stay in the database and come back when it is on again.
