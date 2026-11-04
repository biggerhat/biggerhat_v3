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

## Description fields: icons instead of `{{ }}`

Card text is still stored as plain strings with `{{tag}}` tokens (so `GameText`, exports, the API and old cards are unchanged), but every description field in the editors is a `GameTextEditor` (`components/CardCreator/GameTextEditor.vue`): suits, ranges, soulstones etc. appear as real inline icons while editing. The `IconTokenPalette` inserts an icon at the caret, pasted `{{tag}}` text becomes icons, Backspace removes an icon whole, Enter adds a line break in multiline fields and is ignored in single-line ones. The icon table and tag map live in `lib/gameIcons.ts` (shared with `GameIcon` / `GameText`); the contenteditable ↔ string conversion is in `lib/gameTextDom.ts`.

My Cards (`CustomCharacterController::index`) selects only the columns the tiles render, not the full rows with every action, ability and note.

## Live preview, thumbnails, editor structure (Nov 2026)

- **Preview = saved image.** `CardRenderer` / `UpgradeCardRenderer` use `composables/useScaledTarotPreview`: faces are laid out at the real tarot size (`useTarotGrowToFit`, tier 0 upward, same rule as the capture pages' `useTarotFit`) and scaled down by CSS transform to the column. Text therefore wraps exactly as on the saved card. `useTarotGrowToFit` reads `offsetWidth` (not `getBoundingClientRect`) so a scaled preview still measures at its layout size.
- **Thumbnails.** Every render also writes `{dir}/thumb.webp` (480px wide, GD; `CapturesCardImages::makeThumbnail`) and sets `thumb_image` on `custom_characters` / `custom_upgrades`. My Cards tiles use `thumb_image || front_image`. If GD lacks WebP the column stays null and tiles fall back to the full image. For rows rendered before this existed run `php artisan app:backfill-card-thumbnails` (reads PNGs on disk, no Chrome).
- **Unsaved changes.** `useEditorDraft` exposes `dirty`; both save bars show "Unsaved changes" and hide Re-render while dirty (it would re-render the saved, not the edited, card).
- **Upgrade editor.** "Duplicate from an official upgrade" (create only) uses `api.card-creator.upgrades` + `upgrade-detail` (`CardCreatorSearchController`); `{{ability=}}` / `{{action=}}` / `{{trigger=}}` placeholders in official descriptions are swapped for the attached items in order. Keyword and Master Name use `SuggestInput` (free text with official suggestions).
- **Editor structure.** Character editor's Abilities / Actions cards are `components/CardCreator/AbilitiesPanel.vue` / `ActionsPanel.vue` (`v-model` array edited in place, own search + Browse modal); shared types in `components/CardCreator/types.ts`; search race guard is `composables/useLatestFetch`.
- **Leader Builder art.** The Campaign Leader Builder (`Campaigns/LeaderBuilder.vue`) has the same library-art chooser + framing via the shared `components/CardCreator/CardArtField.vue`. Options/rules/normalising live on `CardArt` (`pickerOptions`, `validationRules`, `normalizeFraming`) and are shared by `CustomCharacterRequest` and `StoreLeaderRequest`. Saving the builder creates a fresh leader row, so `LeaderBuilderController::update` carries the previous leader's art over when a request doesn't send any. Totems are not covered (they are created by advancements) — their art is picked in the card editor.

## Round 3 additions (Nov 2026)

- **Campaign crew cards in My Cards.** A crew card tied to a live crew is shown as the crew's *combined* Crew Card: `CustomUpgrade::useCombinedCrewCardImages()` swaps the display attributes (index, editor, share page; never saved) to `campaign_crews.crew_card_*` — which now also holds `crew_card_combination_image` and `crew_card_thumb_image`, written by `CombinedCrewCardImageGenerator` (reset with the other crew card images on Starting Anew).
- **Render slot reserved for the campaign.** `LimitConcurrentRenders(priority:)` — with more than one slot the last one is only usable by leaders, totems and combined crew cards; homebrew character/upgrade renders (`priority: false`) wait for the others.
- **Art from the Arsenal Sheet.** `PATCH tools/card-creator/{id}/art` (`CustomCharacterArtRequest`, art + framing only) backs the "Leader Art" / "Totem Art" dialog (`CardArtField`). It is JSON — call it with `fetch`, not `router.patch`.
- **JSON import / export.** `composables/useCardJsonTransfer` wraps the editors' draft snapshot in `{app:'biggerhat', kind, version, data}`; private notes and links to the owner's custom cards are scrubbed on export; import only fills the form (server validation still applies on save). `restore` in both editors only trusts keys the form has and array-shaped lists.
- **Copy from a share link.** `POST tools/card-creator/shared/{shareCode}/copy` (and `upgrades/shared/...`) for signed-in visitors — `CopyToMyCards.vue` on the share pages; copies without `notes` and without links to the owner's custom cards. Share pages now receive `share_code` as its own prop (the allow-list still excludes everything private).
- **Bulk select** in My Cards: delete and re-render run the per-card endpoints one after another (stops on a 429); campaign-linked cards are never offered for delete.
- **Version history.** `custom_card_versions` (morph) holds the last 10 previous states per card, recorded by the observers via `HasCardVersions::recordPreviousVersion()` after an edit that changed card content. Not kept for leaders/totems/crew cards (their content is tied to advancement logs). Restore = a normal update (so it is undoable) and a full page load. `CardHistoryDialog` closes itself while the shared confirm dialog is open (the confirm mounts underneath it).
- **Undo for inline icons.** `GameTextEditor` keeps its own value history (Ctrl/Cmd+Z, Ctrl+Shift+Z / Ctrl+Y, `historyUndo` from menus); typing in quick succession shares one step, icon inserts and pastes are their own.
- **Splits.** `ContentBlocksPanel`, `TokensMarkersPanel` (upgrade editor) and `AbilitiesPanel` / `ActionsPanel` (character editor); the panel components must not carry the parent's `v-if`.
- Deploy: `php artisan migrate`, then `app:backfill-card-thumbnails`; crew-card combined images/thumbnails appear after each crew's next render (`app:regenerate-crew-card-images` forces it).

## Art linked to characters (Nov 2026)

- `card_arts.character_id` (nullable, `nullOnDelete`) links a piece of library art to the official character it depicts; the admin form has a "Depicts (optional)" search. It never restricts who can use the art.
- **Faction and category are filters, not limits.** The picker's faction checkbox (on by default, can be unticked) and category chips only narrow the list; the server only checks that the art exists and is active (or is the card's current art).
- Suggestions: `CardArtField` (`suggestCharacterId` / `suggestName`) puts art linked to the cloned character — or to a character whose name matches the card's — first, with a "Suggested" badge, never hides it behind the faction filter, and shows a one-click strip when the card has no art. Cloning an official character in the editor auto-picks its linked art if the card has none. The Leader Builder and Arsenal Sheet dialogs suggest by name.
- Local harness note: a from-scratch `migrate` on SQLite needs `APP_ENV=testing` (older migrations drop foreign keys by name); never `source` the harness env in the same command as the test suite — it points the tests at the harness DB.
