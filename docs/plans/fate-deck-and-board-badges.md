# Fate deck, status badges and strategy setups (scoping)

Ideas taken from looking at VASSAL's Malifaux module (the library page and the Wyrd forum threads could not be read; the one confirmed feature list is the 2020 update: draw hand 5/7, multi-card draws, deck and discard trackers, card stacking, glow-rim shortcut). Nothing here is built yet.

The goal behind (1) is full games inside the game tracker, so it comes first and carries the most design weight.

## 1. Fate deck, hands and flips

### What the tracker has today
Nothing: no deck, hand, flip or discard state anywhere (checked `app`, migrations, `Games/*`, `components/Game`). It is a new feature, not an extension.

### Rules it must model (CRG p.6-9, text extracted 2026-11)
- Each player has their **own** 54-card Fate deck: four suits of 13 (Rams, Masks, Tomes, Crows) plus a Red and a Black Joker. Red Joker is value 14 with a suit of its owner's choosing; Black Joker is value 0, no suit.
- Places a card can be: deck (face down, nobody may look through it), **control hand** (owner only; maximum 7), **conflict** (public), **discard pile** (face up, public, order may not be changed), or **removed from the game** (set aside face up, never reshuffled).
- Start Phase (p.31): B. Discard any number of cards from hand, C. draw until the hand is at the maximum. A hand over the maximum after any action, trigger or ability must discard down. There is no separate opening-hand rule: the first draw is the turn 1 start phase. Setup (p.63) has both players shuffle and offer a cut, leaving a fresh 54-card deck.
- End Phase step G (p.32): **each discard pile is shuffled back into its deck** (set hands aside first). So a reshuffle happens every turn, not only when a deck runs out; cards removed from the game are the only ones that stay out.
- When a deck runs out at any time, the owner shuffles their discard pile (jokers included) into a fresh deck and **offers it to the opponent to cut** (a real-world fairness step; we replace it with a server shuffle, see below).
- Flip (p.8): reveal the top card to the conflict. Each + or - adds one revealed card, to a maximum of four in all; + and - cancel, so a flip has one kind. With +, choose any one revealed card; with -, you must use the lowest (you choose among ties). Jokers override: a revealed Red Joker may be chosen even with -, the Black Joker must be chosen even with +, and Black wins if both show. "Reveal" shows a card to both players; "look at" is private and face down (Insight token, p.48: look at the top card and may discard it). Duel flips are simultaneous (p.10), and before a duel flip a player may discard a card of value 5 or lower for a + and a suit. Cheating Fate (p.9): one opportunity per flip; replace the flipped card with one from hand and the old card is discarded (it counts as neither a flip nor a discard). In an opposed duel the lowest total cheats first, the resister on a tie, then the opponent. A controlled model cheats from its controller's hand (p.43). The rules never say whether a cheat card is shown before it is played; it is public once in the conflict. a flipped Black Joker cannot be cheated until discarded; a chosen Red Joker stops the owner cheating their own flip until discarded. Cards in the conflict are discarded once the effect resolves; several cards discarded together are shown to everyone first.

### Decisions (user, 2026-11-18)
- The **server shuffles and deals**, and a player may draw their own hand (drawing is a player action, not automatic). No deck-less "record a flip" mode for now.
- The opponent's hand shows **as a number only**, never card backs.
- Cards and abilities that **manipulate the hand, the top of the deck or the discard pile** must be supported (see "Manipulation" below).

### Who sees what (the visibility rules the user set, made precise)
| Thing | The owner | The opponent | An observer |
| --- | --- | --- | --- |
| Deck contents and order | never | never | never |
| Deck size | yes | yes | yes |
| Hand contents | yes | **no** (count only) | **no** (neither hand) |
| Hand size | yes | yes | yes |
| Conflict (flipped cards) | yes | yes | yes |
| Discard pile (order too) | yes | yes | yes |
| Removed from game | yes | yes | yes |

Solo games (one user plays both sides) show that user both hands. In a tournament game that is also an observable game, the two players see their own hand and observers see neither.

