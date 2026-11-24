# Collection (Malifaux)

What a player owns: miniatures and packages, and how far along each physical model is. (TOS has its own collection, `TOS/CollectionController`, which tracks
built/painted as counts per sculpt and was not changed by the models work.)

**Help:** `resources/js/lib/help/topics/collection.ts` (`/help/collection`, a Help button in the page header). Change it in the same change as the page.

## Two layers

| Table | Holds |
|---|---|
| `user_miniatures` | "I own this miniature": `quantity`, plus `is_built` / `is_painted` kept in step with the models (older code and links read them) |
| `collection_models` | One row per physical model: `stage` (`ModelStageEnum`) and a short `note` (255) |

**`App\Support\Collection\CollectionModels` is the only thing that should write either.** It changes the model rows, then `refresh()` brings the miniature
row in line (quantity = number of models, `is_built` / `is_painted` = every model is). `reconcile()` goes the other way and repairs the model rows when a
miniature row was written some other way (a seeder, a test, old code): it adds the missing models (taking the stage from the old flags when there are none
yet) or drops the least progressed extras. It runs at the start of `totals()` and whenever a write touches a miniature, so the collection page, the profile
stats and the account overview all heal old data on first read.

Removing a miniature (`remove`, `remove-bulk`, quantity 0, the last model) deletes its model rows. Copies per miniature are capped at
`CollectionModels::MAX_PER_MINIATURE` (200).

## Stages

`unbuilt → built → primed → painted → based (table ready)`, in that order. Painted counts as built, and based counts as painted. "Built" and "painted"
everywhere (summary card, filters, profile stats, achievement badges) are read from the stage with `isBuilt()` / `isPainted()`.

## Data from before the models

`2026_11_24_100001_backfill_collection_models` turns every existing row into `quantity` models: painted → painted, built → built, else unbuilt. Nothing is
removed from `user_miniatures`. It is safe to run twice. A row that was painted but not built (possible with the old two flags) now counts as built too.

## Endpoints (`routes/web.php`, `collection.*`, all signed in)

- `toggle` (set quantity), `remove`, `remove-bulk`, `add-character(s)`, `add-package`: as before, through the service.
- `update-status`, `update-status-bulk`: "every copy built / painted (or not)". Built false puts copies back to unbuilt; painted false keeps them built.
- `set-stage-bulk`: every copy of the chosen miniatures to one stage.
- `models.store` / `models.update` / `models.destroy`: one copy. Only its owner can change it (the FormRequest's `authorize()`).

## Page (`pages/Collection/Index.vue`, `components/Collection/ModelList.vue`)

Each row shows one dot per model (`StagePips`, colours in `lib/collectionStages.ts`, always with a text count beside it) and "Built 2/3 · Painted 1/3" when there is more than one copy, the hammer / paintbrush buttons act on every copy, and "Models" opens the copies
(stage with its dot, note, remove, "Add a model"). Removing a miniature, several at once, or a copy that has a note or is the last one asks first (`useConfirm`). The character card carries the faction stripe (`factionSpine`). The summary card and stats count models. The summary says "N models · M miniatures · P packages". The status chips count miniatures and mean "at least one copy to do" (Unbuilt, Unpainted) or
"every copy" (Painted). A shared collection (`/collection/share/{code}`) shows stages but **never notes**.

### The other tabs

- **Packages:** each card compares what the package holds (`packageables.quantity` per character) with the models the player owns of those characters, best
  first (`owned`, `built`, `painted`, `contents[]`), with a "What is inside" list. Removing a package asks first and keeps the miniatures it added.
  Placeholder tile when there is no image. "Browse packages" links to the package list.
- **Factions:** only factions with characters; each card shows models, built and painted, and opens the Collection tab filtered to it.
- **Keywords:** most complete first (then bigger), keywords with nothing owned hidden until "Show N not started" or a search; searchable.
- **Stats:** models by stage (the stacked bar uses `stages[]`), "Ready to paint" (built or primed) and "Still in the box" (unbuilt) lists that open the Collection
  tab searched for that model, top factions, and most complete keywords (prefers keywords of 3+ characters, so 1 of 1 does not outrank 20 of 21).
- An empty owner's page opens with a "Start your collection" card, and the tabs have their own empty states.
- The tab is in the address (`?tab=packages`; the Collection tab needs none).

**Deferred props (`owned_packages`, `keyword_stats`) are `undefined`, not `null`, on a first load.** Test them with `!`. A `=== null` check reads `.length` of
undefined when a tab other than Collection is open on first paint.

Payload: `collection[].models[] {id, stage, note}` (note only for the owner), `built_count`, `painted_count`, `stages[]`, `totals` (by model).
Faction totals are two grouped queries rather than loading the catalogue.

## Not done

No photos per model. No per-model history (when it was painted). TOS collections still use counts per sculpt.

Browser flow: `scripts/ui-audit/tournaments/flows45.cjs` (seeded with a collection written the old way).
