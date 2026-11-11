/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const org = await L.session(browser, 'org@example.test');

    await L.step('team form: search any account by name, pick it, and see it marked unavailable once taken', org, async () => {
        await L.visit(org, `/tournaments/${ids.t2}`);
        await L.clickText(org, /^Players/i, '[role=tab]');
        await org.page.type('input[placeholder="Enter team name..."]', 'Searchers');
        await org.page.type('input[aria-label="Search accounts for member 2"]', 'Dav');
        await L.expectText(org, /Dave/);
        await L.shot(org, '60-team-search');
        await L.clickText(org, /^Dave$/, 'button');
        // the member name auto-fills from the account
        const name = await org.page.$eval('#team-member-1-name', (e) => e.value);
        if (name !== 'Dave') throw new Error(`expected the name to fill from the account, got "${name}"`);
    });
    await L.step('team form: complete and add the team; header and guide say "teams"', org, async () => {
        await org.page.type('#team-member-0-name', 'Solo Sam');
        await L.pickSelect(org, 1, /Arcanists/i); // member 1 faction (combobox 0 is member 1's account)
        await L.pickSelect(org, 3, /Guild/i); // member 2 faction
        await L.clickText(org, /^Add team$/i);
        await L.expectText(org, /1 active teams?/i);
        await L.expectText(org, /1 team is in\./i);
        await L.shot(org, '61-team-added-via-search');
    });
    await L.step('team form: a taken account shows as already playing in the next search', org, async () => {
        await org.page.type('input[aria-label="Search accounts for member 1"]', 'Dav');
        await L.expectText(org, /already playing/i);
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
