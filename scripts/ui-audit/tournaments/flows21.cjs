/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Accessibility polish: a glossary for the standings abbreviations, and a live region that announces what changes.
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const org = await L.session(browser, 'org@example.test');
    const guest = await L.session(browser, null);

    await L.step('glossary: Format & rules explains TP, VP, DIFF and Bye', guest, async () => {
        await L.visit(guest, `/tournaments/${ids.t1}/view`);
        await L.clickText(guest, /Format & rules/i, 'summary');
        await guest.page.waitForSelector('[data-testid="glossary"]', { timeout: 9000 });
        await L.expectText(guest, /Tournament Points/i);
        await L.expectText(guest, /Differential/i);
        await L.shot(guest, '210-glossary');
    });

    await L.step('live region: a polite status region exists and starts empty', guest, async () => {
        const info = await guest.page.evaluate(() => {
            const el = document.querySelector('[data-testid="live-region"]');
            return el ? { live: el.getAttribute('aria-live'), role: el.getAttribute('role'), text: el.textContent.trim() } : null;
        });
        if (!info || info.live !== 'polite' || info.role !== 'status') throw new Error(`live region missing or wrong: ${JSON.stringify(info)}`);
        if (info.text) throw new Error(`live region should start empty, has "${info.text}"`);
    });
    await L.step('live region: a reported result is announced without reloading the page', guest, async () => {
        // the spectator's page stays open while the organizer scores a game; the socket is down here, so the 30 s poll refreshes it
        await L.visit(org, `/tournaments/${ids.t1}`);
        await L.clickText(org, /^Enter score$/i, 'button');
        await L.expectText(org, /Game Score/i);
        await org.page.click('input[type=number]', { clickCount: 3 });
        await org.page.type('input[type=number]', '3');
        await org.page.keyboard.press('Enter');
        await L.expectText(org, /Reported|3 – 0|3 - 0/i);
        await guest.page.waitForFunction(
            () => /Result reported for table \d/.test(document.querySelector('[data-testid="live-region"]')?.textContent ?? ''),
            {
                timeout: 60000,
            },
        );
        await L.shot(guest, '211-live-announced');
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
    if (bad.length) process.exit(1);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
