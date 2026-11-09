# Cooperative Mode (Index of the Untold)

Source rules: `/var/www/Index.pdf` pp. 66–79 (rules), 80–131 (encounters), 132–137 (NP class cards), 150–151 (optional rules). Full gap analysis and build order: [../plans/cooperative-mode.md](../plans/cooperative-mode.md).

## Status
Step 1 only (reference data + browse). There is no playable co-op tracker yet.

## Data
- `coop_classes` → `App\Models\Cooperative\CoopClass`. One NP class card. `role` is `CoopRoleEnum` (obstructor/brawler/firepower/support/boss). `abilities`, `actions` (kind attack|tactical, Rg/Skl/Rst/TN/Dmg, triggers), `behavior` (rows of flip_min/flip_max/ideal_range/ordered effects) and `target_preference` are JSON because the card is always read as a unit.
- `coop_encounters` → `CoopEncounter`. Number 0 = introduction. `phases` is an ordered JSON list (title, narrative, max_hand_size, role_order[{role, class_slug}], setup/special_rules/victory/defeat/progression lines); phase 1 is shown, later phases sit behind a "Reveal" button (the book says not to read them until instructed). `campaign` JSON holds victory/defeat/aftermath/next encounter.
- Both have `is_published`; only published rows reach the public pages. Encounters reference suggested classes by `slug` (validated to exist).

## Admin
`/admin/cooperative/classes` and `/encounters` (`Admin\Cooperative\*AdminController`, `CoopClassRequest`, `CoopEncounterRequest`). They reuse the **campaign-catalog permission trio** (`view/edit/delete_campaign_catalog`) because it is the same book. List-valued fields are edited one item per line (`components/Cooperative/LinesField.vue`). Nav entry: "Cooperative" section in `App\Support\AdminNav`.

## Public
`/cooperative` (encounter grid + class cards grouped by role) and `/cooperative/encounters/{slug}`; routes in `routes/cooperative.php`. Open beta like Campaigns — no feature flag. Sidebar entry in `resources/js/lib/navData.ts`.

## Gotchas
- Class cards in the PDF are images: the data has to be transcribed by hand through the admin forms (no seeder yet).
- Local harness: the scratch DB needs `php artisan db:seed --class=PermissionSeeder` or every `permission:` admin route 403s.
- `vue-tsc` needs the Blog/News syntax errors fixed (and `types: vue/tsx` resolved) before it can type-check; ESLint + build are the real gates.

## Tests
`tests/Feature/Cooperative/` (`CooperativeAdminTest`, `CooperativePublicTest`).
