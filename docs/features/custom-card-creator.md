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
  **Framing**: `card_art_x` / `card_art_y` (0-100 focus point) and `card_art_zoom` (100-300%) on `CustomCharacter` choose
  which part of the art shows. `ArtImage.vue` renders it in pure CSS (a box `zoom`× the window, shifted and
  cover-positioned by the same x/y), so the live preview, the html-to-image export and the server-side capture all match;
  `ArtPositioner.vue` is the drag / arrow-key / zoom-slider editor. Picking different art resets the framing; clearing
  the art resets it server-side too.
  Player uploads are **never stored** — the "Try your own image" option is a preview-only convenience.
  The images live on the `public` disk under `card_arts/` (needs `php artisan storage:link` like other uploads).

## Tests
`tests/Feature/CustomCharacterControllerTest.php`, `CustomUpgradeControllerTest.php`,
`CardCreatorCampaignLeaderTest.php`. The editors' client-side behaviour (drafts, export) has no automated tests.
