/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const alice = await L.session(browser, 'alice@example.test');
    const carol = await L.session(browser, 'carol@example.test');
    const org = await L.session(browser, 'org@example.test');

    await L.step('team event: a member sees the team match card (team names, members)', alice, async () => {
        await L.visit(alice, `/tournaments/${ids.t4}/view`);
        await L.expectText(alice, /Your match/i);
        await L.expectText(alice, /Bravo Crew/);
        await L.expectText(alice, /Alpha Squad/);
        await L.expectText(alice, /Alpha Squad — Alice · Bob/);
        await L.shot(alice, '50-team-match');
    });
    await L.step('team event: alice reports for Alpha Squad', alice, async () => {
        await L.clickText(alice, /^Report result$/i);
        const set = async (id, v) => {
            await alice.page.click(id, { clickCount: 3 });
            await alice.page.keyboard.press('Backspace');
            await alice.page.type(id, String(v));
        };
        await set('#rep-mine-strategy', 5);
        await set('#rep-mine-scheme', 4);
        await set('#rep-theirs-strategy', 2);
        await set('#rep-theirs-scheme', 1);
        await L.clickText(alice, /^Submit result$/i);
        await L.expectText(alice, /You reported\s*9\s*–\s*3/i);
    });
    await L.step('team event: carol (other team) confirms; standings update for teams', carol, async () => {
        await L.visit(carol, `/tournaments/${ids.t4}/view`);
        await L.expectText(carol, /Alpha Squad reported this result[\s\S]*3[\s\S]*9/i);
        await L.clickText(carol, /^Confirm$/i);
        await L.sleep(1500);
        await L.clickText(carol, /^Standings/i, '[role=tab]');
        await L.expectText(carol, /Alpha Squad/);
        await L.shot(carol, '51-team-standings');
    });
    await L.step('team event: organizer sees the team result and the members on the players tab', org, async () => {
        await L.visit(org, `/tournaments/${ids.t4}`);
        await L.clickText(org, /^Players/i, '[role=tab]');
        await L.expectText(org, /Alpha Squad/);
        await L.expectText(org, /Alice \(Arcanists\)/i);
        await L.shot(org, '52-team-players');
        await L.clickText(org, /^Edit$/i, 'button');
        await L.expectText(org, /Edit team/i);
        await L.expectText(org, /Member 2/i);
        await L.shot(org, '53-team-edit');
        await L.clickText(org, /^Cancel$/i);
    });
    await L.step('team event: score dialog hides the master/title picker for teams', org, async () => {
        await L.visit(org, `/tournaments/${ids.t4}`);
        await L.clickText(org, /^Edit score$/i);
        await L.expectText(org, /Game Score/i);
        const t = await L.text(org);
        if (/Select master/i.test(t)) throw new Error('master picker should be hidden for team events');
        await L.shot(org, '54-team-score-dialog');
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
