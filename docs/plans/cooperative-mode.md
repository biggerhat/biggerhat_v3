# Cooperative Mode — gap analysis and plan

Source: `/var/www/Index.pdf` (Index of the Untold). Printed pp. 66–79 rules, pp. 80–131 the 13 encounters (+ Introduction), pp. 132–137 NP class cards, pp. 150–151 optional rules, p. 152+ token glossary. PDF page = printed + 1. Class cards are images (no extractable text) — they have to be transcribed by eye.

Status: planned. Branch `coop-mode-nov9`. Nothing built yet. The only existing hook is `GameModeTypeEnum::Cooperative` (content scoping on Character/Action/Ability/Keyword/Upgrade).

## What the rules need (summary)
- 1–3 players vs a non-player (NP) crew driven by **class stat cards**. No master; any costed model is the leader (cost does NOT drop to 0). Encounter size (ss) split evenly; leftovers go to ONE shared soulstone pool. One shared crew card (any master sharing a keyword with any leader); all player models gain every leader's keywords.
- **Fate**: one shared player fate deck; personal control hands; a collective max hand size per encounter; a **draft** each start phase (reveal cards until face-up + kept = max hand size; player with fewest cards picks first). NP crew has its own deck, no hand, cannot cheat.
- **Turn shape**: all player activations, then the NP phase. NP phase = per role in the encounter's activation order, flip one NP card; every class of that role performs the matching row of its behavior chart (red joker = double damage that activation; black joker = skip the role). Players resolve targeting/ordering using each class's ideal range and ordered target preferences.
- **Duels**: NP never flips. Player attacking a class: beat Res (tie = win, 1 less dmg). Class attacking a player: player flips vs the card-listed Skl TN (tie = lose, 1 less dmg). NP vs NP is deterministic. NP fate modifiers invert onto the player's flip.
- **Encounters**: narrative text, ss, max hand size, board size, role activation order (+ suggested classes), setup steps, special rules, phases with hidden "do not read further" text, victory/defeat, challenge mode, campaign aftermath + next-encounter rules.
- New tokens Elite (class gets Elite Modifications) and Idle (cannot activate; removed when an enemy comes within 6" or it takes damage, which also clears Idle from models within 1"). Token adjustments for NP models (Focused/Adaptable/Entranced/Fragile Ego/Fast/Slow…).
- Cooperative campaigns: encounters in order, per-player arsenals, leaders hirable at 6/8/10 ss by advancements (totem 4 ss), XP 1/game + 1 if defeated + encounter bonuses, per-encounter Aftermath rules.
- Optional rules: Narrative, Shared Hands, Shuffled Decks, Cooperative Equipment (extra Barter items), Fated, Future Sight, Set Challenge, Wandering Monster, Threads of Fate.

## Gaps in the app
1. **No NP class data.** Needs role, unique flag, Res, Sp, max health (+ elite bonus), abilities/actions (Rg/Skl/Rst/TN/Dmg, triggers), behavior chart (rows: flip range, ideal range, ordered effects with modifiers), target-preference list, elite modifications text. ~20–25 classes across 5 roles (Obstructor, Brawler, Firepower, Support, Boss).
2. **No encounter data.** 14 encounters with phases (each its own setup/special rules/victory/defeat/progression), size, hand size, board, role order, deployment key, campaign aftermath/next-encounter.
3. **Tracker has no fate deck.** Co-op needs shared player deck + NP deck, flips, draft, per-player hands, discard/shuffle. (Bonanza has a loot deck we can pattern on.)
4. **Tracker is 2 slots.** `game_players.slot` is 1–2, unique per game. Co-op needs 1–3 player slots on one team plus an NP "side". Reuse the solo pattern (inert slot-2 opponent) for the NP crew, and extend slots for players 2–3.
5. **No NP crew play screen**: spawn/deploy classes (elite/idle), health, tokens, role-by-role flip → shows each class's resolved row, joker handling, kill/spawn, hidden-phase reveal gating, turn/defeat/victory tracking.
6. **Hiring differences**: no master, leader cost applies, split ss, shared pool, shared crew card, merged keywords.
7. **Campaign**: co-op progression, aftermath variations, leader cost by advancements, Threads of Fate; reuses Arsenal Sheet / Aftermath machinery.
8. Tokens (Elite/Idle) and the Cooperative Equipment barter items.

## Suggested build order
1. **Data + browse (no play yet)**: `coop_classes` + `coop_encounters` (+ phases) migrations/models/factories, admin CRUD, public "Cooperative" reference pages (classes by role, encounter pages with hidden-phase spoiler toggles). Seed by transcribing the cards/encounters. Gives immediate value and de-risks the data shape.
2. **Co-op game setup**: `GameFormatEnum::Cooperative`; pick encounter, 1–3 players, hire with shared ss/pool/crew card/keywords; reuse crew builder rules switches.
3. **Decks + draft** (shared player deck, NP deck, control hands, draft UI, max hand size enforcement).
4. **NP phase engine**: spawn classes, per-role flip → resolved behavior rows, jokers, elite/idle, NP health, kill; players resolve movement/targets manually with target-preference hints.
5. **Encounter flow**: phases, victory/defeat checks, hidden text reveals, end-of-game summary.
6. **Campaign integration** + optional rules toggles + cooperative equipment.

## Decisions needed from the owner
- v1 scope: reference pages only, or through a playable tracker (steps 1–5)?
- Is a digital fate deck wanted (players use physical cards today), or should the tracker just show the NP flip/behavior and let players flip physical NP cards?
- Players per team in realtime (1–3): all online, or one device per table?
- IP: class/encounter text is Wyrd's — match how existing Campaign content is handled (admin-entered data).
