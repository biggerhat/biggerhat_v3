/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Crew lists: a player submits one of their saved crews, the organizer sees it, flags and missing lists.
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const alice = await L.session(browser, 'alice@example.test');
    const org = await L.session(browser, 'org@example.test');

    await L.step('crew list: Alice picks one of her crews and it is submitted', alice, async () => {
        await L.visit(alice, `/tournaments/${ids.t8}/view`);
        await L.expectText(alice, /Your crew list/i);
        await L.expectText(alice, /The organizer asks for your crew/i);
        await L.shot(alice, '130-crew-picker');
        await L.pickByTrigger(alice, /Choose one of your crews/i, /Alice Arcanists 40/i);
        await L.clickText(alice, /^Submit$/i, 'button');
        await L.expectText(alice, /Submitted: Alice Arcanists 40/i);
        await L.expectText(alice, /built for 40ss; this event is 50ss/i);
        await L.shot(alice, '131-crew-submitted');
    });
    await L.step('crew list: after submitting, only crews of her (now declared) faction are offered', alice, async () => {
        await L.pickByTrigger(alice, /Alice Arcanists 40/i, /Alice Arcanists 40/i).catch(() => {});
        const options = await alice.page.$$eval('[role=option]', (os) => os.map((o) => o.innerText.trim()));
        if (options.some((o) => /Guild/.test(o))) throw new Error(`a Guild crew was offered to an Arcanists player: ${options.join(' | ')}`);
        await alice.page.keyboard.press('Escape');
    });
    await L.step('organizer: the Players tab shows the crew with its flag, and who has none', org, async () => {
        await L.visit(org, `/tournaments/${ids.t8}`);
        await L.clickText(org, /^Players/i, '[role=tab]');
        await L.expectText(org, /Crew: .* · 40ss/i);
        await L.expectText(org, /Built for 40ss; the event is 50ss/i);
        await L.expectText(org, /No crew list submitted/i);
        await L.shot(org, '132-crew-organizer');
    });
    await L.step('organizer: starting the tournament warns about missing lists', org, async () => {
        await L.clickText(org, /^Start Tournament/i, 'button');
        await L.expectText(org, /1 player\(s\) haven't submitted a crew list yet: Bob/i);
        await L.shot(org, '133-start-warning');
        await L.clickText(org, /^Cancel$/i, 'button');
    });
    await L.step('organizer: opens the private crew from the Players tab', org, async () => {
        const href = await org.page.$eval('a[href*="/crew-builder/share/"]', (a) => a.getAttribute('href'));
        await L.visit(org, new URL(href, L.BASE).pathname);
        const t = await L.text(org);
        if (/private/i.test(t) && !/Alice Arcanists 40/.test(t)) throw new Error('the organizer was shown the Private placeholder');
        await L.expectText(org, /Alice Arcanists 40/);
    });
    await L.step('crew list: Alice removes it again', alice, async () => {
        await L.visit(alice, `/tournaments/${ids.t8}/view`);
        await L.clickText(alice, /^Remove$/i, 'button');
        await L.expectText(alice, /The organizer asks for your crew/i);
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
