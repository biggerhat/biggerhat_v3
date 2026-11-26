/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Board feedback of 2026-11 (round 3):
//  - Load table has a Random table: a table number, Roll again, a thumbnail, and Use this table puts it on the board;
//  - sight guidelines aimed at something off the visible part of the table get an arrow at the edge of the view and a Show it button.
(async () => {
    const browser = await L.launch();
    const s = await L.session(browser, 'alice@example.test', { width: 1280, height: 900 });
    const DEMO = [
        { id: 'a1', sp: 5, sz: 3, name: 'Master', side: 'a', baseMm: 50, x: 18, y: 4 },
        { id: 'b1', sp: 5, sz: 3, name: 'Master', side: 'b', baseMm: 50, x: 18, y: 32 },
    ];
    const openSandbox = async () => {
        await L.visit(s, '/tools/tabletop');
        await s.page.evaluate((tokens) => {
            let saved = {};
            try {
                saved = JSON.parse(localStorage.getItem('tabletop-spike-v2')) || {};
            } catch {
                // start from nothing
            }
            localStorage.setItem('tabletop-spike-v2', JSON.stringify({ ...saved, tokens }));
        }, DEMO);
        await L.visit(s, '/tools/tabletop');
        await s.page.waitForSelector('[data-testid=tabletop-board] svg');
    };
    const hit = (sel) =>
        s.page.$eval(sel, (b) => {
            b.scrollIntoView({ block: 'center' });
            b.click();
        });

    await L.step('random table: roll, pick a number, and put it on the board', s, async () => {
        await openSandbox();
        await hit('[data-testid=mode-terrain]');
        await hit('[data-testid=terrain-clear]');
        await s.page.waitForFunction(() => /0 pieces/.test(document.querySelector('[data-testid=terrain-stats]').innerText), { timeout: 5000 });
        await hit('[data-testid=table-load]');
        await s.page.waitForSelector('[data-testid=random-table] svg', { timeout: 5000 });
        const seedOf = () => s.page.$eval('[data-testid=random-seed] , input[data-testid=random-seed]', (i) => i.value);
        const first = await seedOf();
        await s.page.$eval('[data-testid=random-roll]', (b) => b.click());
        await L.sleep(300);
        const second = await seedOf();
        if (first === second) throw new Error(`Roll again should change the table number (${first})`);
        // a typed number gives the same table every time
        await s.page.$eval('input[data-testid=random-seed]', (i) => {
            i.focus();
            i.select();
        });
        await s.page.keyboard.type('4242');
        await L.sleep(300);
        if ((await seedOf()) !== '4242') throw new Error(`the typed table number should stick, saw ${await seedOf()}`);
        const summary = await s.page.$eval('[data-testid=random-summary]', (e) => e.innerText);
        if (!/pieces/.test(summary)) throw new Error(`the summary should count the pieces: ${summary}`);
        await L.shot(s, '490-random-table');
        await s.page.$eval('[data-testid=random-use]', (b) => b.click());
        await s.page.waitForFunction(
            () => {
                const m = /(\d+) pieces/.exec(document.querySelector('[data-testid=terrain-stats]').innerText);
                return m && Number(m[1]) >= 8;
            },
            { timeout: 5000 },
        );
        await s.page.waitForFunction(() => !document.querySelector('[data-testid=load-dialog]') && document.body.style.pointerEvents !== 'none', {
            timeout: 5000,
        });
    });

    await L.step('sight guidelines aimed off the visible table: an arrow points to it and Show it brings it into view', s, async () => {
        await openSandbox();
        await s.page.$eval('[data-testid=tabletop-board]', (e) => e.scrollIntoView());
        const a = await s.page.$eval('[data-testid=token-a1]', (g) => {
            const r = g.getBoundingClientRect();
            return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
        });
        await s.page.mouse.move(a.x, a.y);
        for (let i = 0; i < 2; i++) await s.page.mouse.wheel({ deltaY: -300 });
        await L.sleep(300);
        const pt = await s.page.$eval('[data-testid=token-a1]', (g) => {
            const r = g.getBoundingClientRect();
            return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
        });
        await s.page.mouse.click(pt.x, pt.y);
        await s.page.waitForSelector('[data-testid=model-guide]');
        await hit('[data-testid=model-guide]');
        await s.page.waitForSelector('[data-testid=guide-panel]');
        // the default aim is the nearest enemy, far down the table and out of view
        await s.page.waitForSelector('[data-testid=aim-offscreen-arrow]', { timeout: 5000 });
        await s.page.waitForSelector('[data-testid=aim-offscreen]');
        await hit('[data-testid=aim-show]');
        await L.sleep(300);
        if (await s.page.$('[data-testid=aim-offscreen-arrow]')) throw new Error('Show it should bring the aim into view, so the arrow goes');
        if (await s.page.$('[data-testid=aim-offscreen]')) throw new Error('the note should go once the aim is in view');
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
