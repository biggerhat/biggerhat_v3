/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Scorekeepers: an organizer adds one; they enter results from the scoring desk and can do nothing else.
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const org = await L.session(browser, 'org@example.test');
    const carol = await L.session(browser, 'carol@example.test');

    await L.step('scorer: the organizer adds Carol as a scorekeeper from Settings', org, async () => {
        await L.visit(org, `/tournaments/${ids.t1}`);
        await L.clickText(org, /^Settings$/i, '[role=tab]');
        await org.page.waitForSelector('[data-testid="scorers-card"]', { timeout: 9000 });
        await org.page.type('input[aria-label="Search accounts to add as a scorekeeper"]', 'Carol');
        await org.page.waitForFunction(
            () => [...document.querySelectorAll('[data-testid="scorers-card"] button')].some((b) => /^Carol$/.test(b.innerText.trim())),
            {
                timeout: 9000,
            },
        );
        await L.shot(org, '190-scorers-search');
        await L.clickText(org, /^Carol$/i, '[data-testid="scorers-card"] button');
        await L.expectText(org, /Carol can now enter results/i);
    });
    await L.step('scorer: Carol sees a Scoring desk link on the event page and opens it', carol, async () => {
        await L.visit(carol, `/tournaments/${ids.t1}/view`);
        await L.clickText(carol, /Scoring desk/i, 'a');
        await L.expectText(carol, /\d+ of \d+ reported/i);
        await L.shot(carol, '191-scoring-desk');
    });
    await L.step('scorer: she enters a result with the score dialog', carol, async () => {
        await L.clickText(carol, /^Enter score$/i, 'button');
        await L.expectText(carol, /Game Score/i);
        await carol.page.click('input[type=number]', { clickCount: 3 });
        await carol.page.type('input[type=number]', '4');
        await carol.page.keyboard.press('Enter');
        await L.expectText(carol, /4 – 0|Edit score/i);
        await L.shot(carol, '192-scoring-desk-scored');
    });
    await L.step('scorer: the organizer pages stay closed to her', carol, async () => {
        const res = await carol.page.goto(`${L.BASE}/tournaments/${ids.t1}`, { waitUntil: 'domcontentloaded' });
        const status = res.status();
        carol.problems.length = 0; // the 403 is exactly what this step expects, so its console line is not a problem
        if (status !== 403) throw new Error(`manage page returned ${status}, expected 403`);
    });

    const phone = await L.session(browser, 'carol@example.test', { width: 390, height: 800 });
    await L.step('scorer: the desk fits at phone width', phone, async () => {
        await L.visit(phone, `/tournaments/${ids.t1}/score`);
        await L.expectText(phone, /reported/i);
        const overflow = await phone.page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        if (overflow > 1) throw new Error(`horizontal overflow of ${overflow}px`);
        await L.shot(phone, '193-scoring-desk-phone');
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
    if (bad.length) process.exit(1);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