### Manipulation (hand, deck and discard effects)
Card and ability text can look at, reorder, take or return cards. Rather than one endpoint per ability, give the server a small set of primitives that any effect is built from, each checked for legality and each with a fixed answer to "who is told":

| Primitive | What it does | Who sees the result |
| --- | --- | --- |
| Peek top N | Look at the top N cards of my deck (Insight token is N=1) | me only; others see "looked at N cards" |
| Reorder top N | Put the peeked cards back in a chosen order | nobody; the order stays hidden |
| Discard from top N | Discard some peeked cards, rest go back | the discarded cards are revealed (they hit the public discard pile); the rest stay hidden |
| Put on top / bottom of deck | Move a hand or discard card to the top or bottom of my deck | the opponent sees a count change only (from a hand) or the card leaving the discard pile (from the pile) |
| Draw from discard | Take a named card from my discard pile into my hand | public: the pile is face up, so everyone sees which card left |
| Discard from hand | Move a chosen hand card to the discard pile | public (a card entering the pile is shown) |
| Take from deck (search) | Look through my deck for a card, take it, then shuffle | the taken card is private if it goes to hand; the search and the shuffle are logged without contents |
| Shuffle deck | Server shuffle | logged, no contents |
| Remove from game | Set a card aside face up | public |
| Draw N | Top N cards to my hand, with the normal hand maximum | me only; the opponent sees the count |

- **The effect belongs to the card's controller:** a controlled model's effect acts on its controller's deck and hand (p.43). Effects that target the **opponent's** deck or hand (rare) are separate and need the owner of that deck to be the one shown the result, so each primitive takes a "whose deck" argument that defaults to my own and is policy-checked.
- **Discard-pile order is fixed and public** ("may not be reordered"), so a draw-back of a specific card is allowed but the pile cannot be rearranged. The primitive picks by card, not by position.
- **Deck order is private and may only be changed by the owner's own effect.** The server keeps the order; the client is told only what the effect lets it see (the peeked cards, then nothing).
- **Audit:** every primitive writes a log row (who, which primitive, how many cards, public cards named). This is the replay's source and the answer when two players disagree. The log never contains a hidden card, only the count.
- **UI:** an "Effects" menu on the Fate panel with these primitives as named actions plus a free-form pair (peek N, then choose what to do with each peeked card: top, bottom, discard, hand). Common card effects get shortcuts later.

### The security shape (the part that must not be done the obvious way)
1. **The server owns the deck.** Shuffles, draws and flips are done in PHP with `random_int`-based shuffling inside a `DB::transaction` that locks the game row (the concurrency pattern in CLAUDE.md). A client can never choose or peek at a card. This replaces "offer to cut" with something verifiable, and means a flip cannot be re-rolled by refreshing.
2. **Hands are never put in shared props or broadcasts.** The existing live channel `game.{uuid}` admits every participant, so anything broadcast on it reaches the opponent. Broadcast only content-free or public facts there (hand *size*, deck size, the public conflict and discard changes). A player gets their own hand from an authenticated request (`GET /games/{game}/fate/hand`, policy-checked to their slot) or on a private user channel (`App.Models.User.{id}` already exists in `routes/channels.php`). Tests assert the opponent's and an observer's payloads contain no hand card.
3. **Observers poll.** Today observers cannot join the presence channel (it admits participants only) and the board re-fetches every 8 s. They would see flips and discards on the same cadence. Faster observer updates need either a public read-only channel for the public state only or a shorter poll; decide with the tournament views in mind.
4. **No client-authoritative zones.** Moving a card between zones is a server action with a legality check (a card must be in the zone it claims to leave). Anything else is a way to forge a flip.

### Data model
`game_fate_cards`: `game_id`, `game_player_id`, `card` (0-53 or a small enum), `zone` (deck, hand, conflict, discard, removed), `position` (order within the zone, discard order is public), `joker_suit` (set when a Red Joker is chosen), `flip_id` (which flip put it in the conflict). One row per card, 54 per player, created when the game starts. Rows move between zones; nothing is deleted. Cast `zone` to an enum, index `(game_id, game_player_id, zone, position)`.

