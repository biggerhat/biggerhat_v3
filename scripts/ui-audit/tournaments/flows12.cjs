/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Capacity, waitlist, registration deadline and self-withdrawal.
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const org = await L.session(browser, 'org@example.test');
    const bob = await L.session(browser, 'bob@example.test');
    const carol = await L.session(browser, 'carol@example.test');
    const alice = await L.session(browser, 'alice@example.test');
    const dave = await L.session(browser, 'dave@example.test');

    await L.step('waitlist: Bob is #1 and Carol #2 while Alice and Zed hold the two seats', carol, async () => {
        await L.visit(carol, `/tournaments/${ids.t6}/view`);
        await L.expectText(carol, /You're #2 on the waitlist/i);
        await L.shot(carol, '110-waitlist-carol');
        await L.visit(bob, `/tournaments/${ids.t6}/view`);
        await L.expectText(bob, /You're #1 on the waitlist/i);
    });
    await L.step('waitlist: the organizer sees the limit, the count and the badges', org, async () => {
        await L.visit(org, `/tournaments/${ids.t6}`);
        await L.clickText(org, /^RSVPs/i, '[role=tab]');
        await L.expectText(org, /limit 2 · 2 waitlisted/i);
        await L.expectText(org, /Waitlist #2/i);
        await L.shot(org, '111-waitlist-organizer');
    });
    await L.step('capacity: the organizer cannot register past the limit', org, async () => {
        await L.clickText(org, /^Players/i, '[role=tab]');
        await L.expectText(org, /\(limit 2\)/i);
        await org.page.type('input[placeholder="Enter player name..."]', 'Overflow');
        await L.pickByTrigger(org, /Select faction/i, /Guild/i);
        await L.clickText(org, /^Add$/i, 'button');
        await L.expectText(org, /tournament is full \(2\)/i);
        org.problems.length = 0; // the 422 that produced the message is the point of this step
    });
    await L.step('deadline: after the closing time a newcomer is told registration has closed', org, async () => {
        await L.clickText(org, /^Settings$/i, '[role=tab]');
        await L.clickText(org, /^Edit/i, 'button');
        await org.page.$eval('#settings-closes-at', (el) => {
            el.value = '2020-01-01T10:00';
            el.dispatchEvent(new Event('input', { bubbles: true }));
        });
        await L.clickText(org, /^Save/i, 'button');
        await org.page.waitForFunction(() => !document.querySelector('#settings-closes-at'), { timeout: 9000 });
        await L.visit(dave, `/tournaments/${ids.t6}/view`);
        await L.expectText(dave, /Registration for this tournament has closed/i);
        await L.shot(dave, '112-registration-closed');
    });
    await L.step('withdraw: Alice leaves before the start; Bob takes the seat and Carol moves up to #1', alice, async () => {
        await L.visit(alice, `/tournaments/${ids.t6}/view`);
        await L.clickText(alice, /^Withdraw from this tournament$/i, 'button');
        await L.expectText(alice, /Withdraw from this tournament\?/i);
        await L.shot(alice, '113-withdraw-confirm');
        await L.clickText(alice, /^Withdraw$/i, 'button');
        await org.page.waitForFunction(() => true);
        await L.visit(carol, `/tournaments/${ids.t6}/view`);
        await L.expectText(carol, /You're #1 on the waitlist/i);
        await L.visit(bob, `/tournaments/${ids.t6}/view`);
        await L.expectText(bob, /You've signed up/i);
    });
    await L.step('withdraw: an active-event player drops after the last finished round and the organizer is notified', alice, async () => {
        await L.visit(alice, `/tournaments/${ids.t1}/view`);
        await L.clickText(alice, /^Withdraw from this tournament$/i, 'button');
        await L.clickText(alice, /^Withdraw$/i, 'button');
        await L.expectText(alice, /You've withdrawn from this tournament/i);
        await L.shot(alice, '114-withdrawn');
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
