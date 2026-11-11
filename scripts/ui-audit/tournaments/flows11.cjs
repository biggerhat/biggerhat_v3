/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// The Rounds tab now lives in its own component; the page's next-step guide drives it through an exposed API.
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const org = await L.session(browser, 'org@example.test');

    await L.step('guide -> Rounds tab: "Set scenario" switches tabs and opens the scenario editor', org, async () => {
        await L.visit(org, `/tournaments/${ids.t5}`);
        await L.expectText(org, /Set up Round 1/i);
        await L.clickText(org, /^Set scenario$/i, 'button');
        await L.expectText(org, /Auto-saves as you change fields/i);
        await L.shot(org, '100-guide-scenario');
    });
    await L.step('rounds tab: Random fills the scenario and the guide moves on', org, async () => {
        await L.clickText(org, /^Random$/i, 'button');
        await L.expectText(org, /Saved/i);
        await L.clickText(org, /^Done$/i, 'button');
        await L.expectText(org, /Finish pairing Round 1/i);
    });
    await L.step('guide: an unpaired player blocks the start and the guide says so; giving them a bye clears it', org, async () => {
        await L.expectText(org, /Finish pairing Round 1/i);
        await L.expectText(org, /unpaired: Eve/i);
        await L.pickByTrigger(org, /Select player/i, /^Eve$/);
        await L.clickText(org, /^Pair$/i, 'button');
        await L.expectText(org, /Round 1 is paired/i);
    });
    await L.step('guide -> Rounds tab: "Start Round 1" starts the round', org, async () => {
        await L.clickText(org, /^Start Round 1$/i, 'button');
        await L.expectText(org, /Round 1 is in progress/i);
        await L.expectText(org, /0 of 2 games reported/i);
    });
    await L.step('rounds tab: the forfeit dialog opens from a game menu', org, async () => {
        await L.clickText(org, /^View games$/i, 'button');
        await org.page.click('button[aria-label^="More actions for table"]');
        await L.clickText(org, /Record a forfeit/i, '[role=menuitem]');
        await L.expectText(org, /Which player conceded\?/i);
        await L.clickText(org, /^Cancel$/i, 'button');
        await org.page.waitForSelector('[role=dialog]', { hidden: true, timeout: 9000 });
        const pe = await org.page.evaluate(() => getComputedStyle(document.body).pointerEvents);
        if (pe === 'none') throw new Error('the page is left unclickable after closing the forfeit dialog (body pointer-events: none)');
    });
    await L.step('rounds tab: scoring both games unlocks End Round, and the guide ends the round', org, async () => {
        for (let i = 0; i < 2; i++) {
            await org.page.waitForSelector('[role=dialog]', { hidden: true, timeout: 9000 });
            await L.clickText(org, /^Enter score$/i, 'button');
            await L.expectText(org, /Game Score/i);
            await org.page.click('input[type=number]', { clickCount: 3 });
            await org.page.type('input[type=number]', String(3 + i));
            await org.page.keyboard.press('Enter');
            await org.page.waitForSelector('[role=dialog]', { hidden: true, timeout: 9000 });
            await L.expectText(org, i === 0 ? /1 of 2 games still need a result/i : /all scores are in/i);
        }
        await L.clickText(org, /^End Round 1$/i, 'button');
        await L.expectText(org, /Round 1 is complete|All rounds|Add Round 2/i);
        await L.shot(org, '101-round-ended');
    });
    await L.step('rounds tab: Add Round 2 from the guide creates the next round', org, async () => {
        await L.clickText(org, /^Add Round 2$/i, 'button');
        await org.page.waitForFunction(() => [...document.querySelectorAll('h2')].filter((h) => /^Round \d/.test(h.innerText.trim())).length >= 2, {
            timeout: 9000,
        });
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
