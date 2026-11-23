/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Deleting a saved crew in the Crew Builder at phone and desktop width: the page must stay usable afterwards.
(async () => {
    const browser = await L.launch();
    for (const [label, vp] of [
        ['phone', { width: 390, height: 844 }],
        ['desktop', { width: 1280, height: 900 }],
    ]) {
        const s = await L.session(browser, 'alice@example.test', vp);
        const errors = [];
        s.page.on('pageerror', (e) => errors.push(e.message));
        await L.step(`crew builder (${label}): deleting a saved crew leaves the page responsive`, s, async () => {
            await L.visit(s, '/tools/crew-builder/editor');
            await s.page.waitForSelector('[aria-label^="More actions for"]');
            const before = await s.page.$$eval('[aria-label^="More actions for"]', (e) => e.length);
            await s.page.$eval('[aria-label^="More actions for"]', (b) => b.click());
            await L.clickText(s, /^Delete…$/, '[role=menuitem]');
            await s.page.waitForSelector('[role=dialog]');
            await L.shot(s, `421-crew-delete-${label}`);
            await L.clickText(s, /^Delete$/, '[role=dialog] button');
            await s.page.waitForFunction(() => !document.querySelector('[role=dialog]'), { timeout: 6000 });
            const alive = await Promise.race([
                s.page.evaluate(() => document.body.style.pointerEvents + '|' + document.querySelectorAll('[aria-label^="More actions for"]').length),
                L.sleep(5000).then(() => 'HUNG'),
            ]);
            if (alive === 'HUNG') throw new Error('the page stopped answering');
            const [pe, after] = alive.split('|');
            if (pe === 'none') throw new Error('body has pointer-events none');
            if (Number(after) !== before - 1) throw new Error(`rows ${before} -> ${after}`);
            if (errors.length) throw new Error(`page errors: ${errors.join('; ')}`);
            await L.shot(s, `421-crew-deleted-${label}`);
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
