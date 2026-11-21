/**
 * Help for the Game Tracker (/games): creating a game, setting it up, and playing it. About how the tool works, in the words on its screens;
 * the rules of the game are in the rulebook. When the tracker's screens or flow change, change this (docs/features/help.md).
 */
import type { HelpTopic } from '../types.ts';

const gameTracker: HelpTopic = {
    slug: 'game-tracker',
    title: 'Game Tracker',
    summary: "Track a game as you play it: each crew's health and tokens, soulstones, turns, schemes and victory points, live for both players.",
    route: 'games.index',
    sections: [
        {
            id: 'start',
            title: 'Starting a game',
            intro: 'Everything begins at Create Game. After that the game walks you through setup, then play.',
            items: [
                {
                    title: 'How will you play?',
                    body: 'Choose In person, On the tabletop app, Online (not the tabletop app) or Other. It is a label that shows in your games list and on the game, and it is remembered for next time. On the tabletop app you also choose a Table: Empty table, or one of your saved tables. The game starts with a copy of that table, so changing or deleting the saved table later does not change a game. The tabletop choices appear only if you have the tabletop.',
                },
                {
                    title: 'Creating a game',
                    body: 'Press Create Game and fill in the New Encounter card: a name if you like (League Round 3, Casual Tuesday), the format, how you will play, the encounter size in soulstones, and the season. A strategy, deployment and scheme pool are generated for you when you create it.',
                    image: {
                        src: 'game-tracker/create.webp',
                        alt: 'The New Encounter card on the Create Game page, with a game name box, a format list, the encounter size, a Solo Mode switch and the Create Game button.',
                        callouts: [
                            'Game name (optional).',
                            'The format.',
                            'The encounter size, in soulstones.',
                            'The Solo Mode switch.',
                            'Create Game.',
                        ],
                    },
                },
                {
                    title: 'Playing against someone',
                    body: 'Share the join link from the Invite Opponent screen. Your opponent joins from it, and Attacker and Defender are assigned at random when they do. They wait on "Waiting for the host to start the game" until you continue.',
                },
                {
                    title: 'Solo Mode',
                    body: 'Turn on Solo Mode to track both players yourself. A scenario is generated and you control both sides, with no opponent needed.',
                },
                {
                    title: 'Bonanza Brawl',
                    body: 'Pick Bonanza Brawl as the format for the solo loot-deck game. You choose one model, go straight to play, and track your own health, loot and victory points; there is no strategy, scheme pool or deployment. Its loot deck has its own help on the screen.',
                },
                {
                    title: 'Your games list',
                    body: 'The Games page lists your games. Open one to carry on where you were: setup picks up at the step you reached, and play shows the screen as it stands. The game is saved on the server as you go.',
                },
            ],
        },
        {
            id: 'setup',
            title: 'Setting up',
            intro: 'Setup goes through four steps: faction, master, crew and scheme, with a fifth, Deploy, between crew and scheme in a game played on the tabletop app. Each player does their own.',
            items: [
                {
                    title: 'Faction and master',
                    body: 'Choose your faction, then your master. Confirm each choice from the bar at the bottom of the screen (on a phone it floats above the tab bar).',
                },
                {
                    title: 'Choosing a crew',
                    body: 'Pick one of your saved crews for that faction and master, or press Create New Crew to build one in the Crew Builder. A bar there says "Use this crew in game", which sends the crew back here. Crews that are over budget are marked.',
                },
                {
                    title: 'Tracking points only',
                    body: 'Playing from a paper list? Skip the crew: you track victory points, schemes and turns only, with no model health or tokens. You can still Summon a model later if you want one on screen.',
                },
                {
                    title: 'Deploying on the tabletop',
                    body: 'In a game you chose to play On the tabletop app, a Deploy step comes after the crews, because the rulebook deploys before schemes are chosen. The game shows "Set up the table" and a button that opens the table. Deployment itself happens there: see Deploying a game in the Tabletop help. If you are playing at a real table, choose In person and there is no Deploy step.',
                },
                {
                    title: 'Choosing a scheme',
                    body: 'On Select Your Scheme (after deployment, in a tabletop game), choose one scheme from the pool for Turn 1. Some schemes ask for more: a model from your crew, a target marker, or a terrain note. What you choose stays hidden from your opponent.',
                },
                {
                    title: 'Starting play',
                    body: 'When setup is finished the game moves to play. Both screens update by themselves.',
                },
            ],
        },
        {
            id: 'play',
            title: 'The play screen',
            intro: 'The play screen is where the game happens. What is on it depends on the width of your screen.',
            items: [
                {
                    title: 'Tabs',
                    body: "On a wide screen everything is on one page: the game panel on the left, with your crew and your opponent's crew beside it. On a tablet there are two tabs, Game and Crews. On a phone there are three: Game, My Crew and your opponent's name. The game panel holds the scenario, scoring and schemes; the crews hold the models.",
                    image: {
                        src: 'game-tracker/play-screen.webp',
                        alt: "The play screen on a wide display: the game panel on the left with the turn, victory points and scoring, your crew in the middle and your opponent's crew on the right.",
                        callouts: [
                            'Open board: the tabletop for this game.',
                            'The Fate deck.',
                            'Game settings.',
                            'Your soulstone pool, with minus and plus.',
                            'Submit Turn.',
                        ],
                    },
                },
                {
                    title: 'The scenario',
                    body: 'The strategy, deployment and scheme pool you are playing are listed on the Game tab. Tap one to open its card. Fold the panel away with Collapse game panel when you want the room.',
                },
                {
                    title: 'Soulstones',
                    body: "Your soulstone pool is next to your crew's heading. Use the minus and plus beside it to change it. The game can also add a soulstone for you when a model is killed (see Game settings).",
                },
                {
                    title: 'Staying in step',
                    body: 'Changes you make appear for your opponent straight away, and theirs for you. If the connection drops, a Reconnecting badge appears and the screen keeps itself refreshed until it is back.',
                },
                {
                    title: 'Watching and the board',
                    body: 'Open the Tabletop board for the game from the play screen to see and move the models on a 36 inch table, or open the Fate deck there. Both use the same game, so health and activation are shared. Anyone you invite to watch sees the game without being able to change it.',
                },
            ],
        },
        {
            id: 'crew',
            title: 'Your crew',
            intro: 'Each model has a row. Everything below is done from it.',
            items: [
                {
                    title: 'Activation',
                    body: "Tap the circle at the left of a model's row to mark it activated; tap again to undo.",
                    image: {
                        src: 'game-tracker/crew-row.webp',
                        alt: "One model's row on the play screen: an activation circle, three buttons for upgrades, tokens and replace, the model's name with a card button, a row of health pips, and minus and plus buttons around its health.",
                        callouts: [
                            'The activation circle.',
                            'Upgrades.',
                            'Tokens.',
                            'Replace.',
                            "The card: opens the model's card.",
                            'Minus: lower its health.',
                            'Plus: raise its health.',
                        ],
                    },
                },
                {
                    title: 'Health',
                    body: 'Use the minus and plus beside the health to change it, or tap a pip to set it. Taking a model to zero health kills it: it is marked killed, the game can add a soulstone for its crew, and a model that replaces itself on death offers its replacements. A killed model shows Revive.',
                },
                {
                    title: 'Tokens',
                    body: 'Press Tokens on a row to open Manage Tokens: the tokens it has now, and a list of reference tokens from your crew to add. Filter the list by typing. Each token on the row has a small cross to remove it, and tapping its name shows what it does.',
                },
                {
                    title: 'Cards',
                    body: 'Tap a model\'s name or its eye to open its card in the row. "View all crew cards" opens every card at once.',
                },
                {
                    title: 'Upgrades and injuries',
                    body: "Use Upgrades on a row to open the upgrades it has and the reference upgrades from your crew; use Attach Injury for an injury (Campaign games). What is attached shows under the row. The Tabletop board offers the same Upgrades from a model's row.",
                },
                {
                    title: 'Replace',
                    body: "Use Replace to swap a model for another character. The row is updated in place: its position, tokens and upgrades are kept, and its health is kept up to the new model's maximum. Search for the new character in the dialog.",
                },
                {
                    title: 'Summon',
                    body: 'Use Summon to add a model mid-game: choose from the reference characters your crew can summon, or search any character. It is added to your crew and, on the tabletop board, waits for you to place it.',
                },
                {
                    title: 'Markers and references',
                    body: 'The References panel lists the markers, tokens, upgrades and summons that your crew can bring into play, taken from your saved crew. A crew that was skipped, or Bonanza, builds its references from the models on the table.',
                },
            ],
        },
        {
            id: 'turns',
            title: 'Turns and scoring',
            intro: 'The turn bar and the score dialogs keep the game moving and the points straight.',
            items: [
                {
                    title: 'The turn bar',
                    body: 'Shows the turn, who has initiative, who is to activate and how many models each side has left to activate, with Pass. When everyone has activated it offers "Everyone has activated: end phase". In tournament rounds a round clock shows the time left and calls the end.',
                },
                {
                    title: 'Scoring a turn',
                    body: 'Record victory points as they happen. Under Victory points you will see Strategy points and Scheme points for the turn. When the turn is over, press Submit Turn (it shows your points) to open Confirm Turn: score this turn, check your current scheme, and choose your next one. Once you submit, the turn advances and cannot be undone from here.',
                },
                {
                    title: 'Schemes through the game',
                    body: 'Keep your current scheme (it stays hidden from your opponent), or discard it and switch to another. If a scheme\'s once-per-game bonus point was scored, tick "This was the bonus point" so it cannot be scored again. Scheme History lists what you scored and when.',
                },
                {
                    title: "Your opponent's possible schemes",
                    body: "Open Opponent's Possible Schemes to keep track of which of the pool your opponent could be playing and which they cannot.",
                },
                {
                    title: 'Fixing a mistake',
                    body: 'If you mis-clicked a score, press the pencil beside your total (it says Edit and the turn number): Correct Turn Score fixes the strategy and scheme points and updates the total for you.',
                },
            ],
        },
        {
            id: 'finish',
            title: 'Game settings and finishing',
            intro: 'Open Game Settings with the gear beside the turn number.',
            items: [
                {
                    title: 'Watching live',
                    body: 'Turn on Spectate Mode to allow others to watch live. Share the Spectate Link (it has a QR code for people at the table). Watchers see the game but cannot change anything.',
                    image: {
                        src: 'game-tracker/game-settings.webp',
                        alt: 'The Game Settings dialog: Spectate Mode with its QR code and link, the Auto Soulstone on Kill switch, and the Mark Game Complete and Abandon Game choices.',
                        callouts: ['Spectate Mode: let others watch live.', 'Auto Soulstone on Kill.', 'Mark Game Complete.', 'Abandon Game.'],
                    },
                },
                {
                    title: 'Auto Soulstone on Kill',
                    body: 'When this is on, killing a model gives its crew a soulstone for you. Turn it off if you would rather track soulstones by hand.',
                },
                {
                    title: 'Marking the game complete',
                    body: 'Mark Game Complete finalises the scores and ends the game, and the summary shows how it went. Abandon Game ends it without scoring.',
                },
            ],
        },
    ],
};

export default gameTracker;
