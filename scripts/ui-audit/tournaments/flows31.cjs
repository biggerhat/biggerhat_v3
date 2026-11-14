/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Start-up watchdog: when the app bundle never mounts, the page reloads itself once, then offers a Reload button.
(async () => {
    const browser = await L.launch();
    const guest = await L.session(browser, null, { width: 390, height: 844 });
    let loads = 0;

    await L.step('watchdog: a healthy page shows no warning bar', guest, async () => {
        await L.visit(guest, '/');
        await L.sleep(11000);
        if (await guest.page.$('[role=alert][style*="fixed"]')) throw new Error('warning bar appeared on a healthy page');
    });
    await L.step('watchdog: a bundle that never loads reloads once, then shows a Reload button', guest, async () => {
        await guest.page.setRequestInterception(true);
        guest.page.on('request', (r) => {
            if (r.isNavigationRequest() && r.frame() === guest.page.mainFrame()) loads++;
            if (/\/build\/assets\/app-.*\.js|resources\/js\/app\.ts/.test(r.url())) r.abort();
            else r.continue();
        });
        await guest.page.goto(`${L.BASE}/`, { waitUntil: 'domcontentloaded' });
        await guest.page.waitForSelector('[role=alert]', { timeout: 40000 });
        const txt = await guest.page.evaluate(() => document.querySelector('[role=alert]').innerText);
        if (!/Reload/.test(txt)) throw new Error(`bar text: ${txt}`);
        if (loads !== 2) throw new Error(`expected exactly 2 page loads (original + one auto reload), got ${loads}`);
        await L.shot(guest, '310-watchdog');
        guest.problems.length = 0; // the aborted bundle requests are the point of this step
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
