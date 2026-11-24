/**
 * Help for the Malifaux collection (/collection): what you own, and how far along each model is. About how the page works, in the words on
 * its screens. (The Other Side has its own collection page.) When the page changes, change this (docs/features/help.md).
 */
import type { HelpTopic } from '../types.ts';

const collection: HelpTopic = {
    slug: 'collection',
    title: 'Collection',
    summary: 'Keep track of the Malifaux miniatures and packages you own, and mark each model as built, primed, painted or based.',
    route: 'collection.index',
    sections: [
        {
            id: 'start',
            title: 'Adding what you own',
            intro: 'You need an account. Your collection is private until you make it public (see Sharing).',
            items: [
                {
                    title: 'From a character',
                    body: 'Open a character and press Add to Collection. The button then reads In Collection; press it again to take it out. Add All Standard Sculpts adds every standard edition of that character, and on a faction page Add All adds every character you do not have yet (all of their standard sculpts). Each sculpt comes in as one unbuilt model.',
                },
                {
                    title: 'From a package',
                    body: 'Open a package and press Add to Collection. It is added to your packages, and one unbuilt model of each standard sculpt of each character in it is added to your collection. If the box holds two of a model, raise the quantity yourself (see Tracking each model). Pressing In Collection again removes the package only: the miniatures it added stay.',
                },
                {
                    title: 'Your collection page',
                    body: 'My Collection (under My Hat in the sidebar) shows what you have. A new collection opens with Start your collection and links to Browse characters and Browse packages. The top card shows how many characters you have out of all of them, and how many of your models are built and painted.',
                },
            ],
        },
        {
            id: 'models',
            title: 'Tracking each model',
            intro: 'Every model you own is tracked on its own, so three copies of one miniature can be at three different stages.',
            items: [
                {
                    title: 'Stages',
                    body: 'A model is Unbuilt, Built, Primed, Painted or Based (table ready), in that order. A painted model counts as built, and a based model counts as painted. Built and painted everywhere on the site (this page, your profile stats) are read from the stage.',
                },
                {
                    title: 'The dots on a row',
                    body: 'Each row is one miniature and shows one dot per model, coloured by stage: hollow for unbuilt, amber for built, blue for primed, violet for painted, green for based. With more than one model, the row also says "Built 2/3 · Painted 1/3". Point at the dots for a count by stage.',
                },
                {
                    title: 'Models',
                    body: 'Press Models on a row to open its copies. Each has a stage picker, a Note (optional) field for anything you want to remember, such as a colour scheme or who it is lent to, and a trash button. Add a model adds one more copy. Notes save when you leave the field or press Enter.',
                },
                {
                    title: 'All of them at once',
                    body: 'The hammer marks every model of that miniature built (press it again to set them all back to unbuilt) and the paintbrush marks every one painted (again, to go back to built). A button with an outline and no fill means some of the models are done and some are not. The minus and plus change how many you own: more adds unbuilt models, fewer removes the least finished ones first.',
                },
                {
                    title: 'Removing',
                    body: 'The trash button on a row removes the miniature and all its models and notes; you are asked first. Removing a single model asks first only when it has a note or is the last one, because the last model takes the miniature out of your collection.',
                },
            ],
        },
        {
            id: 'find',
            title: 'Finding and grouping',
            intro: 'The Collection tab lists your miniatures grouped by character, with the faction colour down the side.',
            items: [
                {
                    title: 'Search and filters',
                    body: 'Search collection looks at names and keywords, and All Factions narrows to one faction. The chips All, Unbuilt, Unpainted and Painted count miniatures: a miniature is Unbuilt or Unpainted while any of its models still is, and Painted only when every one is.',
                },
                {
                    title: 'Select',
                    body: 'Select lets you tick rows (or the box at the top for every row you can see). A bar at the bottom then offers Mark Built, Unbuilt, Mark Painted, Unpainted, Set stage... (every model of the chosen miniatures to one stage) and Remove. Press Done to leave select mode.',
                },
            ],
        },
        {
            id: 'tabs',
            title: 'The other tabs',
            intro: 'Packages, Factions, Keywords and Stats look at the same collection in different ways. The tab you are on is kept in the address, so a refresh or a shared link opens the same one.',
            items: [
                {
                    title: 'Packages',
                    body: 'Each package shows how much of it you have: "You have 3 of 5 models" with how many of those are built and painted. What is inside lists its characters against the number the package holds. The models you own of the same characters count, wherever they came from. Browse packages opens the package list.',
                },
                {
                    title: 'Factions',
                    body: 'One card per faction with the characters you have out of all of them, and your models, built and painted. Press a card to see that faction in your collection.',
                },
                {
                    title: 'Keywords',
                    body: 'Each keyword with how many of its characters you have, the most complete first. Keywords you have nothing of are hidden; Show N not started brings them back, and Search keywords finds any.',
                },
                {
                    title: 'Stats',
                    body: 'Models by stage shows all of your models in one bar. Ready to paint lists built and primed models, and Still in the box lists unbuilt ones; press a name to find it in your collection. Top Factions and Most Complete Keywords are below. A keyword with only one or two characters is left out of Most Complete Keywords when there are enough bigger ones.',
                },
            ],
        },
        {
            id: 'sharing',
            title: 'Sharing and playing',
            intro: 'Your collection is private until you choose otherwise.',
            items: [
                {
                    title: 'Private and Public',
                    body: 'The button at the top says Private or Public; press it to switch. Share copies a link to your collection, and works once the collection is public. Anyone with the link sees your miniatures, their stages and your packages, but never your notes.',
                },
                {
                    title: 'What can I play?',
                    body: 'What can I play? (button at the top, and in the sidebar) lists each master you own with the models you own that share one of its keywords, and how far that gets you toward a full crew. It counts every model you own, whatever its stage.',
                },
            ],
        },
    ],
};

export default collection;
