# Lore Linking

Lore entries (story/background articles) with attached media, cross-linked to
the model databases — a Lore entry can reference **Malifaux Characters** and
**TOS Units**, so those model pages can surface related lore.

## Routes
- Public: `routes/web.php` `lore` prefix → `Database\LoreController` (`lores.*`).
- Admin: `routes/admin.php` `lores` + `lore-media` prefixes →
  `Admin\LoreAdminController`, `Admin\LoreMediaAdminController`.
- API: `API\V1\LoreController`, `API\V1\LoreMediaController`.

## Models
- `Lore` relations:
  - `media()` → `belongsToMany(LoreMedia, 'lore_lore_media')`
  - `characters()` → `belongsToMany(Character, 'character_lore')` (Malifaux)
  - `tosUnits()` → `belongsToMany(TOS\Unit, 'lore_tos_unit', 'lore_id', 'tos_unit_id')`
- `LoreMedia` — attached images/embeds (managed via `LoreMediaAdminController`).

## Frontend
`pages/Lore/`. Model pages (Characters, TOS Units) display linked lore via the
inverse relations.

## Conventions / Gotchas
- Linking is **many-to-many via explicit pivot tables** — keep the pivot names
  exact (`character_lore`, `lore_tos_unit`, `lore_lore_media`).
- Cross-system: the same Lore entry can link to *both* a Malifaux Character and a
  TOS Unit; don't assume a single game system per entry.

## Tests
`tests/Feature/TOS/LoreUnitLinkTest.php` (TOS-unit link) + lore controller/admin
tests.
