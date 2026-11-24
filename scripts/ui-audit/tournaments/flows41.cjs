/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Board feedback of 2026-11: the Fate hand fits its width without a scroll bar (cards fan over one another and each shows its corner), and the
// sight guidelines can be switched off with G, Escape or a double click instead of staying on.
(async () => {
    const browser = await L.launch();
    for (const [label, vp] of [
        ['desktop', { width: 1280, height: 900 }],
        ['phone', { width: 390, height: 844 }],
    ]) {
        const s = await L.session(browser, 'carol@example.test', vp);
        s.page.on('dialog', (d) => d.accept().catch(() => {}));
        const press = (id) =>
            s.page.$eval(`[data-testid=${id}]`, (b) => {
                b.scrollIntoView({ block: 'center' });
                b.click();
            });
        const handCount = () => s.page.$eval('[data-testid=fate-side-1] [data-testid=fate-hand-count]', (e) => Number(e.innerText.split('/')[0]));

        await L.step(`fate hand (${label}): a full hand fits its width with no scroll bar, and a card can still be picked`, s, async () => {
            await L.visit(s, `/games/${L.ids.play_game}/board`);
            await s.page.waitForSelector('[data-testid=fate-section]');
            await s.page.$eval('[data-testid=fate-open]', (el) => {
                el.scrollIntoView({ block: 'center' });
                el.click();
            });
            await s.page.waitForSelector('[data-testid=fate-side-1]', { timeout: 8000 });
            // the server keeps the hand: draw up to the limit
            for (let i = 0; i < 7 && (await handCount()) < 7; i++) {
                const had = await handCount();
                await press('fate-draw');
                await s.page.waitForFunction(
                    (n) => Number(document.querySelector('[data-testid=fate-side-1] [data-testid=fate-hand-count]').innerText.split('/')[0]) > n,
                    { timeout: 5000 },
                    had,
                );
            }
            const m = await s.page.$eval('[data-testid=fate-hand]', (h) => {
                const cards = [...h.querySelectorAll('[data-testid=fate-hand-card]')];
                const hr = h.getBoundingClientRect();
                const last = cards[cards.length - 1].getBoundingClientRect();
                return {
                    n: cards.length,
                    scrolls: h.scrollWidth > h.clientWidth + 1,
                    overflowX: getComputedStyle(h).overflowX,
                    lastRight: last.right,
                    handRight: hr.right,
                };
            });
            if (m.n < 5) throw new Error(`only ${m.n} cards in the hand`);
            if (m.scrolls || m.overflowX === 'auto' || m.overflowX === 'scroll') throw new Error(`the hand scrolls sideways (${JSON.stringify(m)})`);
            if (m.lastRight > m.handRight + 1) throw new Error(`the last card sticks out of the hand (${JSON.stringify(m)})`);
            await L.shot(s, `410-fate-hand-${label}`);
            // the last card is on top and can be picked
            await s.page.$$eval('[data-testid=fate-hand-card]', (cs) => cs[cs.length - 1].click());
            await s.page.waitForSelector('[data-testid=fate-card-menu]', { timeout: 4000 });
        });

        await L.step(`sight guidelines (${label}): G turns them on and off, Escape ends them, a double click ends them`, s, async () => {
            await L.visit(s, `/games/${L.ids.play_game}/board`);
            await s.page.waitForSelector('[data-testid^=token-]');
            const point = (testid) =>
                s.page.evaluate((id) => {
                    const g = document.querySelector(`[data-testid=${id}]`);
                    g.scrollIntoView({ block: 'center' });
                    const r = g.getBoundingClientRect();
                    return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
                }, testid);
            const tokenId = await s.page.$eval('[data-testid^=token-]', (g) => g.getAttribute('data-testid'));
            const guides = async () => !!(await s.page.$('[data-testid=guide-panel]'));
            const select = async () => {
                const p = await point(tokenId);
                await s.page.mouse.click(p.x, p.y);
                await L.sleep(250);
            };
            const want = async (on, what) => {
                await L.sleep(250);
                if ((await guides()) !== on) {
                    const active = await s.page.evaluate(
                        () =>
                            `${document.activeElement?.tagName} ${document.activeElement?.getAttribute('data-testid') ?? ''} sel=${document.querySelector('[data-testid=selected-panel]') ? 'yes' : 'no'}`,
                    );
                    throw new Error(`${what}: guidelines were ${on ? 'not on' : 'still on'} (focus: ${active})`);
                }
            };

            await select();
            await s.page.keyboard.press('g');
            await want(true, 'G on a chosen model');
            await s.page.keyboard.press('g');
            await want(false, 'G again');

            await s.page.keyboard.press('g');
            await want(true, 'G once more');
            await s.page.keyboard.press('Escape'); // first lets go of the model
            await want(true, 'Escape the first time (it lets go of the model)');
            await s.page.keyboard.press('Escape');
            await want(false, 'Escape the second time');

            await select();
            await s.page.keyboard.press('g');
            await want(true, 'G before the double click');
            // a double click on empty table (the far corner, away from the models)
            const corner = await s.page.evaluate(() => {
                const svg = document.querySelector('[data-testid=tabletop-board] svg') ?? document.querySelector('svg[data-testid=board-svg]');
                const r = (svg ?? document.querySelector('[data-testid=tabletop-board]')).getBoundingClientRect();
                return { x: r.left + r.width - 6, y: r.top + r.height - 6 };
            });
            await s.page.mouse.move(corner.x, corner.y);
            // two quick presses of the left button, the second one a double click (clickCount 2), as a browser sends them
            await s.page.mouse.down({ clickCount: 1 });
            await s.page.mouse.up({ clickCount: 1 });
            await s.page.mouse.down({ clickCount: 2 });
            await s.page.mouse.up({ clickCount: 2 });
            await want(false, 'a double click on the table');
            await L.shot(s, `410-sight-off-${label}`);
        });
    }

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
    for (const r of L.results) console.log(`${r.status} ${r.name}${r.detail ? '\n       ' + r.detail : ''}`);
    if (bad.length) process.exit(1);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
