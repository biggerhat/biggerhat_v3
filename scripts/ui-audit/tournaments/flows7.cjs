/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const org = await L.session(browser, 'org@example.test');

    await L.step('ringer team: tick "Ringer team", add it with no members', org, async () => {
        await L.visit(org, `/tournaments/${ids.t2}`);
        await L.clickText(org, /^Players/i, '[role=tab]');
        await org.page.type('input[placeholder="Enter team name..."]', 'Ringer Squad');
        await L.clickText(org, /Ringer team/i, 'label');
        const hidden = await L.text(org);
        if (/Member 1/.test(hidden)) throw new Error('member editor should be hidden for a ringer team');
        await L.shot(org, '70-ringer-form');
        await L.clickText(org, /^Add team$/i);
        await L.expectText(org, /1 active teams?/i);
        await L.expectText(org, /Ringer Squad/);
        await L.shot(org, '71-ringer-added');
    });
    await L.step('ringer team: the edit dialog saves a rename without members', org, async () => {
        await L.clickText(org, /^Edit$/i, 'button');
        await L.expectText(org, /Edit team/i);
        await org.page.click('[role=dialog] input', { clickCount: 3 });
        await org.page.keyboard.type('Ringer Squad II');
        await L.shot(org, '72-ringer-edit');
        await L.clickText(org, /^Save team$/i);
        await L.expectText(org, /Ringer Squad II/);
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
