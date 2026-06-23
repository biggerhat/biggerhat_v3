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
