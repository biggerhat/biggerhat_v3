/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Tabletop audit pass (2026-11):
//  - on a phone the keyboard help is not shown, and the "details" chip does not sit on the Fate button;
//  - choosing a model by touch is spoken (a polite status), and keyboard choosing is not said twice;
//  - the parts split into their own files (terrain editor, table dialogs, Fate mat) still open on demand;
//  - the sandbox keeps the table across a reload even though it now saves after a pause.
(async () => {
    const browser = await L.launch();
    const DEMO = [
        { id: 'a1', sp: 5, sz: 3, name: 'Rogue Necromancer', side: 'a', baseMm: 50, x: 14, y: 8 },
        { id: 'b1', sp: 5, sz: 3, name: 'Master', side: 'b', baseMm: 50, x: 18, y: 28 },
    ];
    const open = async (s) => {
        await L.visit(s, '/tools/tabletop');
        await s.page.evaluate((t) => {
            let o = {};
            try {
                o = JSON.parse(localStorage.getItem('tabletop-spike-v2')) || {};
            } catch {
                // start from nothing
            }
            localStorage.setItem('tabletop-spike-v2', JSON.stringify({ ...o, tokens: t }));
        }, DEMO);
        await L.visit(s, '/tools/tabletop');
        await s.page.waitForSelector('[data-testid=tabletop-board] svg');
    };
    const centre = (s, sel) =>
        s.page.$eval(sel, (g) => {
            const r = g.getBoundingClientRect();
            return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
        });

    const phone = await L.session(browser, 'alice@example.test', { width: 390, height: 844, isMobile: true, hasTouch: true });
    await L.step(
        'phone: no keyboard help on screen, the details chip and the Fate button do not overlap, and choosing a model is announced',
        phone,
        async () => {
            await open(phone);
            const p = await centre(phone, '[data-testid=token-a1]');
            await phone.page.touchscreen.tap(p.x, p.y);
            await L.sleep(500);
            const keys = await phone.page.$eval('[data-testid=board-keys]', (e) => {
                const r = e.getBoundingClientRect();
                return { w: r.width, h: r.height };
            });
            if (keys.w > 2 || keys.h > 2) throw new Error(`the keyboard help should not take room on a touch screen: ${keys.w}x${keys.h}`);
            const said = await phone.page.$eval('[data-testid=tabletop-announce]', (e) => e.innerText);
            if (!/Rogue Necromancer chosen\./.test(said)) throw new Error(`choosing a model should be announced, got "${said}"`);
            await phone.page.waitForSelector('[data-testid=selection-chip]', { timeout: 5000 }).catch(() => null);
            const chip = await phone.page.$('[data-testid=selection-chip]');
            const fate = await phone.page.$('[data-testid=fate-open]');
            if (chip && fate) {
                const a = await chip.boundingBox();
                const b = await fate.boundingBox();
                if (a && b && a.x < b.x + b.width && a.x + a.width > b.x && a.y < b.y + b.height && a.y + a.height > b.y)
                    throw new Error('the details chip sits on the Fate button');
            }
        },
    );

    const s = await L.session(browser, 'alice@example.test', { width: 1280, height: 900 });
    await L.step('keyboard choosing is not announced twice (the note says it)', s, async () => {
        await open(s);
        await s.page.$eval('[data-testid=tabletop-board] svg', (e) => e.focus());
        await s.page.keyboard.press(']');
        await L.sleep(400);
        const note = await s.page.$eval('[data-testid=tabletop-note]', (e) => e.innerText);
        const said = await s.page.$eval('[data-testid=tabletop-announce]', (e) => e.innerText);
        if (!note) throw new Error('the keyboard note should say which model was chosen');
        if (said) throw new Error(`the keyboard path was said twice: "${said}"`);
    });

    await L.step('split-out parts load on demand: terrain editor, table dialog and the Fate mat', s, async () => {
        await open(s);
        await s.page.$eval('[data-testid=mode-terrain]', (b) => {
            b.scrollIntoView({ block: 'center' });
            b.click();
        });
        await s.page.waitForSelector('[data-testid=terrain-stats]', { timeout: 8000 });
        await s.page.$eval('[data-testid=table-load]', (b) => {
            b.scrollIntoView({ block: 'center' });
            b.click();
        });
        await s.page.waitForSelector('[data-testid=random-table] svg', { timeout: 8000 });
        await s.page.keyboard.press('Escape');
        // the Terrain tab keeps the Fate mat shut: back to the models first
        await s.page.$eval('[data-testid=mode-move]', (b) => b.click());
        await s.page.$eval('[data-testid=fate-open]', (b) => {
            b.scrollIntoView({ block: 'center' });
            b.click();
        });
        await s.page.waitForSelector('[data-testid=fate-draw-hint]', { timeout: 8000 });
    });

    await L.step('the sandbox keeps a moved model across a reload, though it saves after a pause', s, async () => {
        await open(s);
        await s.page.$eval('[data-testid=tabletop-board]', (e) => e.scrollIntoView());
        const p = await centre(s, '[data-testid=token-a1]');
        await s.page.mouse.move(p.x, p.y);
        await s.page.mouse.down();
        await s.page.mouse.move(p.x + 60, p.y + 20, { steps: 6 });
        await s.page.mouse.up();
        // reload at once: the pending save is written as the page is left
        await s.page.reload();
        await s.page.waitForSelector('[data-testid=token-a1]');
        const after = await centre(s, '[data-testid=token-a1]');
        if (Math.abs(after.x - p.x) < 10) throw new Error('the move was lost on reload');
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
