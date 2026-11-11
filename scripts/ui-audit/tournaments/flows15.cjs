/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// A player's tournament history on their public stats page.
(async () => {
    const browser = await L.launch();
    const alice = await L.session(browser, 'alice@example.test');
    const guest = await L.session(browser, null);
    let statsPath = '';

    await L.step('history: Alice sees her finished events, placing and record', alice, async () => {
        await alice.page.goto(`${L.BASE}/my-stats`, { waitUntil: 'networkidle2' });
        statsPath = new URL(alice.page.url()).pathname;
        await L.expectText(alice, /Tournament history/i);
        await L.expectText(alice, /Spring Showdown/);
        const row = await alice.page.$eval('[data-testid="tournament-history"] a[href*="/tournaments/"]', (a) =>
            a.innerText.replace(/\s+/g, ' ').trim(),
        );
        if (!/^1ST \/ 5 .*Spring Showdown.* 2-0-0/i.test(row)) throw new Error(`unexpected event row: ${row}`);
        await L.shot(alice, '140-history');
    });
    await L.step('history: the card is public', guest, async () => {
        await L.visit(guest, statsPath);
        await L.expectText(guest, /Tournament history/i);
        await L.expectText(guest, /Spring Showdown/);
    });
    await L.step('history: someone who has played no finished event has no card', guest, async () => {
        const organizer = await L.session(browser, 'org@example.test');
        await organizer.page.goto(`${L.BASE}/my-stats`, { waitUntil: 'networkidle2' });
        await L.expectText(organizer, /Tournaments/i);
        if (await organizer.page.$('[data-testid="tournament-history"]'))
            throw new Error('the organizer has not played in a finished event; no history card expected');
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
