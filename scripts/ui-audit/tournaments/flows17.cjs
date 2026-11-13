/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const fs = require('fs');
const os = require('os');
const path = require('path');
const L = require('./lib.cjs');
// Event details (rich text) and per-table terrain notes with a photo, for a Malifaux event.
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const org = await L.session(browser, 'org@example.test');
    const guest = await L.session(browser, null);
    const alice = await L.session(browser, 'alice@example.test');

    // a 2x2 PNG, written to disk for the file input
    const png = path.join(os.tmpdir(), 'table-terrain.png');
    fs.writeFileSync(
        png,
        Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAFElEQVR4nGP8z8Dwn4GBgYGJAQoAHhgCAh6X4CYAAAAASUVORK5CYII=', 'base64'),
    );

    await L.step('details: the Details tab offers a rich-text editor and writes the event page', org, async () => {
        await L.visit(org, `/tournaments/${ids.t1}`);
        await L.clickText(org, /^Details$/i, '[role=tab]');
        await org.page.waitForSelector('.tiptap', { timeout: 9000 });
        await L.expectText(org, /Tables and terrain/i);
        await org.page.click('.tiptap');
        await org.page.keyboard.type('House rules: bring your own objective markers.');
        await L.shot(org, '170-details-editor');
        await L.clickText(org, /^Save details$/i);
        await L.expectText(org, /Event details saved/i);
    });
    await L.step('details: everyone sees the details on the public page', guest, async () => {
        await L.visit(guest, `/tournaments/${ids.t1}/view`);
        await guest.page.waitForSelector('[data-testid="event-details"]', { timeout: 9000 });
        // A running event tucks the long details document away behind its summary; opening it shows the text.
        const openAtStart = await guest.page.$eval('[data-testid="event-details"]', (d) => d.open);
        if (openAtStart) throw new Error('details should start collapsed while the event is running');
        await guest.page.click('[data-testid="event-details"] summary');
        await L.expectText(guest, /bring your own objective markers/i);
        await L.shot(guest, '171-details-public');
    });

    await L.step('tables: describe table 1 with a name, notes and a photo', org, async () => {
        await L.visit(org, `/tournaments/${ids.t1}`);
        await L.clickText(org, /^Details$/i, '[role=tab]');
        await org.page.waitForSelector('[data-table="1"]', { timeout: 9000 });
        await org.page.type('#table-name-1', 'The Mill');
        await org.page.type('#table-desc-1', 'Two LoS-blocking buildings in the centre.');
        const input = await org.page.$('#table-image-1');
        await input.uploadFile(png);
        await L.sleep(300);
        await L.shot(org, '172-table-editor');
        await org.page.evaluate(() =>
            [...document.querySelectorAll('[data-table="1"] button')].find((b) => /save table/i.test(b.innerText))?.click(),
        );
        await L.expectText(org, /Table 1 saved/i);
    });
    await L.step('tables: the photo is stored and served', org, async () => {
        // the editor shows a local preview (blob:) until the saved page props arrive, so wait for the stored URL
        const src = await org.page.waitForFunction(
            () => {
                const u = document.querySelector('[data-table="1"] img')?.getAttribute('src');
                return u && u.startsWith('/storage/') ? u : null;
            },
            { timeout: 9000 },
        );
        const url = await src.jsonValue();
        if (!/^\/storage\/tournaments\//.test(url)) throw new Error(`unexpected image url ${url}`);
        const status = await org.page.evaluate(async (u) => (await fetch(u)).status, url);
        if (status !== 200) throw new Error(`image returned ${status}`);
    });
    await L.step('tables: a player sees the table name and terrain on their match card', alice, async () => {
        await L.visit(alice, `/tournaments/${ids.t1}/view`);
        await L.expectText(alice, /Table 1 — The Mill/i);
        await L.expectText(alice, /Two LoS-blocking buildings/i);
        await L.shot(alice, '173-match-terrain');
    });
    await L.step('tables: spectators get a Tables & terrain list and display mode shows the name', guest, async () => {
        await L.visit(guest, `/tournaments/${ids.t1}/view`);
        await guest.page.waitForSelector('[data-testid="tables-terrain"]', { timeout: 9000 });
        await L.clickText(guest, /Tables & terrain/i, 'summary');
        await L.expectText(guest, /Two LoS-blocking buildings/i);
        await L.visit(guest, `/tournaments/${ids.t1}/display`);
        await L.expectText(guest, /The Mill/i);
        await L.shot(guest, '174-display-table-name');
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
    if (bad.length) process.exit(1);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
