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
