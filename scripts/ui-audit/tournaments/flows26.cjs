/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Emergency reset: an organizer sends an in-progress round back to its pre-start state, then starts it again.
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const org = await L.session(browser, 'org@example.test', { width: 390, height: 844 }); // organizers mostly run events from a phone

    await L.step('reset: the round options menu offers Reset (not Delete) while the round is in progress', org, async () => {
        await L.visit(org, `/tournaments/${ids.t1}`);
        await L.clickText(org, /^Rounds/i, '[role=tab]');
        await org.page.waitForSelector('button[aria-label^="Options for"]', { timeout: 9000 });
        await org.page.click('button[aria-label^="Options for"]');
        await L.expectText(org, /Reset round/i);
        const hasDelete = await org.page.evaluate(() => /Delete round/i.test(document.body.innerText));
        if (hasDelete) throw new Error('Delete round must not be offered while the round is in progress');
        await L.shot(org, '260-round-options-menu');
        await org.page.keyboard.press('Escape');
    });
    await L.step('reset: score one game so there is something to throw away', org, async () => {
        await L.clickText(org, /^Enter score$/i, 'button');
        await L.expectText(org, /Game Score/i);
        await org.page.click('input[type=number]', { clickCount: 3 });
        await org.page.type('input[type=number]', '4');
        await org.page.keyboard.press('Enter');
        await org.page.waitForSelector('[role=dialog]', { hidden: true, timeout: 9000 });
        await L.expectText(org, /1 of 2 games scored/i);
    });
    await L.step('reset: confirming sends the round back to Paired with scores cleared', org, async () => {
        await org.page.click('button[aria-label^="Options for"]');
        await L.clickText(org, /Reset round/i, '[role=menuitem]');
        await L.expectText(org, /Reset Round 1\?/i);
        await L.shot(org, '261-reset-confirm');
        await L.clickText(org, /^Reset round$/i, 'button');
        await L.expectText(org, /Paired/);
        await L.expectText(org, /Start round/i);
        const scoreButtons = await org.page.evaluate(
            () => [...document.querySelectorAll('button')].filter((b) => /^(Enter|Edit) score$/i.test(b.innerText.trim())).length,
        );
        if (scoreButtons) throw new Error('score buttons should be gone once the round is back in setup');
        await L.shot(org, '262-after-reset');
    });
    await L.step('reset: the round can be started again', org, async () => {
        await L.clickText(org, /^Start round$/i, 'button');
        await L.expectText(org, /In Progress/);
        await L.expectText(org, /^Enter score$/im);
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
