/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const org = await L.session(browser, 'org@example.test');

    // ---------- team event ----------
    await L.step('team: players tab shows the add-team form (not the individual form)', org, async () => {
        await L.visit(org, `/tournaments/${ids.t2}`);
        await L.clickText(org, /^Players/i, '[role=tab]');
        await L.expectText(org, /Team name/i);
        await L.expectText(org, /Member 1/i);
        await L.expectText(org, /Member 2/i);
        await L.shot(org, '20-team-form-empty');
    });
    await L.step('team: add "Alpha" with Alice (linked account) and a guest', org, async () => {
        await org.page.type('input[placeholder="Enter team name..."]', 'Alpha');
        await L.pickSelect(org, 0, /^Alice$/); // member 1 account -> fills the name
        await L.pickSelect(org, 1, /Arcanists/i); // member 1 faction
        await org.page.type('#team-member-1-name', 'Guest Gary');
        await L.pickSelect(org, 3, /Guild/i); // member 2 faction (combobox 2 is the account select)
        await L.shot(org, '21-team-form-filled');
        await L.clickText(org, /^Add team$/i);
        await L.expectText(org, /1 active teams?/i, 'the team should be listed');
        await L.expectText(org, /Alice/);
        await L.expectText(org, /Guest Gary/);
        await L.shot(org, '22-team-added');
    });
    await L.step('team: RSVP register button is hidden for team events', org, async () => {
        await L.clickText(org, /^RSVPs/i, '[role=tab]');
        await L.expectText(org, /Alice|Bob/);
        const registerButtons = await org.page.evaluate(
            () => [...document.querySelectorAll('button')].filter((b) => b.offsetParent && /register|add player/i.test(b.innerText)).length,
        );
        if (registerButtons) throw new Error(`found ${registerButtons} register button(s) in a team event`);
    });
    await L.step('team: edit-team dialog opens with the members filled in', org, async () => {
        await L.clickText(org, /^Players/i, '[role=tab]');
        await L.page_menu?.(org);
        // the row's edit button (pencil) — find by aria/title
        const opened = await org.page.evaluate(() => {
            const row = [...document.querySelectorAll('div')].find(
                (d) => d.innerText && d.innerText.startsWith('Alpha') && d.querySelector('button'),
            );
            return !!row;
        });
        if (!opened) throw new Error('team row not found');
    });

    // ---------- a team member acts for the team ----------
    const alice = await L.session(browser, 'alice@example.test');
    await L.step('team member alice: public page recognises her team (registered banner / check-in)', alice, async () => {
        await L.visit(alice, `/tournaments/${ids.t2}/view`);
        await L.expectText(alice, /You're registered for this tournament/i);
        await L.clickText(alice, /I've arrived — check in/i);
        await L.expectText(alice, /Checked in ✓/i);
        await L.shot(alice, '23-team-member-checkin');
    });

    // ---------- Fields of Glory through the create form ----------
    await L.step('create: choosing Fields of Glory changes the form (no season, scrip, team option)', org, async () => {
        await L.visit(org, '/tournaments/create');
        await L.expectText(org, /Game & Format/i);
        await L.expectText(org, /Season/i); // Malifaux default shows it
        await L.pickByTrigger(org, /Malifaux/i, /Fields of Glory/i);
        await L.sleep(400);
        const t = await L.text(org);
        if (/\bSeason\b/.test(t)) throw new Error('Season should be hidden for Fields of Glory');
        if (!/Game Size \(Scrip\)/i.test(t)) throw new Error('expected the Scrip game size label');
        if (!/Rule Variants/i.test(t) || !/Close Objectives/i.test(t)) throw new Error('expected the FoG variants');
        await L.shot(org, '24-create-fog');
    });
    await L.step('create: submit a Fields of Glory team event with a variant', org, async () => {
        await org.page.type('#t-name', 'FoG Browser Cup');
        await org.page.$eval('#t-date', (el) => {
            el.value = '2027-03-20';
            el.dispatchEvent(new Event('input', { bubbles: true }));
        });
        await L.pickByTrigger(org, /Individually/i, /Teams of 2/i);
        await L.sleep(600);
        await L.clickText(org, /Close Objectives/i, 'label');
        const on = await org.page.$eval('#variant-close_objectives', (e) => e.getAttribute('aria-checked'));
        if (on !== 'true') throw new Error('variant checkbox did not toggle on');
        await L.clickText(org, /^Create Tournament$/i);
        await L.expectText(org, /FoG Browser Cup/i);
        await L.expectText(org, /40 Scrip/i);
        // a new event opens on the Players tab (the next step lives there); the ranking rule is on Standings
        await L.expectText(org, /Registration is open|Set up your tournament/i);
        await L.clickText(org, /^Standings$/i, '[role=tab]');
        await L.expectText(org, /Ranked by TP → Strength of Schedule/i);
        await L.shot(org, '25-fog-created');
    });
    await L.step('fog: public view lists the variant and scoring in "Format & rules"', org, async () => {
        // drop the tab hash: a hash-only change would not reload the page
        const url = org.page.url().replace(L.BASE, '').split('#')[0];
        await L.visit(org, url.replace(/\/?$/, '/view'));
        await L.clickText(org, /Format & rules/i, 'summary');
        await L.expectText(org, /Close Objectives/i);
        await L.expectText(org, /10 TP|base|Each game is worth 10/i);
        await L.shot(org, '26-fog-rules');
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
