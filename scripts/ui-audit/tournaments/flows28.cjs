/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Two paths that need an in-progress tracker game: the public "Live now" strip, and Game Tracker -> Crew Builder -> back with the crew attached.
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const guest = await L.session(browser, null, { width: 390, height: 844 });
    const dave = await L.session(browser, 'dave@example.test', { width: 390, height: 844 });

    await L.step('live: the event page lists the tracker game being played, with its table and turn', guest, async () => {
        await L.visit(guest, `/tournaments/${ids.t11}/view`);
        await L.expectText(guest, /Live now/i);
        await L.expectText(guest, /Alice/);
        const link = await guest.page.evaluate(() =>
            [...document.querySelectorAll('section[aria-label="Games in progress"] a')].map((a) => a.getAttribute('href')),
        );
        if (!link.length || !link.every((h) => /\/games\//.test(h))) throw new Error(`expected links to tracker games, got ${JSON.stringify(link)}`);
        await L.shot(guest, '280-live-now');
    });
    await L.step('live: only the game with a tracker appears (the other pairing has none)', guest, async () => {
        const count = await guest.page.evaluate(() => document.querySelectorAll('section[aria-label="Games in progress"] li').length);
        if (count !== 1) throw new Error(`expected exactly 1 live game, found ${count}`);
    });

    await L.step('crew: the builder offers a return bar when opened from a game', dave, async () => {
        const url = `/tools/crew-builder/editor?build=${ids.crew_build}&from_game=${ids.crew_game}&from_slot=1`;
        await L.visit(dave, url);
        await L.expectText(dave, /Use this crew in game/i);
        await L.expectText(dave, /Back to game without a crew/i);
        await L.shot(dave, '281-crew-builder-from-game');
    });
    await L.step('crew: "Use this crew in game" attaches it and lands back on the game', dave, async () => {
        await dave.page.waitForFunction(
            () => [...document.querySelectorAll('button')].some((b) => /Use this crew in game/i.test(b.innerText) && !b.disabled),
            { timeout: 9000 },
        );
        await L.clickText(dave, /Use this crew in game/i, 'button');
        await dave.page.waitForFunction((uuid) => location.pathname === `/games/${uuid}`, { timeout: 12000 }, ids.crew_game);
        await L.shot(dave, '282-back-on-game');
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
    for (const r of L.results) console.log(`${r.status} ${r.name}${r.detail ? '\n       ' + r.detail : ''}`);
    if (bad.length) process.exit(1);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
