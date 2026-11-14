/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Organizer analytics: the Insights tab of a finished event and the page across every event the user organizes.
// Checked at desktop and phone width: the page must not scroll sideways, and the panels must show real numbers.
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const hscroll = (s) => s.page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

    for (const [label, vp] of [
        ['desktop', { width: 1280, height: 900 }],
        ['phone', { width: 390, height: 844 }],
    ]) {
        const dave = await L.session(browser, 'dave@example.test', vp);
        await L.step(`insights (${label}): the finished event shows turnout, scores, rounds and factions`, dave, async () => {
            await L.visit(dave, `/tournaments/${ids.t7}#insights`);
            await L.expectText(dave, /Turnout/);
            await L.expectText(dave, /How the games went/);
            await L.expectText(dave, /Round by round/);
            await L.expectText(dave, /Factions/);
            await L.shot(dave, `340-insights-${label}`);
            const t = await L.text(dave);
            // Seed: 4 contested games, scores 9-3, 7-5, 8-4, 6-6 -> average 6 VP per player, one tie.
            if (!/games played\s*4/i.test(t)) throw new Error(`games played is not 4: ${t.replace(/\s+/g, ' ').slice(0, 400)}`);
            if (!/Biggest win:\s*Alice 9-3 over Bob/.test(t.replace(/\s+/g, ' '))) throw new Error('biggest win line missing');
            if (!/Arcanists/.test(t)) throw new Error('faction rows missing');
            if ((await hscroll(dave)) > 1) throw new Error(`page scrolls sideways by ${await hscroll(dave)}px`);
        });
        await L.step(`analytics (${label}): the all-events page counts the organizer's events`, dave, async () => {
            await L.visit(dave, '/tournaments');
            await L.clickText(dave, /Analytics across your events/i, 'a');
            await L.expectText(dave, /Turnout by event/);
            await L.expectText(dave, /Faction results/);
            await L.expectText(dave, /Spring Showdown/);
            if ((await hscroll(dave)) > 1) throw new Error(`page scrolls sideways by ${await hscroll(dave)}px`);
            await L.shot(dave, `341-analytics-${label}`);
            await L.clickText(dave, /Last 12 months/i, 'a');
            await L.expectText(dave, /Time range|Turnout by event|Nothing to show yet/);
        });
    }

    const guest = await L.session(browser, null);
    await L.step('analytics: a guest is sent to sign in', guest, async () => {
        await L.visit(guest, '/tournaments/analytics');
        if (!guest.page.url().includes('/login')) throw new Error(`landed on ${guest.page.url()}`);
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
    for (const r of L.results) console.log(`${r.status} ${r.name}${r.detail ? '\n       ' + r.detail : ''}`);
    if (bad.length) process.exit(1);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
