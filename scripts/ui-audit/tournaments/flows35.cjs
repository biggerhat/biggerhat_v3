/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Virtual tabletop spike: the board renders, a model dragged onto raised terrain climbs to that level, the ruler measures
// edge to edge, the level filter and deployment overlay work, and the page never scrolls sideways on a phone.
(async () => {
    const browser = await L.launch();
    const hscroll = (s) => s.page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

    for (const [label, vp] of [
        ['desktop', { width: 1280, height: 900 }],
        ['phone', { width: 390, height: 844 }],
    ]) {
        const s = await L.session(browser, 'alice@example.test', vp);
        // Board inches to client pixels, read from the live SVG so the test does not depend on layout.
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
        const dragTo = async (from, to) => {
            const a = await pixel(...from);
            const b = await pixel(...to);
            await s.page.mouse.move(a.x, a.y);
            await s.page.mouse.down();
            await s.page.mouse.move((a.x + b.x) / 2, (a.y + b.y) / 2, { steps: 4 });
            await s.page.mouse.move(b.x, b.y, { steps: 4 });
            await s.page.mouse.up();
        };

        await L.step(`tabletop (${label}): board renders with terrain levels and both crews`, s, async () => {
            await L.visit(s, '/tools/tabletop');
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            await L.expectText(s, /Ht 5/);
            if ((await s.page.$$('[data-testid^=token-]')).length !== 6) throw new Error('expected 6 demo models');
            if ((await hscroll(s)) > 1) throw new Error(`page scrolls sideways by ${await hscroll(s)}px`);
            await L.shot(s, `350-tabletop-${label}`);
        });
        await L.step(`tabletop (${label}): dragging a model onto the tower roof raises it to Ht 3`, s, async () => {
            await dragTo([22, 4], [10, 8]); // the minion, onto the Ht 3 roof
            await L.expectText(s, /elevation 3/i);
            await dragTo([10, 8], [5.5, 5.5]); // then up onto the Ht 5 upper level
            await L.expectText(s, /elevation 5/i);
            await dragTo([5.5, 5.5], [20, 12]); // and off to the table: a drop of 5
            await L.expectText(s, /1 irreducible damage/i);
        });
        await L.step(`tabletop (${label}): the ruler measures edge to edge between two models`, s, async () => {
            await L.visit(s, '/tools/tabletop');
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            await s.page.click('[data-testid=mode-ruler]');
            await dragTo([12, 5], [18, 4]); // henchman (40mm) to master (50mm), centres 6.08" apart
            await s.page.waitForSelector('[data-testid=ruler-readout]');
            const t = await s.page.$eval('[data-testid=ruler-readout]', (e) => e.innerText);
            // 6.08 - 0.787 - 0.984 = 4.3
            if (!/4\.3"/.test(t)) throw new Error(`ruler readout is not 4.3": ${t}`);
        });
        await L.step(`tabletop (${label}): level filter and deployment overlay`, s, async () => {
            await s.page.select('[data-testid=level-select]', '0');
            await s.page.select('[data-testid=deployment-select]', 'standard');
            await L.shot(s, `351-tabletop-filter-${label}`);
            if ((await hscroll(s)) > 1) throw new Error(`page scrolls sideways by ${await hscroll(s)}px`);
        });
        await L.step(`tabletop (${label}): adding a model works`, s, async () => {
            await s.page.click('[data-testid=add-token]');
            if ((await s.page.$$('[data-testid^=token-]')).length !== 7) throw new Error('model was not added');
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
