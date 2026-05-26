# BiggerHat v3 — Terminology Glossary

Canonical user-facing vocabulary across Malifaux and The Other Side (TOS). When in doubt, use these terms. **Model names and DB columns are not renamed by this glossary** — only the strings users see (Vue templates, page banners, button labels, route name segments where they appear in copy).

If you find a UI string that contradicts an entry below, prefer this document and update the string. If you think the canonical term is wrong, edit the glossary first; PRs that change UI strings should reference the line here that justifies the change.

---

## Cross-system

| Term | Meaning | Notes |
|---|---|---|
| **Game system** | Top-level Wyrd ruleset. We support two: Malifaux and The Other Side. | UI label rendered in `GameSystemSwitcher.vue`. |
| **Character** | A single rules entity (the database row — Lady Justice, Reichart, etc.). | DB column / model name is `Character`. The same person across multiple sculpts is one Character. |
| **Miniature** | A specific *sculpt variant* of a Character. One Character has 1..N Miniatures. | "Miniature" is the physical model; "Character" is the rules. Don't use "Model" in UI text — it's ambiguous. |
| **Crew** | The Malifaux roster of Characters you bring to a game. | Malifaux only. TOS equivalent is "Company". |
| **Company** | The TOS roster of Units you bring to a game. | TOS only. Malifaux equivalent is "Crew". |
| **Garrison** | A TOS *tournament pool* — a larger set of Companies you can field across multiple games. | TOS only. No Malifaux equivalent. |
| **Arsenal** | A Campaign Mode *persistent crew + equipment + injuries + advancements* across multiple games. | Campaign Mode (Malifaux) only. Backed by `CampaignCrew` + related models. |

---

## Malifaux

| Term | Meaning | Notes |
|---|---|---|
| **Faction** | One of the 8 top-level Malifaux affiliations (Arcanists, Bayou, Explorer's Society, Guild, Neverborn, Outcasts, Resurrectionists, Ten Thunders). | Backed by `FactionEnum`. TOS equivalent is "Allegiance". |
| **Keyword** | A sub-faction tag that gates in-keyword hiring. | E.g. Family, Family/Ten Thunders. Indexed via `Keyword` model. |
| **Action** | A discrete in-game effect a model performs (attack or tactical). | Has AP cost, range, stat, suits, damage track. |
| **Ability** | A passive/triggered rule on a model. | Doesn't spend AP. |
| **Trigger** | A modifier that fires on an action when a suit appears in the duel. | Belongs to an Action. |
| **Upgrade** | A card attached to a Character that modifies its rules. "Character Upgrade" or "Crew Card" depending on attachment. | DB column `domain` distinguishes via `UpgradeDomainTypeEnum`. |
| **Crew Card** | An Upgrade attached to the Crew (not an individual Character). | UI label only; underlying model is `Upgrade` with `domain = crew`. |
| **Crew Builder** | The interactive tool at `/tools/crew-builder/editor`. Build a list, save, share. | Singular noun. "Crew Build" is the saved record (`CrewBuild` model). Don't use "Crew Build" in UI. |
| **Community Crews** | The public browse list of shared `CrewBuild` records. | Sidebar label. Was previously the Crew Builder default landing — now the editor lands there. |
| **Game Tracker** | The in-game scoring/activation tool at `/games/*`. | Aka "Game" in route names. |
| **Tournament Tracker** | The Swiss pairing + standings tool at `/tournaments/*`. | |
| **Campaign Mode** | Index of the Untold M4E campaign system. Persistent Arsenal Sheet, Aftermath wizard, etc. | Currently flag-gated as alpha. |
| **My Collection** | Models the user already owns. | Tracks assembly/paint/play status. |
| **My Wishlists** | Models the user *wants to buy*. Pre-Collection state. | Distinct from Collection — explicit in the page banners. |
| **My Stats** | Per-user gameplay statistics from the Game Tracker. | Public via `/stats/{user:slug}`, auth via `/my-stats`. |

---

## The Other Side

| Term | Meaning | Notes |
|---|---|---|
| **Allegiance** | One of the top-level TOS sides. Earth-type (Abyssinia, King's Empire, etc.) or Malifaux-type (Cult of the Burning Man, Gibbering Hordes, etc.). | Backed by `AllegianceEnum` + `tos_allegiances` table. Malifaux equivalent is "Faction". |
| **Syndicate** | A sub-faction that hires across multiple Allegiances of the same type. | Flagged `is_syndicate` on `tos_allegiances`. |
| **Unit** | A TOS rules entity (analog to Malifaux's Character). | Two-sided card: Standard side + Glory side. |
| **Sculpt** | A specific physical mini for a Unit (analog to Malifaux's Miniature). | TOS-specific table `tos_unit_sculpts`. |
| **Fireteam / Squad / Combined Arms / Adjunct / Reserves** | TOS Special Unit Rules pivoted onto `tos_unit_special_rule`. | Don't expand these abbreviations in UI; the rulebook uses them. |
| **Company Builder** | The TOS roster-construction tool. | Singular noun. "Company" is the saved record. |
| **Garrison Builder** | The TOS tournament-pool tool that holds multiple Companies. | Pure TOS — no Malifaux equivalent. |

---

## Patterns to watch

These are surfaces where the wrong term tends to leak in:

- **"Model"** in user copy → use **Character** (rules entity) or **Miniature** (sculpt) depending on context. The word "Model" never wins.
- **"Crew Build"** in user copy → use **Crew** (the thing) or **saved crew** (when referring to the record). Reserve `CrewBuild` for code/DB references.
- **"Faction" in TOS context** → use **Allegiance**.
- **"Allegiance" in Malifaux context** → use **Faction**.
- **"Upgrade" when you mean a Crew Card** → check `domain` — if attached to a crew (not a character), say **Crew Card**.

---

## When to update this file

- A new term enters the UI lexicon (new feature, new sub-system).
- A rename happens (e.g. if Wyrd releases a new ruleset that renames Allegiance → something else).
- A reviewer catches a string that contradicts an entry — update either the string or this glossary, whichever is wrong.

Tooling note: this glossary is referenced from `CLAUDE.md`'s Code Conventions section. Search `grep -rn 'docs/terminology'` to find inbound links.
