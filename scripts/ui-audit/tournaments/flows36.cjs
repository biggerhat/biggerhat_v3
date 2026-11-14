/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Game board: a live game's crews are on the 36" table, a dragged model keeps its place after a reload,
// and a person who is not in the game cannot open it.
(async () => {
    const browser = await L.launch();
    const hscroll = (s) => s.page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    const circle = (s, nth) =>
        s.page.evaluate((i) => {
            const c = document.querySelectorAll('[data-testid^=token-] circle')[i];
            const r = c.getBoundingClientRect();
            return { cx: Number(c.getAttribute('cx')), cy: Number(c.getAttribute('cy')), px: r.left + r.width / 2, py: r.top + r.height / 2 };
        }, nth);

    const nav = await L.session(browser, 'carol@example.test');
    await L.step('board: the play screen links to it', nav, async () => {
        await L.visit(nav, `/games/${L.ids.play_game}`);
        await nav.page.waitForSelector('[data-testid=open-board]');
        await nav.page.click('[data-testid=open-board]');
        await nav.page.waitForSelector('[data-testid=tabletop-board]', { timeout: 9000 }).catch(async () => {
            throw new Error(`board did not appear at ${nav.page.url()}: ${(await L.text(nav)).replace(/\\s+/g, ' ').slice(0, 300)}`);
        });
        if (!nav.page.url().endsWith('/board')) throw new Error(`landed on ${nav.page.url()}`);
        await L.clickText(nav, /Back to the game/i, 'a');
        await nav.page.waitForSelector('[data-testid=open-board]');
    });

    for (const [label, vp] of [
        ['desktop', { width: 1280, height: 900 }],
        ['phone', { width: 390, height: 844 }],
    ]) {
        const carol = await L.session(browser, 'carol@example.test', vp);
        await L.step(`board (${label}): the game's crews are on the table with a Back link`, carol, async () => {
            await L.visit(carol, `/games/${L.ids.play_game}/board`);
            await carol.page.waitForSelector('[data-testid^=token-]');
            const n = (await carol.page.$$('[data-testid^=token-]')).length;
            if (n < 2) throw new Error(`expected both crews on the table, found ${n} models`);
            await L.expectText(carol, /Back to the game/);
            if ((await hscroll(carol)) > 1) throw new Error(`page scrolls sideways by ${await hscroll(carol)}px`);
            await L.shot(carol, `360-board-${label}`);
        });
        await L.step(`board (${label}): a dragged model keeps its place after a reload`, carol, async () => {
            const before = await circle(carol, 0);
            await carol.page.mouse.move(before.px, before.py);
            await carol.page.mouse.down();
            await carol.page.mouse.move(before.px + 40, before.py + 80, { steps: 6 });
            await carol.page.mouse.up();
            await L.sleep(1200); // the save is a background request
            await L.visit(carol, `/games/${L.ids.play_game}/board`);
            await carol.page.waitForSelector('[data-testid^=token-]');
            const after = await circle(carol, 0);
            if (Math.abs(after.cx - before.cx) < 0.5 && Math.abs(after.cy - before.cy) < 0.5)
                throw new Error('the model went back to where it started');
        });
        await L.step(`board (${label}): terrain laid out on the game's table is saved with the game`, carol, async () => {
            const centre = (testid) => carol.page.$eval(`[data-testid=${testid}]`, (b) => b.scrollIntoView({ block: 'center' }));
            const click = async (testid) => {
                await centre(testid);
                await carol.page.click(`[data-testid=${testid}]`);
            };
            const pieces = () => carol.page.$eval('[data-testid=terrain-stats]', (e) => Number((e.innerText.match(/(\d+) pieces?/) || [])[1]));
            await L.visit(carol, `/games/${L.ids.play_game}/board`);
            await carol.page.waitForSelector('[data-testid=mode-terrain]');
            await click('mode-terrain');
            await carol.page.waitForSelector('[data-testid=terrain-stats]');
            const start = await pieces();
            await carol.page.select('[data-testid=terrain-preset]', 'tower');
            await click('terrain-add');
            await carol.page.waitForFunction(
                (n) => Number((document.querySelector('[data-testid=terrain-stats]').innerText.match(/(\d+) pieces?/) || [])[1]) === n,
                { timeout: 5000 },
                start + 1,
            );
            await L.sleep(1200); // the save is a background request
            await L.visit(carol, `/games/${L.ids.play_game}/board`);
            await carol.page.waitForSelector('[data-testid=mode-terrain]');
            await click('mode-terrain');
            const kept = await pieces();
            if (kept !== start + 1) throw new Error(`expected ${start + 1} pieces after a reload, found ${kept}`);
            await L.shot(carol, `361-board-terrain-${label}`);
            // Leave the table as it was found.
            await click('terrain-clear');
            await L.sleep(1200);
        });
    }

    const live = await L.session(browser, 'carol@example.test');
    await L.step('board: a move broadcast by the other player appears without a reload', live, async () => {
        // No Reverb server in the harness: stand in for Echo and fire the event the server would send.
        await live.page.evaluateOnNewDocument(() => {
            window.Echo = {
                join() {
                    const ch = {
                        listen(event, cb) {
                            if (event === '.GameBoardMoved') window.__boardMoved = cb;
                            if (event === '.GameBoardTerrainChanged') window.__boardTerrain = cb;
                            return ch;
                        },
                    };
                    return ch;
                },
                // the notification bell listens on a private channel
                private() {
                    const quiet = { listen: () => quiet, notification: () => quiet };
                    return quiet;
                },
                leave() {},
            };
        });
        await L.visit(live, `/games/${L.ids.play_game}/board`);
        await live.page.waitForSelector('[data-testid^=token-]');
        await live.page.waitForFunction(() => typeof window.__boardMoved === 'function', { timeout: 9000 });
        const id = await live.page.evaluate(() =>
            document.querySelectorAll('[data-testid^=token-]')[1].getAttribute('data-testid').replace('token-', ''),
        );
        await live.page.evaluate((i) => window.__boardMoved({ positions: { [i]: { x: 20, y: 18 } } }), id);
        await L.sleep(300);
        const at = await live.page.evaluate(() => {
            const c = document.querySelectorAll('[data-testid^=token-] circle')[1];
            return { cx: Number(c.getAttribute('cx')), cy: Number(c.getAttribute('cy')) };
        });
        if (Math.abs(at.cx - 20) > 0.01 || Math.abs(at.cy - 18) > 0.01) throw new Error(`model did not move: ${JSON.stringify(at)}`);
    });
    await L.step('board: terrain the other player lays out appears without a reload', live, async () => {
        await live.page.waitForFunction(() => typeof window.__boardTerrain === 'function', { timeout: 9000 });
        const wall = (id, x) => ({
            id,
            name: 'Wall',
            traits: ['blocking', 'climbable'],
            surfaces: [{ id: `${id}-1`, top: 2, shape: { kind: 'rect', x, y: 10, w: 8, h: 1.5 } }],
        });
        await live.page.evaluate((layout) => window.__boardTerrain({ terrain: layout }), [wall('w1', 4), wall('w2', 20)]);
        await live.page.waitForFunction(
            () =>
                document.querySelectorAll('[data-testid=tabletop-board] svg text').length > 0 &&
                [...document.querySelectorAll('[data-testid=tabletop-board] svg text')].filter((t) => t.textContent.trim() === 'Ht 2').length === 2,
            { timeout: 5000 },
        );
        await live.page.evaluate(() => window.__boardTerrain({ terrain: [] }));
        await live.page.waitForFunction(
            () => ![...document.querySelectorAll('[data-testid=tabletop-board] svg text')].some((t) => /^Ht /.test(t.textContent.trim())),
            { timeout: 5000 },
        );
    });

    const outsider = await L.session(browser, 'alice@example.test');
    await L.step('board: someone who is not in the game gets a 403', outsider, async () => {
        const res = await outsider.page.goto(`${L.BASE}/games/${L.ids.play_game}/board`, { waitUntil: 'networkidle2' });
        if (res.status() !== 403) throw new Error(`expected 403, got ${res.status()}`);
        outsider.problems.length = 0; // the browser logs the 403 itself; that is the point of the step
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
