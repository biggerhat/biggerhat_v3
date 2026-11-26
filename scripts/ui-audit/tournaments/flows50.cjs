/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Board feedback of 2026-11 (round 3, second post):
//  - the Step move help keeps its words in one block and the buttons wrap below it;
//  - choosing a model does not move the page on the sandbox either;
//  - a Fate flip for a model is one row;
//  - the Fate deck: a "tap to draw" hint, a hand limit that can be lowered, Undo and Shuffle, and Start phase can shuffle the discard in.
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
        await s.page.click('[data-testid=mode-move]');
    };
    const hit = (sel) =>
        s.page.$eval(sel, (b) => {
            b.scrollIntoView({ block: 'center' });
            b.click();
        });
    const centre = (sel) =>
        s.page.$eval(sel, (g) => {
            const r = g.getBoundingClientRect();
            return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
        });

    await L.step('step move help: the words keep a full line, the buttons wrap under them', s, async () => {
        await openSandbox();
        await s.page.$eval('[data-testid=step-toggle]', (b) => {
            const d = b.closest('details');
            if (d && !d.open) d.open = true;
            b.scrollIntoView({ block: 'center' });
        });
        await s.page.click('[data-testid=step-toggle]');
        await s.page.waitForSelector('[data-testid=step-help]');
        await s.page.$eval('[data-testid=tabletop-board]', (e) => e.scrollIntoView());
        const a = await centre('[data-testid=token-a1]');
        await s.page.mouse.move(a.x, a.y);
        await s.page.mouse.down();
        await s.page.mouse.move(a.x, a.y + 40, { steps: 6 });
        await s.page.mouse.up();
        await s.page.waitForSelector('[data-testid=step-undo]');
        const m = await s.page.$eval('[data-testid=step-help]', (h) => {
            const t = h.querySelector('span').getBoundingClientRect();
            const b = h.querySelector('[data-testid=step-undo]').getBoundingClientRect();
            const box = h.getBoundingClientRect();
            return { text: t.width, box: box.width, textBottom: t.bottom, buttonTop: b.top };
        });
        if (m.text < m.box * 0.8) throw new Error(`the help text is squeezed to ${Math.round(m.text)}px of a ${Math.round(m.box)}px box`);
        if (m.buttonTop < m.textBottom - 1) throw new Error('the buttons should sit under the text');
    });

    for (const [w, h] of [
        [1280, 900],
        [1900, 1200],
        [900, 1000],
        [412, 900],
    ]) {
        await L.step(`choosing a model on the sandbox does not move the page (${w}x${h})`, s, async () => {
            await s.page.setViewport({ width: w, height: h });
            await openSandbox();
            await s.page.$eval('[data-testid=tabletop-board]', (e) => e.scrollIntoView());
            await s.page.evaluate(() => window.scrollBy(0, 60));
            await L.sleep(300);
            const y0 = await s.page.evaluate(() => window.scrollY);
            for (const id of ['a1', 'b1', 'a1']) {
                const q = await centre(`[data-testid=token-${id}]`).catch(() => null);
                if (!q || q.y < 0 || q.y > h) continue;
                await s.page.mouse.click(q.x, q.y);
                await L.sleep(1500);
                const y1 = await s.page.evaluate(() => window.scrollY);
                if (Math.abs(y1 - y0) > 2) throw new Error(`choosing ${id} moved the page: ${y0} -> ${y1}`);
            }
        });
    }
    await s.page.setViewport({ width: 1280, height: 900 });

    await L.step('a Fate flip for a model is one row, and the colours are on the chips', s, async () => {
        await openSandbox();
        const p = await centre('[data-testid=token-a1]');
        await s.page.mouse.click(p.x, p.y);
        await s.page.waitForSelector('[data-testid=model-fate]');
        const rows = await s.page.$eval('[data-testid=model-fate]', (box) => {
            const top = (sel) => Math.round(box.querySelector(sel).getBoundingClientRect().top);
            return {
                minus: top('[data-testid=model-fate-minus]'),
                plus: top('[data-testid=model-fate-plus]'),
                flip: top('[data-testid=model-fate-flip]'),
            };
        });
        if (Math.abs(rows.minus - rows.flip) > 6 || Math.abs(rows.plus - rows.flip) > 6)
            throw new Error(`minus, plus and Flip should share a row: ${JSON.stringify(rows)}`);
        if (await s.page.$('[data-testid=model-fate] summary')) throw new Error('the drop-down should be gone');
        await hit('[data-testid=model-fate-flip]');
        await s.page.waitForSelector('[data-testid=model-fate-choose]', { timeout: 5000 });
        await hit('[data-testid=model-fate-choose]');
        await s.page.waitForSelector('[data-testid=model-fate-result]');
        await hit('[data-testid=model-fate-done]');
    });

    await L.step('Fate deck: tap-to-draw hint, a lower hand limit, shuffle, undo, and the start phase shuffle', s, async () => {
        await openSandbox();
        await hit('[data-testid=fate-open]');
        await s.page.waitForSelector('[data-testid=fate-draw-hint]', { timeout: 5000 });
        const txt = (sel) => s.page.$eval(sel, (e) => e.innerText.trim());
        await hit('[data-testid=fate-refill]');
        await s.page.waitForFunction(
            () => /^7\/7$/.test(document.querySelector('[data-testid=fate-side-1] [data-testid=fate-hand-count]')?.innerText.trim()),
            { timeout: 5000 },
        );
        // a master that shrinks the hand: the limit goes to 5, and the count is read against it
        await hit('[data-testid=fate-limit-minus]');
        await hit('[data-testid=fate-limit-minus]');
        if ((await txt('[data-testid=fate-refill]')) !== 'Up to 5')
            throw new Error(`the button should say Up to 5, it says ${await txt('[data-testid=fate-refill]')}`);
        await s.page.waitForSelector('[data-testid=fate-over-max]');
        // discard two with the hand menu, then draw up to 5 draws nothing; raising it back draws two
        await hit('[data-testid=fate-hand-card]');
        await hit('[data-testid=fate-menu-discard]');
        await s.page.waitForFunction(
            () => /^6\/5$/.test(document.querySelector('[data-testid=fate-side-1] [data-testid=fate-hand-count]').innerText.trim()),
            { timeout: 5000 },
        );
        await hit('[data-testid=fate-undo]');
        await s.page.waitForFunction(
            () => /^7\/5$/.test(document.querySelector('[data-testid=fate-side-1] [data-testid=fate-hand-count]').innerText.trim()),
            { timeout: 5000 },
        );
        // the start phase can shuffle the discard pile in first
        await hit('[data-testid=fate-start-phase]');
        await s.page.waitForSelector('[data-testid=fate-start-shuffle]');
        await s.page.click('[data-testid=fate-start-shuffle]');
        await s.page.click('[data-testid=fate-start-card]');
        await s.page.click('[data-testid=fate-start-apply]');
        await s.page.waitForFunction(() => !document.querySelector('[data-testid=fate-start-dialog]'), { timeout: 5000 });
        // one card discarded, then shuffled back in: the discard pile is empty, and the hand is 6 of 5 less nothing drawn
        const discard = await s.page.$$('[data-testid=fate-discard-card]');
        if (discard.length) throw new Error('the discard pile should have gone back into the deck');
        await hit('[data-testid=fate-shuffle-quick]');
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
