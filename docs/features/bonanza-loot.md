# Bonanza Loot (Bonanza Brawl)

Solo loot-deck format. Three surfaces: the **Loot Deck reference page**, the
**print PDF**, and **Bonanza Brawl mode** inside the [Game Tracker](game-tracker.md).

## Data
- `LootCard` model: `suit` (crow/mask/ram/tome/joker), `value`, `value_label`,
  `title_a/b`, `effect_a/b`, `image`, side relations `sideAActions`/`sideBActions`
  (+ `triggers`), `sideAAbilities`/`sideBAbilities`, `sideATriggers`/`sideBTriggers`.
- 54 cards seeded by `Database\Seeders\LootCardSeeder` (13/suit + 2 jokers).
- Admin: `Admin\LootCardAdminController` (`admin/loot-cards`, super_admin).

## Reference page
`tools.bonanza_loot_deck` → `pages/Tools/BonanzaLootDeck.vue`, rendering
`components/Bonanza/BonanzaSplitCard.vue` → `LootEffectText` →
`LootAbilityDisplay` / `ActionCard` / `LootTriggerDisplay`. Tokens render via
`GameText`.

## Print PDF (headless Chrome)
- `PrintBonanzaLootDeckController` serves a **cached** PDF.
- `App\Services\BonanzaDeckPdfGenerator` renders `resources/views/PDF/BonanzaDeck.blade.php`
  with **Browsershot** (`->noSandbox()`, Letter, 4 tarot cards/page, crop marks).
- **Cache** lives on the public disk at a path that embeds a **hash of the Blade
  template** (`loot-deck-{hash}.pdf`) — a template/glyph change busts it
  automatically; also stale when a card's `updated_at` is newer. `isStale()` +
  the print route regenerate inline on demand (self-healing).
- `Jobs\GenerateBonanzaLootDeckPdf` (queued, unique) pre-warms the cache on card
  create/update/delete + a manual admin button; broadcasts `Events\BonanzaDeckPdfStatus`
  over Reverb (admin "Print Deck PDF" panel shows live status).

## Glyphs (icons)
`components/GameText.vue` `tagToIconType` is the **canonical** token→icon map
(`fortitude`→physical_defense, `warding`→magical_defense, `unusual`→unusual_defense,
`stone`→soulstone, suits, range types, `signatureaction`). `GameIcon` maps types
→ single chars in the **`M4E-Symbols`** font (`public/font/M4E-Symbols.otf`):
crow=c mask=m ram=r tome=t soulstone=s melee=y missile=z magic=q pulse=p
physical_defense=u magical_defense=x unusual_defense=v signature=f.
**The PDF Blade mirrors this map** — keep them in sync; unmapped tokens fall back
to `(Word)`.

## Tracker mode (Game Tracker)
`GameFormatEnum::BonanzaBrawl` — forced solo, 11ss, fields a **single non-Leader
model** (`GameController::buildBonanzaCharactersProp` excludes `station = 'master'`
but keeps NULL stations), auto-creates one `GameCrewMember`, seeds `loot_state`
on the game at the in_progress transition. Loot draw/select/yoink in
`GamePlayController`. No Summon, no Crew/Scheme select.

## Deploy
Needs `npm install` (puppeteer's Chromium) + Chromium system libs (libnss3,
libnspr4, libgbm1, libasound2, …). Browsershot binary overrides via
`config/services.php` `browsershot.*`.

## Tests
`tests/Feature/Tools/BonanzaLootDeckTest.php`, `BonanzaPrintTest.php`,
`tests/Feature/Admin/BonanzaDeckPdfTest.php`, `tests/Feature/Games/BonanzaBrawlTest.php`.
