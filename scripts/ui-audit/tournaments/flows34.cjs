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
            await L.expectText(dave, /Strategies/);
            await L.expectText(dave, /Schemes/);
            await L.expectText(dave, /1 of 4 games/);
            await L.shot(dave, `340-insights-${label}`);
            if (process.env.SHOTS) await dave.page.screenshot({ path: `${process.env.SHOTS}/340-insights-${label}-full.png`, fullPage: true });
            const t = await L.text(dave);
            // Seed: 4 contested games, scores 9-3, 7-5, 8-4, 6-6 -> average 6 VP per player, one tie.
            if (!/games played\s*4/i.test(t)) throw new Error(`games played is not 4: ${t.replace(/\s+/g, ' ').slice(0, 400)}`);
            if (!/Biggest win:\s*Alice 9-3 over Bob/.test(t.replace(/\s+/g, ' '))) throw new Error('biggest win line missing');
            if (!/Arcanists/.test(t)) throw new Error('faction rows missing');
            if ((await hscroll(dave)) > 1) throw new Error(`page scrolls sideways by ${await hscroll(dave)}px`);
            const href = await dave.page.$eval('[data-testid=insights-csv]', (a) => a.href);
            const csv = await dave.page.evaluate(async (u) => (await fetch(u)).text(), href);
            if (!/Turnout[\s\S]*Rounds/.test(csv)) throw new Error(`insights CSV looks wrong: ${csv.slice(0, 200)}`);
        });
        await L.step(`analytics (${label}): the all-events page counts the organizer's events`, dave, async () => {
            await L.visit(dave, '/tournaments');
            await L.clickText(dave, /Analytics across your events/i, 'a');
            await L.expectText(dave, /Turnout by event/);
            await L.expectText(dave, /Faction results/);
            await L.expectText(dave, /Spring Showdown/);
            if ((await hscroll(dave)) > 1) throw new Error(`page scrolls sideways by ${await hscroll(dave)}px`);
            await L.shot(dave, `341-analytics-${label}`);
            const csv = await dave.page.evaluate(
                async (href) => (await fetch(href)).text(),
                await dave.page.$eval('[data-testid=analytics-csv]', (a) => a.href),
            );
            if (!/Summary[\s\S]*Events/.test(csv)) throw new Error(`analytics CSV looks wrong: ${csv.slice(0, 200)}`);
            await L.clickText(dave, /Last 12 months/i, 'a');
            await L.expectText(dave, /Time range|Turnout by event|Nothing to show yet/);
        });
    }

    const lg = await L.session(browser, 'dave@example.test', { width: 390, height: 844 });
    let leagueUuid;
    await L.step('league analytics: set up a league holding the finished event', lg, async () => {
        await L.visit(lg, '/tournaments/series');
        await L.clickText(lg, /New league/i, 'a');
        await lg.page.waitForSelector('#s-name', { timeout: 9000 });
        await lg.page.type('#s-name', 'Analytics League');
        await L.clickText(lg, /^Create league$/i, 'button');
        await L.expectText(lg, /No finished events yet/i);
        leagueUuid = lg.page.url().split('/series/')[1].split(/[/?#]/)[0];
        await L.visit(lg, `/tournaments/${ids.t7}`);
        await L.clickText(lg, /^Settings$/i, '[role=tab]');
        await lg.page.waitForSelector('[data-testid="series-card"]', { timeout: 9000 });
        await L.pickByTrigger(lg, /Not in a league/i, /Analytics League/);
        await L.expectText(lg, /Added to the league/i);
    });
    await L.step('league analytics: the creator reaches it from the league page, with a CSV', lg, async () => {
        await L.visit(lg, `/tournaments/series/${leagueUuid}`);
        await L.clickText(lg, /^\s*Analytics\s*$/i, 'a');
        await L.expectText(lg, /Turnout by event/);
        await L.expectText(lg, /Analytics League/);
        await L.expectText(lg, /Strategies/);
        await L.expectText(lg, /Schemes/);
        if ((await hscroll(lg)) > 1) throw new Error(`page scrolls sideways by ${await hscroll(lg)}px`);
        await L.shot(lg, '342-league-analytics-phone');
        const href = await lg.page.$eval('[data-testid=analytics-csv]', (a) => a.href);
        const csv = await lg.page.evaluate(async (u) => (await fetch(u)).text(), href);
        if (!/Summary[\s\S]*Schemes/.test(csv)) throw new Error(`league CSV looks wrong: ${csv.slice(0, 200)}`);
    });
    const other = await L.session(browser, 'alice@example.test');
    await L.step('league analytics: another signed-in user is refused', other, async () => {
        const res = await other.page.goto(`${L.BASE}/tournaments/series/${leagueUuid}/analytics`, { waitUntil: 'networkidle2' });
        if (res.status() !== 403) throw new Error(`expected 403, got ${res.status()}`);
        other.problems.length = 0; // the browser logs the 403 itself; that is the point of the step
    });

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
