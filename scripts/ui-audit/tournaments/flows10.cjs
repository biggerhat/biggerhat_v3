/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Spectator and player screens: status chips, announcements, the confirm block, display mode, wide layout.
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const org = await L.session(browser, 'org@example.test');
    const guest = await L.session(browser, null);

    await L.step('announcement: organizer posts one from Manage', org, async () => {
        await L.visit(org, `/tournaments/${ids.t1}`);
        await L.clickText(org, /^Announce$/i, 'button');
        await L.expectText(org, /Shown above the pairings/i);
        await org.page.type('#announcement-text', 'Lunch until 1:15');
        await L.clickText(org, /^Post$/i, 'button');
        await L.expectText(org, /Edit announcement/i);
    });
    await L.step('announcement: guests see it on the view page and the display', guest, async () => {
        await L.visit(guest, `/tournaments/${ids.t1}/view`);
        await L.expectText(guest, /Lunch until 1:15/);
        await L.shot(guest, '95-announcement-view');
        await L.visit(guest, `/tournaments/${ids.t1}/display`);
        await L.expectText(guest, /Lunch until 1:15/);
        await L.shot(guest, '96-announcement-display');
    });
    await L.step('announcement: organizer clears it', org, async () => {
        await L.clickText(org, /^Edit announcement$/i, 'button');
        await L.clickText(org, /^Clear$/i, 'button');
        await L.expectText(org, /^Announce$/im);
    });

    await L.step('view: running pairings carry a "Playing" status chip and "vs" instead of dashes', guest, async () => {
        await L.visit(guest, `/tournaments/${ids.t1}/view`);
        await L.expectText(guest, /Playing/);
        const t = await L.text(guest);
        if (/–\s*-\s*–/.test(t)) throw new Error('unplayed pairings should not show "– - –"');
        await L.expectText(guest, /\bvs\b/i);
    });

    await L.step('display: paired-but-not-started round says "find your table"; controls fade; QR shown', guest, async () => {
        await L.visit(guest, `/tournaments/${ids.t5}/display`);
        await L.clickText(guest, /^pairings$/i, 'nav button');
        await L.expectText(guest, /Round 1 is paired — find your table/i);
        await guest.page.waitForSelector('img[alt^="QR code"]', { timeout: 9000 });
        await new Promise((r) => setTimeout(r, 6500));
        const op = await guest.page.$eval('nav[aria-label="Display mode"]', (e) => getComputedStyle(e).opacity);
        if (Number(op) > 0.1) throw new Error(`controls should have faded out, opacity ${op}`);
        await guest.page.mouse.move(100, 100);
        await new Promise((r) => setTimeout(r, 800));
        const back = await guest.page.$eval('nav[aria-label="Display mode"]', (e) => getComputedStyle(e).opacity);
        if (Number(back) < 0.9) throw new Error(`controls should be back on movement, opacity ${back}`);
        await L.shot(guest, '97-display-paired');
    });
    await L.step('display: before any result the standings view shows the roster', guest, async () => {
        await L.clickText(guest, /^standings$/i, 'nav button');
        await L.expectText(guest, /First results will appear here/i);
    });

    const wide = await L.session(browser, 'alice@example.test', { width: 1700, height: 1000 });
    await L.step('view: wide screens show standings beside the pairings', wide, async () => {
        await L.visit(wide, `/tournaments/${ids.t1}/view`);
        await L.clickText(wide, /^R1$/i, '[role=tab]');
        await wide.page.waitForSelector('aside[aria-label="Current standings"]', { visible: true, timeout: 9000 });
        await L.shot(wide, '98-view-wide');
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
