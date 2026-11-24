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
                        alt: 'The New Encounter card on the Create Game page, with a game name box, a format list, a How will you play list, the encounter size, a Solo Mode switch and the Create Game button.',
                        callouts: [
                            'Game name (optional).',
                            'The format.',
                            'How you will play: in person, on the tabletop app, online or other.',
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
        {
            id: 'log',
            title: 'Logging a game played elsewhere',
            intro: 'Played on paper, in another app or at an event? Press Log a game (on the Game Tracker page, or on your stats page) and enter it afterwards. It counts in your stats like any other game.',
            items: [
                {
                    title: 'The quick log',
                    body: 'The top of the page is all you need: a name for the game if you want one (Name this game, just for you), the Day played, the Format and Encounter size, Who won? (I won, I lost or A tie), and for you and your opponent the Faction, Master and Victory points. The opponent is a name you type. Log game stays off until the result, both factions and both point totals are in. The master box searches the models as you type; if yours is not listed, what you typed is kept.',
                },
                {
                    title: 'The same details the tracker keeps',
                    body: 'Under the quick log are sections you can open and fill in: Strategy, deployment and schemes (and the scheme pool); Turn by turn (Add a turn, then strategy points, scheme points, the scheme played and whether it was scored, held or discarded, and the strategy bonus point); Crews (Start from a saved crew, or Add a model, and tick killed for the ones that died); and Notes, event and who can see it. All of them are optional. When you fill in the turns, the victory point totals are added up from them. If the points and the result disagree (a win with fewer points), the page says so: that is fine for a concession.',
                },
                {
                    title: 'Inviting your opponent',
                    body: 'If your opponent has a BiggerHat account, search for them under Link their BiggerHat account. They are sent a notification and a card on their stats page, Games waiting for you. Until they press It was me, the game is only in your stats; when they do, it counts in theirs too, from their side. Not me removes it from their list and adds nothing.',
                },
                {
                    title: 'Who can see it',
                    body: 'A logged game is private: only you, and a player you invited, can open it, and it counts only in your own stats. Turn on Anyone can see this game (under Notes, event and who can see it) and anyone with the link can open it and it counts on your public stats page too.',
                },
                {
                    title: 'Changing or deleting it',
                    body: 'The name you give a game is what it is called in your lists; left empty, it is shown as the two players. Open the game from your games list (it has a Logged label) and press Edit to change anything, or Delete to remove it from your stats. Only the person who logged it can do either. A logged game has no play screen or table, because the game was already played.',
                },
            ],
        },
        {
            id: 'stats',
            title: 'Your stats and analytics',
            intro: 'Your stats page (My Stats) shows how you do. Full analytics goes deeper, over every game you have finished: ones from the tracker, ones you logged, and your tournament rounds.',
            items: [
                {
                    title: 'The stats page',
                    body: 'My Stats counts the games you played in the tracker and the ones you logged. Games where you played both sides (solo games) count too: press Leave out solo games to take them out, and Include solo games to put them back. Full analytics opens the deeper page; Log a game is there too.',
                },
                {
                    title: 'Full analytics',
                    body: 'The top shows your games, record, win rate, average points and margin, and your current streak. Under it, in order: Over time; By faction, By master, Against each faction, and Attacker and defender; Matchups (faction against faction, and master against master); Head to head against each opponent; By strategy, deployment, encounter size, season, how it was played, event and where the game came from; Turn by turn; Schemes; Models; and a table of Games with the day, result, factions and points of each, linking to the game.',
                },
                {
                    title: 'Filters and sharing a view',
                    body: 'Press Filters to narrow everything to a date range, a result, a faction or master, an opponent, a strategy, a size, a season, how it was played or an event, and to choose where the games come from (Game Tracker, Logged by hand, Tournaments). Include solo games (playing both sides) is a switch there. The count beside the button says how many games are in view, and Clear filters resets them. The filters are in the address, so you can send someone the same view. CSV downloads the games in view as a spreadsheet.',
                },
                {
                    title: 'Reading the numbers',
                    body: 'A win rate counts a tie as not a win, and shows only once there are 5 games behind it; below that you see a dash and the record. Hover a win rate to see the range it likely sits in. Turn by turn, Schemes and Models use only the games that recorded turns, schemes or a crew, and say how many that was. Another player looking at your page sees your tracker games and tournament rounds, and the logged games you made public.',
                },
            ],
        },
        {
            id: 'reports',
            title: 'Battle reports',
            intro: 'Write up a game in the same editor the articles use: the story, the turning point, photos of the table. A report can be about a game from the tracker or one you logged, or stand on its own.',
            items: [
                {
                    title: 'Starting a report',
                    body: 'On a finished game, press Write a battle report: it is on the game summary and on a logged game, and the pen beside each game in your list does the same. Battle reports in the footer opens the list, where Write a report starts one with no game. Pick the game under The game it is about (your finished games, from the tracker or logged by hand, and rounds you played in finished tournaments that were not tracked), give it a Title, and write. A game has one report from you; it shows as Edit in your list once written.',
                },
                {
                    title: 'The editor',
                    body: 'Headings, bold, italic, underline, strike, highlight, lists, quotes, links, tables, alignment and the game symbols work as in articles, and the image button adds a photo (up to 5 MB). Links to other pages and the card embeds the articles have are left out of reports. Press Save report when you are done: you come back to the report, and Edit changes it.',
                },
                {
                    title: 'Who can read it',
                    body: 'A report starts private: only you can open it. Turn on Ask to make this report public and a moderator reads it first; it shows as Waiting for a moderator and you are told when they decide. Once approved it is listed on the Battle reports page and anyone with the link can read it. If it is not approved you see the reason on the report, and it stays yours: change it and save to ask again. Fixing typos or adding a few words keeps an approved report public; a new image or link, a new title or game, or a lot of new text sends it back to a moderator, and it is hidden until they approve it again, so what is public is what they read. If a moderator did not approve it, Ask for a second look on the report sends it back to them once with your reason. Once a moderator has approved three of your reports and turned none down, your reports go public as soon as you ask. If a public report by someone else should not be there, press Report this under it and say why. Turn the switch off at any time to take it back to private. Public reports are also listed on the stats page of the person who wrote them. If you delete the game, the report stays.',
                },
                {
                    title: 'The crew list and comments',
                    body: "Under The crew you played, pick one of your crew lists from the Crew Builder: readers see its master and models, not the crew's name or notes, and it follows the list if you change it. Once a report is public, signed-in readers can leave a comment and press Nice report. Let readers comment turns comments off; you can remove any comment under your report, and so can the moderators.",
                },
                {
                    title: 'The scoreboard',
                    body: "Above your text a report shows the players, factions, masters and points of the game it is about. Turn off Show the game's scoreboard above the report to show only your text. It needs a game picked.",
                },
            ],
        },
    ],
};

export default gameTracker;
