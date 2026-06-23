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

## Tests
No dedicated feature tests yet — add under `tests/Feature/` (CRUD gate, store
validation, share-code resolution) when touching this area.
