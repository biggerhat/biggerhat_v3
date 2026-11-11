/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// The organizer's "next step" card (NextStepCard + useTournamentNextStep) across tournament states.
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const org = await L.session(browser, 'org@example.test');

    await L.step('next step: running event shows progress strip and games-reported count', org, async () => {
        await L.visit(org, `/tournaments/${ids.t1}`);
        await L.expectText(org, /Round 1 is in progress/i);
        await L.expectText(org, /0 of 2 games reported/i);
        const current = await org.page.$eval('ol[aria-label="Tournament progress"] li[aria-current=step]', (e) => e.innerText.trim());
        if (!/Play/.test(current)) throw new Error(`expected Play as current stage, got "${current}"`);
        await L.shot(org, '80-next-step-running');
    });
    await L.step('next step: "View games" switches to the rounds tab', org, async () => {
        await L.clickText(org, /^View games$/i, 'button');
        await L.expectText(org, /Round 1/i);
    });
    await L.step('next step: team event in registration says teams, button opens the confirm dialog', org, async () => {
        await L.visit(org, `/tournaments/${ids.t2}`);
        await L.expectText(org, /Registration is open/i);
        await L.expectText(org, /\d+ teams? (is|are) in/i);
        await L.clickText(org, /Start Tournament/i, 'button');
        await L.expectText(org, /Cancel/i);
        await L.shot(org, '81-next-step-registration');
    });
    await L.step('next step: round-less active event offers Add Round 1', org, async () => {
        await L.visit(org, `/tournaments/${ids.t3}`);
        await L.expectText(org, /Registration is open|Set up your tournament|Add Round 1/i);
        await L.shot(org, '82-next-step-fog');
    });

    await L.step('display: shows a live / reconnecting status (no websocket here, so it falls back to polling)', org, async () => {
        await L.visit(org, `/tournaments/${ids.t1}/display`);
        await org.page.waitForSelector('[role=status]', { timeout: 9000 });
        await L.expectText(org, /Live|Reconnecting/i);
        await L.shot(org, '84-display-status');
    });

    const phone = await L.session(browser, 'org@example.test', { width: 390, height: 800 });
    await L.step('next step: card fits at phone width', phone, async () => {
        await L.visit(phone, `/tournaments/${ids.t1}`);
        await L.expectText(phone, /Round 1 is in progress/i);
        const overflow = await phone.page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        if (overflow > 1) throw new Error(`horizontal overflow ${overflow}px`);
        await L.shot(phone, '83-next-step-phone');
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
