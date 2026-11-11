/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Side awards: the organizer names one and gives it to a player; the public page and the winner see it.
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const org = await L.session(browser, 'org@example.test');
    const guest = await L.session(browser, null);
    const alice = await L.session(browser, 'alice@example.test');

    await L.step('awards: the Details tab has a side-awards editor with ideas', org, async () => {
        await L.visit(org, `/tournaments/${ids.t1}`);
        await L.clickText(org, /^Details$/i, '[role=tab]');
        await org.page.waitForSelector('[data-testid="awards-editor"]', { timeout: 9000 });
        await L.expectText(org, /Best Sportsmanship/i);
        await L.shot(org, '180-awards-editor');
    });
    await L.step('awards: an idea fills the name, the organizer picks a winner and adds it', org, async () => {
        await L.clickText(org, /^Best Painted$/i, 'button');
        await L.pickByTrigger(org, /Not awarded yet/i, /^Alice$/);
        await org.page.type('input[aria-label="Award note"]', 'Ten Thunders crew');
        await L.clickText(org, /^Add award$/i);
        await L.expectText(org, /Award added/i);
        await L.expectText(org, /Save award/i, 'the award should be listed as saved');
    });
    await L.step('awards: everyone sees it on the public page', guest, async () => {
        await L.visit(guest, `/tournaments/${ids.t1}/view`);
        await guest.page.waitForSelector('[data-testid="awards"]', { timeout: 9000 });
        await L.expectText(guest, /Best Painted/);
        await L.expectText(guest, /Alice/);
        await L.expectText(guest, /Ten Thunders crew/);
        await L.shot(guest, '181-awards-public');
    });
    await L.step('awards: the winner is notified', alice, async () => {
        await L.visit(alice, '/notifications');
        await L.expectText(alice, /You won "Best Painted"/i);
    });
    await L.step('awards: deleting asks first and removes it from the public page', org, async () => {
        await L.visit(org, `/tournaments/${ids.t1}`);
        await L.clickText(org, /^Details$/i, '[role=tab]');
        await L.clickText(org, /^Delete$/i, 'button');
        await L.clickText(org, /^Delete$/i, '[role=alertdialog] button, [role=dialog] button');
        await L.expectText(org, /Award deleted/i);
        await L.visit(guest, `/tournaments/${ids.t1}/view`);
        const has = await guest.page.evaluate(() => !!document.querySelector('[data-testid="awards"]'));
        if (has) throw new Error('awards card should be gone');
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
    if (bad.length) process.exit(1);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
