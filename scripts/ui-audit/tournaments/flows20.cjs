/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Single-elimination top cut: start it from the Swiss standings, advance the bracket, see the stages named.
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const org = await L.session(browser, 'org@example.test');
    const guest = await L.session(browser, null);

    await L.step('cut: a finished Swiss event offers a top cut next to finalizing', org, async () => {
        await L.visit(org, `/tournaments/${ids.t9}`);
        await L.clickText(org, /^Rounds/i, '[role=tab]');
        await org.page.waitForSelector('[data-testid="top-cut"]', { timeout: 9000 });
        await L.expectText(org, /Finalize Standings/i);
        await L.expectText(org, /Start top cut/i);
        await L.shot(org, '200-top-cut-offer');
    });
    await L.step('cut: starting a top 4 asks first, then pairs 1v4 and 2v3 as Semifinals', org, async () => {
        await L.pickByTrigger(org, /Top 4|Top 2|Top 8/i, /Top 4 \(semifinals\)/i);
        await L.clickText(org, /^Start top cut$/i, 'button');
        await L.expectText(org, /Start a top 4\?/i);
        await L.clickText(org, /^Start top cut$/i, '[role=alertdialog] button, [role=dialog] button');
        await L.expectText(org, /Top cut paired/i);
        await L.expectText(org, /Semifinals/i);
        const rows = await org.page.evaluate(() => document.body.innerText);
        // seeds: C1 (10-2) C3 (8-4) C5 (6-6) then the losers; 1v4 and 2v3 by Swiss placing
        if (!/C1/.test(rows) || !/C5/.test(rows)) throw new Error('expected the seeded players in the semifinal pairings');
        await L.shot(org, '201-semifinals-paired');
    });
    await L.step('cut: the public page names the stage', guest, async () => {
        await L.visit(guest, `/tournaments/${ids.t9}/view`);
        await L.expectText(guest, /\bSF\b/);
        await L.shot(guest, '202-public-semifinal-tab');
    });

    await L.step('cut: finished semifinals offer Advance bracket in the guide and the Rounds tab', org, async () => {
        await L.visit(org, `/tournaments/${ids.t10}`);
        await L.expectText(org, /Semifinals are done/i);
        await L.shot(org, '203-advance-guide');
    });
    await L.step('cut: advancing pairs the winners as Finals', org, async () => {
        await L.clickText(org, /^Advance bracket$/i, 'button');
        await L.expectText(org, /Bracket advanced/i);
        await L.clickText(org, /^Rounds/i, '[role=tab]');
        await L.expectText(org, /Finals/);
        await L.shot(org, '204-finals-paired');
    });
    await L.step('cut: finalizing is not offered before the final is played', org, async () => {
        await L.visit(org, `/tournaments/${ids.t10}`);
        const finalize = await org.page.evaluate(() => /Finalize Standings/.test(document.body.innerText));
        if (finalize) throw new Error('finalize should not be offered before the final is played');
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
    if (bad.length) process.exit(1);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
