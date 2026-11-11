/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const org = await L.session(browser, 'org@example.test');

    // ---------- settings: variants + scoring overrides persist and show to players ----------
    await L.step('settings: toggle self-report, a variant and a scoring override, then save', org, async () => {
        await L.visit(org, `/tournaments/${ids.t2}`);
        await L.clickText(org, /^Settings$/i, '[role=tab]');
        await L.clickText(org, /^Edit$/i);
        await L.clickText(org, /Let players report their own results/i, 'label');
        await L.clickText(org, /Alternating Leaders/i, 'label');
        await L.clickText(org, /Advanced Scoring|Advanced/i, 'button');
        await org.page.click('#score-win_tp', { clickCount: 3 });
        await org.page.type('#score-win_tp', '5');
        await L.clickText(org, /Add differential to TP/i, 'label');
        await L.shot(org, '30-settings-edited');
        await L.clickText(org, /^Save Changes$/i);
        await L.expectText(org, /Player self-reporting\s*On/i);
        await L.shot(org, '31-settings-saved');
    });
    await L.step('settings: players now see the modified scoring and the variant', org, async () => {
        await L.visit(org, `/tournaments/${ids.t2}/view`);
        await L.clickText(org, /Format & rules/i, 'summary');
        await L.expectText(org, /Modified from/i);
        await L.expectText(org, /Alternating Leaders/i);
        await L.expectText(org, /plus your differential/i);
        await L.shot(org, '32-view-modified-rules');
    });

    // ---------- Fields of Glory manage: allegiance declaration + operation/deployment scenario ----------
    await L.step('fog: add an individual player with an allegiance', org, async () => {
        await L.visit(org, `/tournaments/${ids.t3}`);
        await L.clickText(org, /^Players/i, '[role=tab]');
        await L.expectText(org, /Allegiance/i);
        await org.page.type('input[placeholder="Enter player name..."]', 'Ferris');
        await L.pickByTrigger(org, /Select allegiance/i, /./);
        await L.clickText(org, /^Add$/i);
        await L.expectText(org, /1 active players?/i);
        await L.shot(org, '33-fog-player-added');
    });
    await L.step('fog: round scenario editor offers operations and deployments', org, async () => {
        await L.clickText(org, /^Rounds/i, '[role=tab]');
        await L.clickText(org, /^Set Scenario$/i);
        await L.expectText(org, /Operation/i);
        await L.pickByTrigger(org, /Operation/i, /Deep Recovery/i);
        await L.pickByTrigger(org, /Deployment/i, /Corners/i);
        await L.expectText(org, /Saved|Auto-saves/i);
        await L.sleep(1500);
        await L.shot(org, '34-fog-scenario');
        await L.visit(org, `/tournaments/${ids.t3}/view`);
        await L.clickText(org, /^R1$/i, '[role=tab]');
        await L.expectText(org, /Operation:\s*Deep Recovery/i);
        await L.expectText(org, /Deploy:\s*Corners/i);
        await L.shot(org, '35-fog-view-scenario');
    });
    await L.step('fog: public RSVP copy says allegiance, not faction', org, async () => {
        await L.visit(org, `/tournaments/${ids.t3}/view`);
        // the organizer sees the RSVP card too (not RSVPed, not a player)
        await L.expectText(org, /Optionally pick an allegiance/i);
    });

    // ---------- Custom scoring: the diff checkbox works in the create form ----------
    await L.step('create: custom ruleset scoring editor toggles and posts overrides', org, async () => {
        await L.visit(org, '/tournaments/create');
        await L.pickByTrigger(org, /Malifaux/i, /Custom/i);
        await org.page.type('#t-name', 'Custom Cup');
        await org.page.$eval('#t-date', (el) => {
            el.value = '2027-04-01';
            el.dispatchEvent(new Event('input', { bubbles: true }));
        });
        await L.clickText(org, /Advanced Scoring/i, 'button');
        await org.page.click('#score-win_tp', { clickCount: 3 });
        await org.page.type('#score-win_tp', '4');
        await L.clickText(org, /Add differential to TP/i, 'label');
        const on = await org.page.$eval('#score-diff-adds', (e) => e.getAttribute('aria-checked'));
        if (on !== 'true') throw new Error('diff checkbox did not toggle');
        await L.clickText(org, /^Create Tournament$/i);
        await L.expectText(org, /Custom Cup/i);
    });
    await L.step('create: the saved custom event reports its overrides', org, async () => {
        // the single-threaded dev server can be slow to render the manage page after create
        await org.page.waitForFunction(() => [...document.querySelectorAll('[role=tab]')].some((e) => /^Settings$/i.test(e.innerText.trim())), {
            timeout: 25000,
        });
        await L.clickText(org, /^Settings$/i, '[role=tab]');
        await L.expectText(org, /Modified from|Each game is worth/i);
        await L.shot(org, '36-custom-settings');
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
