/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// How a game is played: the Create Game form asks (in person / tabletop / online / other), a tabletop game offers a saved table,
// the game shows how it is played, and on the board the attacker is red and the defender blue.
(async () => {
    const browser = await L.launch();
    const hscroll = (s) => s.page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    /** The page's own props, as Inertia put them in the page. */
    const pageProps = (s) => s.page.evaluate(() => JSON.parse(document.querySelector('[data-page]').getAttribute('data-page')).props);

    for (const [label, vp] of [
        ['desktop', { width: 1280, height: 900 }],
        ['phone', { width: 390, height: 844 }],
    ]) {
        const carol = await L.session(browser, 'carol@example.test', vp);
        carol.page.on('dialog', (d) => d.accept().catch(() => {}));

        await L.step(`create (${label}): asks how it will be played, and a table only for the tabletop`, carol, async () => {
            await L.visit(carol, '/games/create');
            await carol.page.waitForSelector('[data-testid=play-mode]');
            if (await carol.page.$('[data-testid=play-table]')) throw new Error('the table picker showed before the tabletop was chosen');
            await L.pickByTrigger(carol, /In person/i, /On the tabletop app/i);
            await carol.page.waitForSelector('[data-testid=play-table]');
            await L.expectText(carol, /copy of this table/i);
            if ((await hscroll(carol)) > 1) throw new Error(`page scrolls sideways by ${await hscroll(carol)}px`);
            await L.shot(carol, `390-create-tabletop-${label}`);
            await L.pickByTrigger(carol, /On the tabletop app/i, /Online/i);
            await carol.page.waitForFunction(() => !document.querySelector('[data-testid=play-table]'));
        });
    }

    const solo = await L.session(browser, 'carol@example.test', { width: 1280, height: 900 });
    solo.page.on('dialog', (d) => d.accept().catch(() => {}));
    let uuid = '';
    await L.step('create: a solo tabletop game on an empty table records how it is played', solo, async () => {
        await L.visit(solo, '/games/create');
        await solo.page.waitForSelector('[data-testid=play-mode]');
        await L.pickByTrigger(solo, /In person/i, /On the tabletop app/i);
        await solo.page.waitForSelector('[data-testid=play-table]');
        await solo.page.evaluate(() => {
            const sw = document.querySelector('button[role=switch][aria-label="Solo mode"]');
            if (sw && sw.getAttribute('aria-checked') !== 'true') sw.click();
        });
        await L.clickText(solo, /^Create Game$/i, 'button');
        await solo.page.waitForFunction(() => /\/games\/[0-9a-f-]{36}$/.test(location.pathname), { timeout: 12000 });
        uuid = solo.page.url().split('/games/')[1];
        await L.expectText(solo, /Tabletop/);
    });

    await L.step('board: the attacker is red and the defender blue', solo, async () => {
        await L.visit(solo, `/games/${uuid}/board`);
        await solo.page.waitForSelector('[data-testid=deployment-zones]');
        const roles = (await pageProps(solo)).players.map((p) => p.role);
        if (!roles.includes('attacker') || !roles.includes('defender')) throw new Error(`solo roles were ${JSON.stringify(roles)}`);
        // zone a belongs to slot 1, zone b to slot 2; read the fill each zone is drawn with
        const fills = await solo.page.evaluate(() =>
            [...document.querySelectorAll('[data-testid=deployment-zones] [fill]')]
                .map((e) => e.getAttribute('fill'))
                .filter((f) => f && f.startsWith('hsl(')),
        );
        const want = (role) => (role === 'attacker' ? 'hsl(5 70% 48%)' : 'hsl(215 70% 50%)');
        if (fills[0] !== want(roles[0]) || fills[fills.length - 1] !== want(roles[1])) {
            throw new Error(`roles ${JSON.stringify(roles)} but the zones were drawn ${JSON.stringify(fills)}`);
        }
        await L.shot(solo, '390-board-roles');
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
