/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Score entry keyboard flow: the cursor lands in the first score box, and "Save & next game" walks a round without going back to the list.
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const org = await L.session(browser, 'org@example.test', { width: 390, height: 844 });
    const dialogTitle = (o) => o.page.evaluate(() => document.querySelector('[role=dialog]')?.innerText.split('\n').slice(0, 3).join(' | ') ?? '');
    const focusedIsScore = (o) => o.page.evaluate(() => document.activeElement?.hasAttribute('data-score-input') ?? false);

    await L.step('score flow: opening a game puts the cursor in the first score box', org, async () => {
        await L.visit(org, `/tournaments/${ids.t1}`);
        await L.clickText(org, /^Rounds/i, '[role=tab]');
        await L.clickText(org, /^Enter score$/i, 'button');
        await L.expectText(org, /Game Score/i);
        await L.sleep(300);
        if (!(await focusedIsScore(org))) throw new Error('first score input should have focus');
    });
    let first;
    await L.step('score flow: Save & next game keeps the dialog open on a different pairing', org, async () => {
        first = await dialogTitle(org);
        await org.page.keyboard.type('4');
        await org.page.click('[data-testid="save-next-game"]');
        await L.sleep(1200);
        const second = await dialogTitle(org);
        if (!second || second === first) throw new Error(`expected another game, got "${second}" after "${first}"`);
        if (!(await focusedIsScore(org))) throw new Error('the next game should also start in a score box');
        await L.shot(org, '270-next-game');
    });
    await L.step('score flow: the last unscored game has no "next" button and Enter saves it', org, async () => {
        const hasNext = await org.page.$('[data-testid="save-next-game"]');
        if (hasNext) throw new Error('no unscored game is left, so there should be no next button');
        await org.page.keyboard.type('3');
        await org.page.keyboard.press('Enter');
        await org.page.waitForSelector('[role=dialog]', { hidden: true, timeout: 9000 });
        await L.expectText(org, /2 of 2 games scored|All games scored|Edit score/i);
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