### Endpoints (all FormRequest + policy, throttled)
- `POST fate/draw` (n, capped by hand room; a surge or model effect draws into the controller's hand), `POST fate/discard` (card ids, from hand), `POST fate/flip` (modifiers, damage track, optional label), `POST fate/cheat` (conflict card id plus hand card id), `POST fate/resolve` (clears the conflict to the discard pile), `POST fate/shuffle` (discard back into the deck: automatic at the end phase and when a deck runs out), `POST fate/remove` (set a discard-pile card aside).
- Start-phase helper: `fate/refill` draws up to 7 after the player discards.
- A stale concurrent request redirects cleanly (the CLAUDE.md lock-and-recheck pattern).

### UI
- A **Fate panel** on the play screen and on the tabletop's side column: a deck count, my hand as cards I can tap to discard or cheat with, the opponent's hand as a count of card backs, the conflict in the middle, and both discard piles (face up, newest on top).
- A **Flip** button with +/- steppers and the duel option; the result lands in the conflict for everyone. A cheat prompt appears for the owner only.
- Card art: no Fate card art is in the repo. Use an original rendering (value and a suit icon from the `M4E-Symbols` font), not Wyrd's card art, the same way the terrain textures are original.
- Log each flip into the game's existing history so a replay can show it.

### Phasing
1. Schema, models, server deck service, the draw, discard, flip, cheat and resolve actions, and the manipulation primitives (peek, reorder, put on top or bottom, draw from discard, search, remove), with the action log and tests (including the visibility assertions and that hidden order never leaves the server). No UI.
2. The Fate panel on the play screen (participants), realtime for public state.
3. Observer view (public state only) and the tournament hybrid.
4. Tabletop board integration (flip from a model's panel, the flip result shown beside the model).
5. Replay of flips.

### Open questions for the user
- (Answered) Server shuffle and deal, player draws their own hand; opponent's hand is a number only.
- Which manipulation effects come up most? The primitives cover the cases found so far; a list of the actual card and ability texts would let us add shortcuts and find any gap (for example effects that make the opponent discard or reveal).
- Should an observer see a flip result live or at the next poll? (Polling is 8 s today.)
- Are cheat-fate declines to be tracked ("declined" ends the chance for that flip)? The cheating order in a duel (lowest total first) suggests the panel should prompt in that order.
- The opening hand is not specified by the rules; should the first draw be automatic at the turn 1 start phase?
- Is an Insight token's "look at the top card, may discard it" worth a private peek action? It is the one case where a deck card is shown to its owner alone.

## 2. Status badges on the board (VASSAL's glow rims and token stacks)
The board's tokens (health, activated, status tokens, notes) live in the model panel only, so on a phone the table looks the same whether a model is activated, stunned or burning.

- Draw a small badge ring on each base: activated (dimmed or a tick), and up to three status tokens as colour chips with a count, using the token's own icon from the `GameText.tagToIconType` map.
- Pure presentation, no schema: `models[]` already carries `tokens`, `activated` and `health`. The cost is the render budget (see `docs/performance.md`): badges must be drawn from a memoised map so a drag does not rebuild them.
- Observers get them too since they are public tracker state.
- Phasing: 1 day, ship before the Fate deck so the board is readable while play is built.

## 3. Predefined strategy setups
VASSAL modules ship ready-made scenario setups. Strategies here have only free text (`strategies.setup`, `rules`, `scoring`), no structured placement, so this needs data first.

- Add `board_setup` JSON to `strategies`: the markers a strategy makes and where, written relative to the deployment (for example "one X marker within 6" of the centerline for each player", "four markers 6" from each edge"), plus a recommended deployment.
- An admin form (mirroring the marker/terrain admin) to author it, and a "Set up this strategy" button on the game board that places the markers through the existing marker API, honouring the CRG p.47 rule that a marker that cannot be made where indicated goes "as close as possible" (the nearest-legal-spot geometry already does this).
- It needs someone to author about ten to twenty setups from the strategy cards; not a coding task. Start with the current season's pool.
- Phasing: schema and admin first, the board button second.

## Suggested order
2 (badges, small, visible) -> 1 phases 1-2 (the real work) -> 3 (needs authored data, can run in parallel with 1) -> 1 phases 3-5.
