# Custom Card Creator

Users design homebrew Malifaux **character cards** and **upgrade cards**, preview
them rendered in-app, and share via a code. Cards render with the real M4E
styling/fonts (client-side, no server image baking).

## Routes (`routes/web.php`, `card-creator` prefix, `card_creator.` names)
- `CustomCharacterController` — `index`, `create`, `store`, `{customCharacter}/edit`,
  `update`, `destroy`, public `share/{shareCode}`. (CRUD is auth-gated.)
- `CustomUpgradeController` (nested `upgrades` prefix) — same shape + `upgrades.share`.
- Search/autocomplete for embedding existing game entities:
  `API\CardCreatorSearchController`.
- Admin: `admin/custom-cards`.

## Models
`CustomCharacter`, `CustomUpgrade` (`$guarded = ['id']`, `share_code`,
JSON stat/ability/action payloads).

## Frontend
`components/CardCreator/` — `CardRenderer.vue`, `CardFrontFace.vue`,
`CardBackFace.vue`, `UpgradeCardRenderer.vue`, `UpgradeFrontFace.vue`,
`UpgradeBackFace.vue`, and **`utils.ts`** (the shared helpers).
- `utils.ts` embeds the **`M4E-Symbols`** font as base64 (`fetchFontEmbedCSS` /
  `/font/M4E-Symbols.otf`) so html-to-image captures render glyphs — the same
  font/glyph map used by `GameText`/`GameIcon` (see [bonanza-loot.md](bonanza-loot.md)).
- Faction gradient + color helpers also live in `utils.ts`.

## Conventions / Gotchas
- Card capture/preview is **client-side** (html-to-image / a renderer component),
  not a server PDF — different pipeline from the Bonanza print PDF.
- Reuse the `GameText` token vocabulary for ability/action text so custom cards
  match official rendering.

- **Saving** goes through `saveCardJson()` in `utils.ts`: it never throws, and non-validation failures (offline, 419,
  5xx) come back as `message` so the editors can toast them instead of leaving the Save button spinning.
- **Export** goes through `captureCardFaces()` (shared by both editors): inlines unsaved `blob:` portraits for the capture
  and always restores them, even if the capture throws.
- **Drafts**: `composables/useEditorDraft.ts` warns before leaving with unsaved changes and keeps a debounced
  localStorage draft for *new* cards (`bh_draft_card`, `bh_draft_upgrade_<domain>`) with a "Restore draft?" banner.
