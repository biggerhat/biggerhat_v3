# Campaign Mode

Multi-week Malifaux campaign (the M4E "Index of the Untold" style): a crew
persists across weeks, hires from a **Starting Arsenal**, builds a **Leader**,
plays weekly games, and resolves aftermath (injuries, advancements, equipment).

## Access / Routes
- **Feature-flagged**: routes in `routes/campaign.php` (required from `web.php`)
  are wrapped in the **`campaign.access`** middleware → `App\Support\CampaignAccess::canUse(user)`.
  Feature flags 404 (not 403) to hide pre-release features.
- Admin catalog: `routes/admin.php` `campaign` prefix (also `campaign.access`),
  gated by `view/edit/delete_campaign_catalog` permissions.

## Controllers (`app/Http/Controllers/Campaign/`)
`CampaignController`, `CampaignGameController`, `CampaignInvitationController`,
`CampaignTeaserController`, `CampaignAftermathController`, `WeeklyCycleController`,
`WeeklyHireController`, `StartingArsenalController`, `LeaderBuilderController`,
`LeaderSearchController` (action/ability search for the leader builder),
`CrewLifecycleController`, `ArsenalSheetController`.

## Models (`app/Models/Campaign/`)
`Campaign`, `CampaignPlayer`, `CampaignCrew`, `CampaignCrewCard`, `CampaignWeek`,
`CampaignGame`, `CampaignInvitation`, `CampaignArsenalModel` (+ `…Injury`),
`CampaignEquipment`, `CampaignLeaderAdvancement`, `CampaignAftermath`,
`WeeklyEvent`, plus flavor result tables (`BackAlleyDoctorResult`, `LuckyMiss`).

## Frontend
`pages/Campaigns/` — incl. `LeaderBuilder.vue` (action/ability selection with a
source-ally cost cap; characteristics from a fixed `characteristic_options` list).

## Conventions / Gotchas
- **Leader builder cost cap** is rulebook-correct: cap the **source ally's** cost
  (`source_character_id` → `Character.cost`) `<=` the cap, not the action's own
  `stone_cost` — enforced in both `LeaderSearchController` and `StoreLeaderRequest`.
- Stateful weekly flows need `lockForUpdate` inside `DB::transaction` with a
  re-check after the lock (see CLAUDE.md Concurrency).
- Eager-load only real columns (e.g. campaign keywords have no `faction` column).

## Tests
`tests/Feature/Campaign/*` — leader builder search/save, weekly cycle/hire,
arsenal, aftermath, concurrency edges.

## Equipment rules in play
- Equipment is a crew-level pool; it is assigned to models at CrewSelect (`GameSetupController::resolveEquipmentAssignments`),
  which enforces `campaign_leader_only`, `campaign_non_unique_only` and Omen's Mark being attached every game.
- **Those Who Thirst** (pg 29-30): red joker on the barter → pick from the TTW table in Aftermath Phase 3 (`ttw_pickup`,
  source `joker`). One TTW item per crew; Omen's Mark is free and exempt. In Phase 6, `omens_mark_killed` annihilates the
  crew's mark and gives one to the opposing crew (pre-ticked when a linked tracker game shows the carrier died).
- **Corrupted Pawns** (optional rule, pg 146): `campaign_equipment.bound_arsenal_model_id` + source `starting_corrupted_pawns`.
  Picked on the Starting Arsenal page, auto-attached to its model at hire, not counted in the TTW limit, not removable by
  hand, and annihilated/restored with its model (`CampaignArsenalModelObserver`). Re-saving the starting arsenal resets it.
- Not modelled: "Laid to Rest" (the victim flips twice) is only a hint in Phase 6; `campaign_annihilate_after_game` is unused.

## Local seed data
`CampaignCatalogSeeder` (placeholder advancement tables/crew cards, plus the real Back-Alley Doctor and
Lucky Miss tables) + `CampaignRulebookSeeder` (the book's real equipment, Those Who Thirst items and
injury table, pg 22-35) + `CampaignSeeder` (two users `alpha@`/`beta@biggerhat.test`, a campaign and a
live game). Log a game via `/campaigns/{id}/games/log` to walk the Aftermath wizard end to end.
