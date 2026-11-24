# Help

User-facing help for the major features: how each one works, in the words on its screens. Rules of the game are linked to, not reproduced.

## Keep it current (this is the rule)

**When you change or add to a feature that has a help topic, change its topic in the same change.** That means a new button, tab, step or
option, a renamed label, a removed feature, a changed shortcut, or a flow that works differently. Features with a topic today:

| Feature | Topic file | Page |
|---|---|---|
| Crew Builder | `resources/js/lib/help/topics/crewBuilder.ts` | `/help/crew-builder` |
| Game Tracker | `resources/js/lib/help/topics/gameTracker.ts` | `/help/game-tracker` |
| Collection (Malifaux) | `resources/js/lib/help/topics/collection.ts` | `/help/collection` |
| Tabletop (sandbox and a game's board) | `resources/js/lib/help/topics/tabletop.ts` | `/help/tabletop` (behind the tabletop flag) |

Use the names that are on the screen (the button says "Submit Turn", so the help says "Submit Turn"). If you are unsure how something
behaves, read the code or run it: unverified help is worse than none. A PR that changes one of these features should say in its description
whether the help was updated (the `pr-prep` skill asks).

## How it is built

- **Text** is plain data: `resources/js/lib/help/topics/<name>.ts` exports a `HelpTopic` (`lib/help/types.ts`): sections of titled items,
  and optionally shortcut groups. Registered in `lib/help/index.ts` (`HELP_TOPICS`).
- **Which topics exist and who sees them** is `App\Support\Help\HelpTopics` (`SLUGS`, `allows()`). A pre-release feature's topic is a 404 until the
  feature is open (the tabletop's uses `TabletopAccess`). A topic's slug must be in `SLUGS` and its file `lib/help/topics/<camelCaseSlug>.ts`.
- **Pages**: `HelpController` -> `Help/Index.vue` (`/help`, `help.index`) and `Help/Show.vue` (`/help/{topic}`, `help.show`). A "Help" link is in the
  sidebar under Community.
- **In the feature**: `components/Help/HelpButton.vue` (`<HelpButton topic="crew-builder" section="building" />`) opens `HelpDialog.vue` at a
  section; or use `HelpDialog` directly (the tabletop board opens it from its own Help button, the terrain editor's Help and the `?` key).
  `HelpContent.vue` renders a topic and is shared by the dialog and the page.
- The tabletop topic also exports `TRAIT_HELP`, used as the terrain trait tooltips.

## Pictures

A help item can carry a screenshot: `image: { src, alt, callouts }` on the item (`lib/help/types.ts`). They live in `public/images/help/<topic>/` (**not**
`public/help/`: a real folder at `/help` shadows the `/help` pages on nginx and `php -S`) and `lib/help/images.json` records each one's size and how many
numbered callouts it has. The numbers are drawn on the screenshot and `callouts` is the same legend as text (also the text version for a screen reader).

- **Remake them all**: `HELP_SHOTS=1 FLOWS=flows38.cjs CHROME=... scripts/ui-audit/tournaments/run.sh` (see `docs/ui-audit.md` for the Chrome and
  `LD_LIBRARY_PATH` setup; `public/hot` must be moved aside and `npm run build` done first). It signs in as the harness's demo users, puts each screen in its
  state, draws the numbers on the real elements, and writes the images and `images.json`. Light theme, WebP, the tabletop ones at 2x.
- **Add one**: add a `snap(...)` step to `scripts/ui-audit/tournaments/flows38.cjs` (a region, plus `callouts`: a CSS selector, `{ text, tag }`, or `{ sel, at }` to
  put the number on the `top`/`bottom`/`left`/`right` of the element), run it, **look at the image**, then add `image` to the item with the same number of
  `callouts`. The script fails if a callout's element is gone or its number falls outside the picture.
- **When a screen changes** (a button moves or is renamed), remake the pictures for that feature and look at them: the script fails loudly if an element
  is missing, but not if a number is now pointing at the wrong thing.
- Pictures show demo data only (made-up crews such as Marshal Hale, no card art). Keep it that way: no real card images, no real users.
- `help.test.mjs` checks every referenced image exists, is in the manifest, has alt text and a legend matching its numbers, and that none is left unused.

## Adding a topic for a new feature

1. Write `lib/help/topics/<name>.ts` (copy the shape of an existing one) and add it to `HELP_TOPICS` in `lib/help/index.ts`.
2. Add its slug to `HelpTopics::SLUGS` and a case in `allows()` (true, or the feature's own gate).
3. Put a `<HelpButton topic="<slug>" />` in the feature's header; add a row to the table above and a "Help" pointer in the feature's doc.
4. `npm run test:tabletop` (it runs `lib/help/help.test.mjs`) and `php artisan test tests/Feature/HelpTest.php` check the two sides agree and the text is complete.

## Tests

`resources/js/lib/help/help.test.mjs` (every topic complete; registry matches the PHP list; tone guard; tabletop trait, preset and shortcut coverage),
`tests/Feature/HelpTest.php` (pages, the tabletop gate, the old `/tools/tabletop/help` redirect, the topic files and routes exist). Browser steps in
`scripts/ui-audit/tournaments/flows35.cjs` (tabletop help) and `flows37.cjs` (the dialog and pages, with axe, and that every picture loads); the pictures themselves are made by `flows38.cjs`.
