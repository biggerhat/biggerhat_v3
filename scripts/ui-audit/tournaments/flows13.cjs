/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const fs = require('fs');
const path = require('path');
const L = require('./lib.cjs');
// A finished event: the Final results card, the PDF sheet and the shareable image.
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const guest = await L.session(browser, null);
    await guest.page.evaluateOnNewDocument(() => {
        // Capture the download the image button triggers instead of letting the browser save it.
        window.__downloads = [];
        HTMLAnchorElement.prototype.click = function () {
            if (this.download) window.__downloads.push({ name: this.download, href: this.href });
            else window.open(this.href);
        };
    });

    await L.step('results: a finished event leads with the podium and the three ways to take it away', guest, async () => {
        await L.visit(guest, `/tournaments/${ids.t7}/view`);
        await L.expectText(guest, /Final results/i);
        const podium = await guest.page.$$eval('ol[aria-label="Podium"] li', (lis) => lis.map((l) => l.innerText.replace(/\s+/g, ' ').trim()));
        if (podium.length !== 3 || !/^1st Alice/i.test(podium[0])) throw new Error(`unexpected podium: ${podium.join(' | ')}`);
        await L.expectText(guest, /PDF/);
        await L.expectText(guest, /Image/);
        await L.shot(guest, '120-results-card');
    });

    await L.step('results: the PDF sheet is served (and saved for a look)', guest, async () => {
        const res = await fetch(`${L.BASE}/tournaments/${ids.t7}/results.pdf`);
        const buf = Buffer.from(await res.arrayBuffer());
        if (res.status !== 200 || buf.slice(0, 4).toString() !== '%PDF') throw new Error(`bad PDF response: ${res.status}`);
        fs.writeFileSync(path.join(process.env.SHOTS || '.', 'results.pdf'), buf);
    });

    await L.step('results: the Image button produces a 1200x630 PNG', guest, async () => {
        await L.clickText(guest, /^Image$/i, 'button');
        await guest.page.waitForFunction(() => window.__downloads.length > 0, { timeout: 9000 });
        const out = await guest.page.evaluate(async () => {
            const d = window.__downloads[0];
            const blob = await (await fetch(d.href)).blob();
            const bmp = await createImageBitmap(blob);
            const data = await new Promise((resolve) => {
                const r = new FileReader();
                r.onload = () => resolve(String(r.result).split(',')[1]);
                r.readAsDataURL(blob);
            });
            return { name: d.name, type: blob.type, size: blob.size, w: bmp.width, h: bmp.height, data };
        });
        if (out.type !== 'image/png' || out.w !== 1200 || out.h !== 630 || out.size < 5000)
            throw new Error(`bad image: ${JSON.stringify({ ...out, data: undefined })}`);
        if (!/spring-showdown-results\.png/.test(out.name)) throw new Error(`unexpected file name ${out.name}`);
        fs.writeFileSync(path.join(process.env.SHOTS || '.', 'results-card.png'), Buffer.from(out.data, 'base64'));
    });

    await L.step('results: an unfinished event has no results card and no PDF', guest, async () => {
        await L.visit(guest, `/tournaments/${ids.t1}/view`);
        const t = await L.text(guest);
        if (/Final results/i.test(t)) throw new Error('an active event must not show Final results');
        const res = await fetch(`${L.BASE}/tournaments/${ids.t1}/results.pdf`);
        if (res.status !== 404) throw new Error(`expected 404, got ${res.status}`);
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
