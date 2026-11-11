/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
(async () => {
    const browser = await L.launch();
    const { ids } = L;

    // ---- guest: public view + display
    const guest = await L.session(browser, null);
    await L.step('guest: tournament view renders pairings', guest, async () => {
        await L.visit(guest, `/tournaments/${ids.t1}/view`);
        await L.expectText(guest, /GG Open/);
        await L.expectText(guest, /Alice/);
        await L.expectText(guest, /Format & rules/i);
        await L.shot(guest, '01-view-guest');
    });
    await L.step('guest: display mode shows tables, then standings', guest, async () => {
        await L.visit(guest, `/tournaments/${ids.t1}/display`);
        await L.expectText(guest, /GG Open/);
        await L.expectText(guest, /Table/i);
        await L.shot(guest, '02-display-pairings');
        await L.clickText(guest, /^standings$/i);
        await L.expectText(guest, /First results will appear here|Strength|SoS|TP/i);
        await L.shot(guest, '03-display-standings');
    });

    // ---- self-report between two real accounts
    const alice = await L.session(browser, 'alice@example.test');
    const bob = await L.session(browser, 'bob@example.test');
    await L.step('alice: sees her match card with a Report result button', alice, async () => {
        await L.visit(alice, `/tournaments/${ids.t1}/view`);
        await L.expectText(alice, /Your match/i);
        await L.shot(alice, '04-alice-match');
        await L.clickText(alice, /^Report result$/i);
        await L.expectText(alice, /Submit result/i);
    });
    await L.step('alice: submits a result', alice, async () => {
        const set = async (id, v) => {
            await alice.page.click(id, { clickCount: 3 });
            await alice.page.type(id, String(v));
        };
        await set('#rep-mine-strategy', 4);
        await set('#rep-mine-scheme', 3);
        await set('#rep-theirs-strategy', 1);
        await set('#rep-theirs-scheme', 2);
        await L.shot(alice, '05-alice-form');
        await L.clickText(alice, /^Submit result$/i);
        await L.sleep(1200);
        await L.expectText(alice, /You reported\s*7\s*–\s*3/i, 'alice should see her pending report');
        await L.shot(alice, '06-alice-pending');
    });
    await L.step('bob: sees the proposal and confirms it', bob, async () => {
        await L.visit(bob, `/tournaments/${ids.t1}/view`);
        await L.expectText(bob, /Alice reported this result[\s\S]*3[\s\S]*7/i, 'bob should see the proposal from his side (3 – 7)');
        await L.shot(bob, '07-bob-proposal');
        await L.clickText(bob, /^Confirm$/i);
        await L.sleep(1200);
        await L.expectText(bob, /Result recorded|3\s*–\s*7/i);
        await L.shot(bob, '08-bob-confirmed');
    });

    // ---- organizer: manage T1
    const org = await L.session(browser, 'org@example.test');
    await L.step('organizer: manage page loads with tabs and the confirmed score', org, async () => {
        await L.visit(org, `/tournaments/${ids.t1}`);
        await L.expectText(org, /GG Open/);
        await L.clickText(org, /^Rounds/i, '[role=tab]');
        await L.expectText(org, /7\s*–\s*3|Reported/i);
        await L.shot(org, '09-manage-rounds');
    });
    await L.step('organizer: score dialog opens for the other game', org, async () => {
        await L.clickText(org, /^Enter score$/i);
        await L.expectText(org, /Game Score/i);
        await L.expectText(org, /Strategy VP/i);
        await L.shot(org, '10-score-dialog');
        await L.clickText(org, /^Cancel$/i);
    });
    await L.step('organizer: players tab, check in all, edit dialog', org, async () => {
        await L.clickText(org, /^Players/i, '[role=tab]');
        await L.expectText(org, /4 active players/i);
        await L.clickText(org, /Check in all/i);
        await L.sleep(900);
        await L.expectText(org, /4 checked in/i);
        await L.shot(org, '11-players-checked-in');
    });
    await L.step('organizer: settings are locked on a running event', org, async () => {
        await L.clickText(org, /^Settings$/i, '[role=tab]');
        await L.expectText(org, /locked once the tournament has started/i);
        await L.shot(org, '12a-settings-locked');
    });
    await L.step('organizer: settings edit form opens on a registration-stage event', org, async () => {
        await L.visit(org, `/tournaments/${ids.t2}`);
        await L.clickText(org, /^Settings$/i, '[role=tab]');
        await L.clickText(org, /^Edit$/i);
        await L.expectText(org, /Let players report their own results/i);
        await L.expectText(org, /Rule Variants/i);
        await L.expectText(org, /Teams of|Individually/i);
        await L.shot(org, '12-settings-form');
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
