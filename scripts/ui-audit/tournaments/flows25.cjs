/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Leagues: an organizer starts a series, puts a finished event into it and the public leaderboard shows the points.
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const dave = await L.session(browser, 'dave@example.test'); // created the finished "Spring Showdown"
    const guest = await L.session(browser, null);

    await L.step('league: the tournament list links to leagues, which start empty', guest, async () => {
        await L.visit(guest, '/tournaments');
        await L.clickText(guest, /Browse or start a league/i, 'a');
        await L.expectText(guest, /No leagues yet/i);
        await L.shot(guest, '250-leagues-empty');
    });
    await L.step('league: an organizer creates one with the default points table', dave, async () => {
        await L.visit(dave, '/tournaments/series');
        await L.clickText(dave, /New league/i, 'a');
        await dave.page.waitForSelector('#s-name', { timeout: 9000 });
        await dave.page.type('#s-name', 'Winter Ladder');
        await L.shot(dave, '251-league-form');
        await L.clickText(dave, /^Create league$/i, 'button');
        await L.expectText(dave, /Winter Ladder/);
        await L.expectText(dave, /No finished events yet/i);
        await L.expectText(dave, /1st 10/);
    });
    await L.step('league: a finished event is put into it from its Settings tab', dave, async () => {
        await L.visit(dave, `/tournaments/${ids.t7}`);
        await L.clickText(dave, /^Settings$/i, '[role=tab]');
        await dave.page.waitForSelector('[data-testid="series-card"]', { timeout: 9000 });
        await L.pickByTrigger(dave, /Not in a league/i, /Winter Ladder/);
        await L.expectText(dave, /Added to the league/i);
        await L.shot(dave, '252-event-in-league');
    });
    await L.step('league: the public event page links back to the league', guest, async () => {
        await L.visit(guest, `/tournaments/${ids.t7}/view`);
        await guest.page.waitForSelector('[data-testid="series-link"]', { timeout: 9000 });
        await L.expectText(guest, /Part of the league Winter Ladder/i);
    });
    await L.step('league: the leaderboard turns finishing places into points', guest, async () => {
        await L.clickText(guest, /Winter Ladder/i, 'a');
        await guest.page.waitForSelector('[data-testid="series-standings"]', { timeout: 9000 });
        await L.expectText(guest, /Alice/);
        await L.expectText(guest, /Points: 1st 10/);
        const first = await guest.page.evaluate(() =>
            document.querySelector('[data-testid="series-standings"] tbody tr')?.innerText.replace(/\s+/g, ' '),
        );
        if (!first || !/^1 /.test(first) || !/10/.test(first)) throw new Error(`unexpected top row: ${first}`);
        await L.shot(guest, '253-leaderboard');
    });
    await L.step('league: only the creator sees Edit and Delete', guest, async () => {
        const guestEdit = await guest.page.evaluate(() => /Edit/.test(document.body.innerText) && !!document.querySelector('a[href*="/edit"]'));
        if (guestEdit) throw new Error('guests must not see the edit link');
        await L.visit(dave, '/tournaments/series');
        await L.clickText(dave, /Winter Ladder/i, 'a');
        await dave.page.waitForSelector('a[href*="/edit"]', { timeout: 9000 });
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
    if (bad.length) process.exit(1);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
