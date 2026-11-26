/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// The deployment step: either player picks the kind of deployment (standard, corner, flank, wedge) before a crew is placed, and the other
// player sees it. Run on its own (it uses the same seeded game as flows40). The websocket is off in the harness: the other player reloads.
(async () => {
    const browser = await L.launch();
    const carol = await L.session(browser, 'carol@example.test');
    const dave = await L.session(browser, 'dave@example.test');
    const game = L.ids.deploy_game;
    const board = async (s) => {
        await L.visit(s, `/games/${game}/board`);
        await s.page.waitForSelector('[data-testid=deploy-panel]');
    };
    const pressed = (s, t) => s.page.$eval(`[data-testid=deploy-type-${t}]`, (b) => b.getAttribute('aria-pressed') === 'true');

    await L.step('the attacker picks the deployment type on the board', carol, async () => {
        await board(carol);
        await carol.page.waitForSelector('[data-testid=deploy-type]');
        const before = await carol.page.$$eval(
            '[data-testid=deploy-type] button',
            (bs) => bs.filter((b) => b.getAttribute('aria-pressed') === 'true').length,
        );
        if (before !== 1) throw new Error(`exactly one deployment should be chosen to begin with, ${before} are`);
        const target = (await pressed(carol, 'wedge')) ? 'flank' : 'wedge';
        await carol.page.$eval(`[data-testid=deploy-type-${target}]`, (b) => {
            b.scrollIntoView({ block: 'center' });
            b.click();
        });
        await carol.page.waitForFunction(
            (t) => document.querySelector(`[data-testid=deploy-type-${t}]`)?.getAttribute('aria-pressed') === 'true',
            { timeout: 8000 },
            target,
        );
        carol.target = target;
    });

    await L.step('the other player sees the new deployment, and it is fixed once a crew is being placed', dave, async () => {
        await board(dave);
        await dave.page.waitForSelector('[data-testid=deploy-type]');
        if (!(await pressed(dave, carol.target))) throw new Error(`the defender should see ${carol.target} chosen`);
        // the defender keeps the crew together, then the attacker picks the group: the buttons go
        await dave.page.$eval('[data-testid=deploy-split-confirm]', (b) => {
            b.scrollIntoView({ block: 'center' });
            b.click();
        });
        await dave.page.waitForFunction(() => document.querySelector('[data-testid=deploy-panel]')?.dataset.phase !== 'split', { timeout: 8000 });
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
