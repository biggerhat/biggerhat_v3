/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Crews are chosen per round: a player picks the crew for their game, can change it round to round, the organizer sees it.
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const alice = await L.session(browser, 'alice@example.test');
    const org = await L.session(browser, 'org@example.test');

    await L.step("crew: Alice picks the crew she is bringing to Round 1 (only her faction's crews are offered)", alice, async () => {
        await L.visit(alice, `/tournaments/${ids.t8}/view`);
        await L.expectText(alice, /Your crew for Round 1/i);
        await L.expectText(alice, /pick the crew you are bringing to this game/i);
        await L.shot(alice, '130-crew-picker');
        await L.pickByTrigger(alice, /Choose one of your crews/i, /Alice Guild 40/i);
        const options = await alice.page.$$eval('[role=option]', (os) => os.map((o) => o.innerText.trim())).catch(() => []);
        if (options.length) await alice.page.keyboard.press('Escape');
        await L.clickText(alice, /^Use this crew$/i, 'button');
        await L.expectText(alice, /Bringing: Alice Guild 40/i);
        await L.expectText(alice, /built for 40ss; this event is 50ss/i);
        await L.shot(alice, '131-crew-chosen');
    });
    await L.step('crew: an Arcanists crew is not offered to a Guild player', alice, async () => {
        await L.clickText(alice, /Alice Guild 40/i, 'button[role=combobox]');
        const options = await alice.page.$$eval('[role=option]', (os) => os.map((o) => o.innerText.trim()));
        await alice.page.keyboard.press('Escape');
        if (options.length !== 2 || options.some((o) => /Arcanists/.test(o))) throw new Error(`unexpected options: ${options.join(' | ')}`);
    });
    await L.step("organizer: the Rounds tab shows each side's crew with its flag, and who has none", org, async () => {
        await L.visit(org, `/tournaments/${ids.t8}`);
        await L.clickText(org, /^Rounds/i, '[role=tab]');
        await L.expectText(org, /Alice: .* · 40ss/i);
        await L.expectText(org, /Built for 40ss; the event is 50ss/i);
        await L.expectText(org, /Bob: no crew picked/i);
        await L.shot(org, '132-crew-organizer');
    });
    await L.step('organizer: Start Round warns about the missing crew, and can start anyway', org, async () => {
        await L.clickText(org, /^Start Round$/i, 'button');
        await L.expectText(org, /1 player\(s\) haven't chosen a crew/i);
        await L.shot(org, '133-start-warning');
        await L.clickText(org, /^Start anyway$/i, 'button');
        await L.expectText(org, /Round 1 is in progress/i);
    });
    await L.step('crew: Alice can still change her crew while the game is being played', alice, async () => {
        await L.visit(alice, `/tournaments/${ids.t8}/view`);
        await L.clickText(alice, /Alice Guild 40/i, 'button[role=combobox]');
        await L.clickText(alice, /Alice Guild 50/i, '[role=option]');
        await L.clickText(alice, /^Update$/i, 'button');
        await L.expectText(alice, /Bringing: Alice Guild 50/i);
        const t = await L.text(alice);
        if (/built for 40ss/i.test(t)) throw new Error('the size flag should be gone after switching to the 50ss crew');
    });
    await L.step('organizer: opens the private crew and sees the flag cleared', org, async () => {
        await L.visit(org, `/tournaments/${ids.t8}`);
        await L.clickText(org, /^Rounds/i, '[role=tab]');
        await L.expectText(org, /Alice: .* · 50ss/i);
        const t = await L.text(org);
        if (/Built for 40ss/i.test(t)) throw new Error('the organizer still sees the old flag');
        const href = await org.page.$eval('a[href*="/crew-builder/share/"]', (a) => a.getAttribute('href'));
        await L.visit(org, new URL(href, L.BASE).pathname);
        await L.expectText(org, /Alice Guild 50/);
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
