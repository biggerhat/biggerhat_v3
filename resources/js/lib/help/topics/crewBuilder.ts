/**
 * Help for the Crew Builder (/crew-builder): building, saving and sharing a crew list. About how the tool works, in the words on its
 * screens; hiring rules are in the rulebook. When the builder's screens or flow change, change this (docs/features/help.md).
 */
import type { HelpTopic } from '../types.ts';

const crewBuilder: HelpTopic = {
    slug: 'crew-builder',
    title: 'Crew Builder',
    summary:
        'Build a crew list for an encounter: choose a faction and master, hire models and upgrades within the soulstone budget, then save, share or export it.',
    route: 'tools.crew_builder.editor',
    sections: [
        {
            id: 'start',
            title: 'Getting started',
            intro: 'You can build a crew without an account. Sign in to save crews, keep a list of them and share them.',
            items: [
                {
                    title: 'Your crews',
                    body: 'Signed in, the builder opens on your crews, in two tabs: Active and Archived. Press New Crew (or Create your first crew) to start one, or Edit a crew to carry on. Each crew has a menu with Copy share link, Make public or private, Archive and Delete. Archive hides a crew without losing it; open the Archived tab to bring it back.',
                    image: {
                        src: 'crew-builder/my-crews.webp',
                        alt: 'The My Crews list: an Import button and a New Crew button at the top right, Active and Archived tabs, and a crew card called Demo Patrol with Edit and View buttons and a more-actions menu.',
                        callouts: [
                            'Import: paste a crew list to rebuild it.',
                            'New Crew: start a crew from scratch.',
                            'The Active and Archived tabs.',
                            "A crew's menu: copy the share link, make it public or private, archive it, or delete it.",
                        ],
                    },
                },
                {
                    title: 'Without an account',
                    body: 'Without signing in, the builder opens straight into the editor. You can build, copy and export a crew, but it is not saved, so sign in if you want to keep it.',
                },
                {
                    title: 'Choosing a faction and master',
                    body: 'A new crew starts with Choose Your Faction, then Choose Your Master. A master with more than one title lets you switch between them while you build. Go back with the arrow at the top left.',
                },
                {
                    title: 'Importing a crew',
                    body: 'Press Import and paste a crew list, either exported from BiggerHat or in the standard format (names such as "Lucas McCabe, Tomb Delver"). The crew is rebuilt for you to carry on with.',
                },
                {
                    title: "Other people's crews",
                    body: 'The Crew Builder page lists crews that people have made public, and you can filter by faction. Open one to see it, or use Copy to My Builds (when signed in) to make your own copy to change.',
                },
            ],
        },
        {
            id: 'building',
            title: 'Building a crew',
            intro: 'Once you have a master the editor shows your crew (what you have hired) and the hiring pool (who you can hire). On a wide screen they sit side by side; on a narrower screen they are two tabs, Crew and Hiring Pool.',
            items: [
                {
                    title: 'Name, size and budget',
                    body: 'Give the crew a name at the top. The encounter size is the soulstone budget for the game (it starts at 50): change it to match your encounter. The editor shows what you have spent and what is left. Tick "Allow over-hiring" if you want to add models beyond the budget.',
                },
                {
                    title: 'Your master and totem',
                    body: 'Your master is in the crew from the start, and so is its totem if it has one, at no cost.',
                },
                {
                    title: 'The Hiring Pool',
                    body: "The hiring pool lists who you can hire (it is the Hiring Pool tab on a narrow screen). Search by name, narrow the list with the Keyword, Versatile, OOK and All filters (each shows how many it holds), and sort by Station, Name or Cost. A model you cannot hire, such as one that would go over budget, shows the reason. The expand button opens every model's details at once.",
                    image: {
                        src: 'crew-builder/hiring-pool.webp',
                        alt: 'The hiring pool: a search box, the filter buttons Keyword, Versatile, OOK and All with the number of models in each, sort buttons, and a list of models each with an add button.',
                        callouts: [
                            'Search models by name.',
                            'The filters: Keyword, Versatile, OOK and All, each with how many models it holds.',
                            'Sort by Station, Name or Cost.',
                            'The add button hires a model.',
                        ],
                    },
                },
                {
                    title: 'Hiring and removing',
                    body: "Use the add button on a model to hire it, and the remove button in your crew to let it go. The cost shown is what the model costs in your crew, so a model outside your master's keywords shows a higher cost.",
                },
                {
                    title: 'Miniatures and sculpts',
                    body: 'Where a model has more than one miniature or sculpt, choose yours from the Select miniature and Select sculpt lists; its card image changes to match.',
                },
                {
                    title: 'Crew upgrades',
                    body: "A master's crew upgrades are listed with the crew. Tap one to make it the active upgrade, or tap it again to turn it off.",
                },
                {
                    title: 'Custom cards',
                    body: 'Use "Add custom card to crew" to put a card you made in the Custom Card Creator into the crew.',
                },
                {
                    title: 'Trigger counts',
                    body: 'Trigger Counts totals the crow, mask, ram, tome and soulstone triggers across the crew, so you can see what it leans on.',
                },
                {
                    title: 'Notes',
                    body: 'Use Notes to write a description for the crew, such as how it plays.',
                },
            ],
        },
        {
            id: 'saving',
            title: 'Saving and sharing',
            intro: 'A signed-in crew saves itself as you build it.',
            items: [
                {
                    title: 'Autosave',
                    body: 'While you are signed in, changes are saved as you go. "Saved" with the time appears in the toolbar, and a message shows there if a save fails.',
                },
                {
                    title: 'Public and private',
                    body: 'A crew is private until you make it public with the Public / Private button. Make it public to get a share link, and use the link button to copy it. Anyone with the link can see the crew.',
                },
                {
                    title: 'Exporting',
                    body: 'The export menu has Copy as Text, Copy as Markdown (for forums and Discord), Export PDF (with References) for a printable list that includes the tokens, markers and upgrades your crew uses, and Quick Reference Sheet, a one-page list that needs the crew to be saved first.',
                    image: {
                        src: 'crew-builder/toolbar.webp',
                        alt: 'The toolbar at the top of the crew editor with the Export menu open, listing Copy as Text, Copy as Markdown, Export PDF (with References) and Quick Reference Sheet.',
                        callouts: [
                            'The crew name.',
                            'The encounter size: your soulstone budget.',
                            'Allow over-hiring.',
                            'The Export menu.',
                            'Copy as Text, the first of four ways to export.',
                        ],
                    },
                },
                {
                    title: 'Deleting',
                    body: 'Delete a crew from its menu. You are asked to confirm, and it cannot be undone; archive it instead if you might want it back.',
                },
            ],
        },
        {
            id: 'games',
            title: 'Using a crew in a game',
            intro: 'The Crew Builder and the Game Tracker work together.',
            items: [
                {
                    title: 'From a game',
                    body: 'On a game\'s crew step, choose a saved crew, or press Create New Crew. The builder then shows a bar with "Use this crew in game": it saves your crew, puts it in the game and takes you back.',
                },
                {
                    title: 'References in play',
                    body: 'The tokens, markers, upgrades and summons your crew can use are collected from the models you hired. The Game Tracker lists them as references, so you can add a token or summon a model in a tap during the game.',
                },
            ],
        },
    ],
};

export default crewBuilder;