- `useShrinkToFit` binary-searches the font-size grid (about 5 reflows instead of up to 14).
- **Character art** comes from an admin-curated library (`card_arts` table, `CardArt` model,
  `Admin\CardArtAdminController` at `/admin/card-arts`, super-admin only). A `CustomCharacter` stores `card_art_id`;
  the editor's "Choose art" dialog lists active art (filter by faction / category / search), and the render surfaces
  receive the path (`card_art_path` on the share page and Arsenal Sheet Leader/Totem, `card.characterImage` on the
  capture page). Changing `card_art_id` regenerates Campaign Leader/Totem card images (`CARD_RENDER_COLUMNS`).
  Retired (inactive) art stays on cards that already use it; deleting art clears it from those cards.
  **Framing**: `card_art_x` / `card_art_y` (0-100 focus point) and `card_art_zoom` (50-300%; below 100 the art shrinks inside the window) plus a free offset `card_art_dx` / `card_art_dy` (±100, % of the window) on `CustomCharacter` choose
  which part of the art shows. `ArtImage.vue` renders it in pure CSS (a box `zoom`× the window, shifted and
  cover-positioned by the same x/y), so the live preview, the html-to-image export and the server-side capture all match;
  The offset pushes the art past the window edges on either axis (including the axis where it fits and can't pan). `ArtPositioner.vue` is the drag / arrow-key / zoom-slider editor: dragging adds to one on-screen shift per axis, split into pan first and offset for the remainder, so it stays reversible. Picking different art resets the framing; clearing
  the art resets it server-side too.
  **Saved card images**: every custom character gets a server-rendered front/back/combination PNG
  (`GenerateLeaderCardImage` → headless capture of `CardCreator/Capture`, with the chosen art and framing). The
  Arsenal Sheet and Game Tracker show it for campaign Leaders/Totems and homebrew hires; "My Cards" tiles and the
  share page use it for thumbnails and a *Download card* link. `CustomCharacter::queueCardImage()` (called from
  `CustomCharacterObserver` whenever a card-render column — including art/framing — changes) renders Leaders/Totems
  immediately, and everyone else ~20s after the last save, coalescing bursts of edits into one render (the job clears
  the `renderPendingKey` marker as it starts). Run `php artisan app:regenerate-leader-card-images` to backfill cards
  that existed before this.
  Player uploads are **never stored** — the "Try your own image" option is a preview-only convenience.
  The images live on the `public` disk under `card_arts/` (needs `php artisan storage:link` like other uploads).

## Tests
`tests/Feature/CustomCharacterControllerTest.php`, `CustomUpgradeControllerTest.php`,
`CardCreatorCampaignLeaderTest.php`. The editors' client-side behaviour (drafts, export) has no automated tests.

## Realtime: render-finished events

When a saved card render finishes, `LeaderCardImageGenerator` / `CombinedCrewCardImageGenerator` fire `CardImageGenerated`
(`ShouldBroadcastNow`, private `App.Models.User.{owner}` channel, event `.card.image.generated`, payload `kind`
= `custom_character`|`crew_card`, `id`, `generated_at`). `useCardImageEvents` listens (only removing its own listener, since
`useNotifications` shares the channel); My Cards, the share page and the Arsenal Sheet reload on it. The Arsenal Sheet keeps
its old polling as a fallback when Reverb is unavailable, and does one safety reload after 60s if an event is lost.

## Saved-image states and housekeeping

- `CustomCharacter::cardImagePending()` = the coalescing marker (`renderPendingKey`, waiting for its delay) **or** the `renderingKey` the job holds while Chrome runs. My Cards, the share page and the Editor save bar show "Rendering…" from it.
- `POST /tools/card-creator/{id}/render` (`tools.card_creator.rerender`, owner-only, throttled 10/min) queues an immediate render; ignored while one is already pending. My Cards shows a retry button on tiles with no image; the Editor save bar has "Re-render".
- Deleting a card removes `custom-characters/{id}/` from the public disk (observer `deleted`).
- My Cards tiles fall back to the art/logo if the saved image 404s, link to the share page, and get a search box above 6 characters.
- The Editor's save bar is sticky (offset by `--tabbar-h` on phones) and, when editing, shows image state with Download / Share page / Re-render.

## Limits and privacy (audit, Nov 2026)

- **Public share pages use allow-lists** (`CustomCharacterController::PUBLIC_SHARE_FIELDS`, the `only([...])` in `CustomUpgradeController::share`). The models carry private `notes`, `user_id` and campaign bookkeeping, so never pass a whole model to a public page — add the field to the list when the page needs it.
- **Per-user cap** of 200 custom characters (`CustomCharacterRequest::MAX_PER_USER`) and 200 upgrades/crew cards: every character now costs a headless-Chrome render. Display names are capped at 60 characters server-side (the card has room for ~32).
- `POST /tools/card-creator/{id}/duplicate` copies a card (art, framing, notes) into a plain custom character; campaign state, XP and injuries are not copied.
- Editor searches ignore stale or failed responses (`fetchLatest`), and UI text follows the 11px (`text-2xs`) floor.
- Not handled: deleting a *user account* cascades the rows at the database level, so their `custom-characters/{id}/` image folders stay on disk (the observer only runs for model deletes).

## Saved images for upgrades / crew cards, and render capacity

- Custom upgrades and crew cards get saved front / back / combined images exactly like characters: `CustomUpgrade::queueCardImage()` (20s delayed, coalesced) → `GenerateCustomUpgradeCardImage` → `CustomUpgradeCardImageGenerator` (shares `CapturesCardImages` with the character generator) → capture page `CardCreator/UpgradeCapture.vue` (`tools.card_creator.upgrades.capture`). Files live in `custom-upgrades/{id}/`; `CardImageGenerated` kind `custom_upgrade`; `card_image_pending`, `…/render` and `…/duplicate` mirror the character endpoints. `app:regenerate-leader-card-images` backfills both.
- **Global render cap:** every Chrome render job (`GenerateLeaderCardImage`, `GenerateCustomUpgradeCardImage`, `GenerateCombinedCrewCardImage`) runs through `LimitConcurrentRenders`, which allows `services.browsershot.max_concurrent_renders` (env `BROWSERSHOT_MAX_CONCURRENT_RENDERS`, default 2) at once across all workers via cache locks, and releases the rest back to the queue. The jobs use `retryUntil` (15 min) so being released never burns a try. Needs a cache store that supports locks (redis / database / file).
- Deleting a user (admin or self) clears their `custom-characters/*` and `custom-upgrades/*` folders (`User::booted`), since the database cascade fires no model events.

## UI notes (design audit, Nov 2026)

- My Cards tiles are card-gallery tiles: a `3/4` image area showing the saved front image (`object-contain`), stacked on `sm+`, and a horizontal thumbnail + details row on phones. One shared tile serves the Crew Cards and Upgrades tabs. Icon-only actions are fixed `size-8` (40px on coarse pointers) so five of them always fit.
- Below `lg` the Editors put the preview *after* the form (it is a screen-tall card); the sticky save bar's **Preview** button scrolls to `#card-preview`.
- Amber "Campaign" badges use `bg-amber-700` (white on amber-600 fails contrast at this size).

## Saved image size

Both faces of a saved image are always the same exact tarot size (2.75 x 4.75 in proportions, like the Bonanza cards). The capture pages (`CardCreator/Capture.vue`, `CardCreator/UpgradeCapture.vue`) use `useTarotFit`: start at the smallest tier (`TAROT_WIDTH_TIERS`, 550px wide, rendered at 2x) and step up until the taller face's content fits, so text-heavy cards get a *larger* tarot card instead of a taller, off-ratio one. Dead space inside the border is expected. Only past the largest tier does a face grow taller (both still match). The page sets `data-capture-ready` when settled and `CapturesCardImages::capture()` waits for it before the screenshot.
