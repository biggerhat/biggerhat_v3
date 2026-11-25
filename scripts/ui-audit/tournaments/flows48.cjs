/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Board feedback of 2026-11 (round 2):
//  - a Step move stops at the edge of its ring, the ring is measured from the edge of the base and moves with each step, Escape puts the model
//    back, Undo step takes back a leg;
//  - the view can be moved with Space held while Sight guidelines are on, and a double click no longer ends them;
//  - dragging empty table keeps the chosen model, a still click lets go of it, and choosing a model does not scroll the page.
(async () => {
    const browser = await L.launch();
    const s = await L.session(browser, 'alice@example.test', { width: 1280, height: 900 });
    // the game board is carol's: she is in the game
    const carol = await L.session(browser, 'carol@example.test', { width: 1280, height: 900 });

    const pixel = (x, y) =>
        s.page.evaluate(
            ([bx, by]) => {
                const svg = document.querySelector('[data-testid=tabletop-board] svg');
                const r = svg.getBoundingClientRect();
                const vb = svg.viewBox.baseVal;
                return { x: r.left + ((bx - vb.x) / vb.width) * r.width, y: r.top + ((by - vb.y) / vb.height) * r.height };
            },
            [x, y],
        );
    const toBoardTop = () => s.page.evaluate(() => document.querySelector('[data-testid=tabletop-board]').scrollIntoView());
    const DEMO = [
        { id: 'a1', sp: 5, sz: 3, name: 'Master', side: 'a', baseMm: 50, x: 18, y: 4 },
        { id: 'a2', sp: 5, sz: 2, name: 'Henchman', side: 'a', baseMm: 40, x: 12, y: 5 },
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
    const dragTo = async (from, to) => {
        await toBoardTop();
        const a = await pixel(...from);
        const b = await pixel(...to);
        await s.page.mouse.move(a.x, a.y);
        await s.page.mouse.down();
        await s.page.mouse.move((a.x + b.x) / 2, (a.y + b.y) / 2, { steps: 4 });
        await s.page.mouse.move(b.x, b.y, { steps: 4 });
        await s.page.mouse.up();
    };
    const stepOn = async () => {
        await s.page.$eval('[data-testid=step-toggle]', (b) => {
            const d = b.closest('details');
            if (d && !d.open) d.open = true;
            b.scrollIntoView({ block: 'center' });
        });
        await s.page.click('[data-testid=step-toggle]');
        await s.page.waitForSelector('[data-testid=step-help]');
    };
    const tokenAt = async (id) =>
        s.page.$eval(`[data-testid=token-${id}]`, (g) => {
            const r = g.getBoundingClientRect();
            return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
        });
    const near = (a, b, tol = 6) => Math.hypot(a.x - b.x, a.y - b.y) <= tol;
    const viewX = () => s.page.$eval('[data-testid=tabletop-board] svg', (svg) => svg.viewBox.baseVal.x);

    await L.step('step move: a leg stops at the edge of the ring, which is drawn from the base and moves with each step', s, async () => {
        await openSandbox();
        await stepOn();
        await dragTo([18, 4], [18, 6]); // 2" of a Walk of 5
        await s.page.waitForSelector('[data-testid=walk-reach]');
        const ring = await s.page.$eval('[data-testid=walk-reach]', (c) => ({ cy: Number(c.getAttribute('cy')), r: Number(c.getAttribute('r')) }));
        // 3" left, plus the 50 mm base's radius (0.98"), around where the model now stands
        if (Math.abs(ring.r - 3.98) > 0.05) throw new Error(`the ring should reach 3" past the base (3.98), saw ${ring.r}`);
        if (Math.abs(ring.cy - 6) > 0.3) throw new Error(`the ring should be around the model's new place (y 6), saw ${ring.cy}`);
        await dragTo([18, 6], [18, 20]); // asks for 14": it stops at the 3" that are left
        const total = await s.page.$eval('[data-testid=move-trail-label]', (e) => e.textContent.trim());
        if (!/^5(\.0)?" of 5"$/.test(total)) throw new Error(`the move should stop at 5" of 5", saw ${total}`);
        const at = await tokenAt('a1');
        const want = await pixel(18, 9);
        if (!near(at, want, 10)) throw new Error(`the model should have stopped at y 9, it is ${Math.round(at.y - want.y)}px off`);
    });

    await L.step('step move: Undo step takes back the last leg, and Escape puts the model back where it began', s, async () => {
        await openSandbox();
        await stepOn();
        await dragTo([18, 4], [18, 6]);
        await dragTo([18, 6], [18, 8]);
        if ((await s.page.$$('[data-testid=move-trail-leg]')).length !== 2) throw new Error('two legs expected');
        await s.page.$eval('[data-testid=step-undo]', (b) => b.click());
        await L.sleep(200);
        let at = await tokenAt('a1');
        if (!near(at, await pixel(18, 6), 10)) throw new Error('Undo step should put the model back at the end of the first leg');
        const total = await s.page.$eval('[data-testid=move-trail-label]', (e) => e.textContent.trim());
        if (!/^2(\.0)?" of 5"$/.test(total)) throw new Error(`after undo the move should be 2" of 5", saw ${total}`);
        // Escape, with the table focused (a drag on the model gives it the keyboard)
        await dragTo([18, 6], [18, 8]);
        await s.page.keyboard.press('Escape');
        await L.sleep(200);
        at = await tokenAt('a1');
        if (!near(at, await pixel(18, 4), 10)) throw new Error('Escape should put the model back where it began');
        if (await s.page.$('[data-testid=move-trail-path]')) throw new Error('the trail should be gone after Escape');
    });

    await L.step('sight guidelines: Space and drag moves the view, and a double click leaves them on', s, async () => {
        await openSandbox();
        await toBoardTop();
        // zoom in so the view has somewhere to go
        const a1 = await pixel(18, 4);
        await s.page.mouse.move(a1.x, a1.y);
        for (let i = 0; i < 2; i++) await s.page.mouse.wheel({ deltaY: -300 });
        await L.sleep(300);
        // choose a model that is still on screen after the zoom, and turn its guidelines on
        const id = await s.page.$eval('[data-testid=tabletop-board] svg', (svg) => {
            const b = svg.getBoundingClientRect();
            const g = [...svg.querySelectorAll('[data-testid^=token-]')].find((t) => {
                const r = t.getBoundingClientRect();
                return r.left > b.left && r.right < b.right && r.top > b.top && r.bottom < b.bottom;
            });
            return g?.getAttribute('data-testid')?.replace('token-', '') ?? null;
        });
        if (!id) throw new Error('no model on screen after zooming');
        const pt = await tokenAt(id);
        await s.page.mouse.click(pt.x, pt.y);
        await s.page.waitForSelector('[data-testid=model-guide]');
        await s.page.$eval('[data-testid=model-guide]', (b) => {
            b.scrollIntoView({ block: 'center' });
            b.click();
        });
        await s.page.waitForSelector('[data-testid=guide-panel]');
        await toBoardTop();
        const before = await viewX();
        const r = await s.page.$eval('[data-testid=tabletop-board] svg', (svg) => {
            const b = svg.getBoundingClientRect();
            return { x: b.left + b.width / 2, y: b.top + b.height - 8 };
        });
        await s.page.keyboard.down(' ');
        await s.page.mouse.move(r.x, r.y);
        await s.page.mouse.down();
        await s.page.mouse.move(r.x - 120, r.y, { steps: 6 });
        await s.page.mouse.up();
        await s.page.keyboard.up(' ');
        const after = await viewX();
        if (Math.abs(after - before) < 0.5) throw new Error(`Space and drag should move the view (x ${before} -> ${after})`);
        if (!(await s.page.$('[data-testid=guide-panel]'))) throw new Error('moving the view should leave the guidelines on');
        // a double click on bare table no longer switches them off
        await s.page.mouse.move(r.x, r.y);
        await s.page.mouse.down({ clickCount: 1 });
        await s.page.mouse.up({ clickCount: 1 });
        await s.page.mouse.down({ clickCount: 2 });
        await s.page.mouse.up({ clickCount: 2 });
        await L.sleep(250);
        if (!(await s.page.$('[data-testid=guide-panel]'))) throw new Error('a double click should not turn the guidelines off any more');
        await s.page.keyboard.press('g');
        await L.sleep(250);
    });

    await L.step('choosing: dragging empty table keeps the model chosen, a still click lets go of it', s, async () => {
        await openSandbox();
        await toBoardTop();
        // the panel is always there: with nothing chosen it says "Tap a model to select it"
        const chosen = () => s.page.$eval('[data-testid=selected-panel]', (e) => !/Tap a model to select/.test(e.innerText)).catch(() => false);
        if (await chosen()) throw new Error('nothing should be chosen to begin with');
        const m = await pixel(12, 5);
        await s.page.mouse.click(m.x, m.y);
        await L.sleep(250);
        if (!(await chosen())) throw new Error('a click on a model should choose it');
        const a = await pixel(30, 20);
        await s.page.mouse.move(a.x, a.y);
        await s.page.mouse.down();
        await s.page.mouse.move(a.x - 30, a.y - 10, { steps: 5 });
        await s.page.mouse.up();
        await L.sleep(200);
        if (!(await chosen())) throw new Error('moving the view should keep the model chosen');
        const b = await pixel(30, 20);
        await s.page.mouse.click(b.x, b.y);
        await L.sleep(250);
        if (await chosen()) throw new Error('a still click on empty table should let go of the model');
    });

    await L.step('game board: choosing a model does not scroll the page', carol, async () => {
        const page = carol.page;
        await L.visit(carol, `/games/${L.ids.play_game}/board`);
        await page.waitForSelector('[data-testid^=token-]');
        const id = await page.$eval('[data-testid^=token-]', (g) => g.getAttribute('data-testid'));
        await page.evaluate((t) => document.querySelector(`[data-testid=${t}]`).scrollIntoView({ block: 'center' }), id);
        await L.sleep(300);
        const y0 = await page.evaluate(() => window.scrollY);
        const p = await page.$eval(`[data-testid=${id}]`, (g) => {
            const r = g.getBoundingClientRect();
            return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
        });
        await page.mouse.click(p.x, p.y);
        await L.sleep(1200); // a smooth scroll takes its time
        const y1 = await page.evaluate(() => window.scrollY);
        if (Math.abs(y1 - y0) > 2) throw new Error(`choosing a model moved the page: ${y0} -> ${y1}`);
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
