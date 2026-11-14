/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Game Tracker pre-game setup at phone width: sticky Create button, stepper-first layout, floating confirms, join redirect, skipping a crew.
(async () => {
    const browser = await L.launch();
    const a = await L.session(browser, 'alice@example.test', { width: 390, height: 844 });
    const b = await L.session(browser, 'bob@example.test', { width: 390, height: 844 });
    let url;
    const inViewport = (o, sel) =>
        o.page.evaluate((s) => {
            const el = document.querySelector(s);
            if (!el) return null;
            const r = el.getBoundingClientRect();
            return r.top >= 0 && r.bottom <= window.innerHeight;
        }, sel);
    const headingTop = (o, re) =>
        o.page.evaluate((src) => {
            const h = [...document.querySelectorAll('h2')].find((x) => new RegExp(src, 'i').test(x.innerText));
            return h ? Math.round(h.getBoundingClientRect().top + window.scrollY) : null;
        }, re.source);

    await L.step('create: the Create Game button is on screen without scrolling', a, async () => {
        await L.visit(a, '/games/create');
        await L.sleep(500);
        const btn = await a.page.evaluate(() => {
            const b = [...document.querySelectorAll('button')].find((x) => /^Create Game$/i.test(x.innerText.trim()));
            const r = b.getBoundingClientRect();
            return { top: r.top, bottom: r.bottom, h: window.innerHeight };
        });
        if (btn.bottom > btn.h) throw new Error(`button bottom ${btn.bottom} is below the viewport ${btn.h}`);
        await L.shot(a, '290-create-sticky');
        await L.clickText(a, /^Create Game$/i, 'button');
        await L.sleep(2200);
        url = a.page.url().replace(/^https?:\/\/[^/]+/, '');
        if (!/^\/games\/[0-9a-f-]{36}$/.test(url)) throw new Error('did not land on the game: ' + url);
        await L.expectText(a, /assigned at random when they join/i);
    });
    await L.step('join: opening the game address as a non-player lands on the invite page, not a 403, and does not seat them', b, async () => {
        await L.visit(b, url);
        await L.expectText(b, /You have been invited/i);
        await L.expectText(b, /assigned at random when you join/i);
        // The host's lobby is still waiting: opening the invite did not take the second seat.
        await L.visit(a, url);
        await L.expectText(a, /assigned at random when they join/i);
        await L.shot(b, '289-invite-page');
    });
    await L.step('join: the Join game button takes the second seat', b, async () => {
        await L.clickText(b, /^Join game$/i, 'button');
        await L.expectText(b, /Select Your Faction/i);
    });
    await L.step('layout: the faction step starts high on the page and the scenario is collapsed', b, async () => {
        const top = await headingTop(b, /Select Your Faction/);
        if (top === null || top > 560) throw new Error(`faction heading starts ${top}px down`);
        const open = await b.page.evaluate(() => document.querySelector('[data-testid=scenario-summary]')?.open);
        if (open !== false) throw new Error('scenario summary should be collapsed during a select step, got ' + open);
        await L.shot(b, '291-faction-step-top');
    });
    await L.step('faction: picking a tile shows the floating confirm bar in view', b, async () => {
        await L.clickText(b, /^Arcanists$/i, 'button');
        await L.sleep(400);
        if (!(await inViewport(b, '[data-testid=faction-confirm-bar]'))) throw new Error('confirm bar not fully in the viewport');
        await L.shot(b, '292-faction-bar');
        await L.clickText(b, /^Confirm$/i, 'button');
        await L.sleep(1200);
    });
    await L.step('faction: the host picks too (no websocket here, so reload first)', a, async () => {
        await L.visit(a, url);
        await L.clickText(a, /^Arcanists$/i, 'button');
        await L.clickText(a, /^Confirm$/i, 'button');
        await L.sleep(1200);
        const remembered = await a.page.evaluate(() => localStorage.getItem('game-last-faction'));
        if (remembered !== 'arcanists') throw new Error('the confirmed faction should be remembered, got ' + remembered);
    });
    for (const [o, name] of [
        [a, 'host'],
        [b, 'guest'],
    ]) {
        await L.step(`master: ${name} picks Rasputina with the keyboard-reachable card`, o, async () => {
            await L.visit(o, url);
            await L.expectText(o, /Select Your Master/i);
            const focusable = await o.page.evaluate(
                () => !![...document.querySelectorAll('[role=button][tabindex="0"]')].find((e) => /Rasputina/.test(e.innerText)),
            );
            if (!focusable) throw new Error('master card is not a focusable button');
            await L.clickText(o, /Rasputina/i, '[role=button]');
            await L.clickText(o, /^Confirm$/i, 'button');
            await L.sleep(1200);
        });
    }
    await L.step('crew: a player with no saved crew can skip it and track points only', a, async () => {
        await L.visit(a, url);
        await L.expectText(a, /Select Your Crew/i);
        await L.expectText(a, /Playing from a paper list/i);
        await L.shot(a, '293-crew-skip');
        await L.clickText(a, /Skip crew, track points only/i, 'button');
        await L.sleep(1200);
        await L.expectText(a, /Waiting|Crew selected/i);
    });
    await L.step('crew: once the other player skips too the game moves on to schemes', b, async () => {
        await L.visit(b, url);
        await L.clickText(b, /Skip crew, track points only/i, 'button');
        await L.sleep(1500);
        await L.visit(b, url);
        await L.expectText(b, /Scheme/i);
        const text = await b.page.evaluate(() => document.body.innerText);
        if (!/Crew not tracked/i.test(text)) throw new Error('the scheme step should list both crews as not tracked');
        await L.shot(b, '294-scheme-step');
    });
    for (const [o, name] of [
        [a, 'host'],
        [b, 'guest'],
    ]) {
        await L.step(`scheme: ${name} picks a scheme`, o, async () => {
            await L.visit(o, url);
            await L.clickText(o, /^Select$/i, 'button');
            await L.clickText(o, /^Confirm Scheme$/i, 'button');
            await L.sleep(1500);
        });
    }
    await L.step('play: a game where both sides skipped their crew still starts and renders', a, async () => {
        await L.visit(a, url);
        await L.sleep(800);
        const text = await a.page.evaluate(() => document.body.innerText);
        if (!/Turn\s*1|Round\s*1|Submit Turn|Game|Turn/i.test(text)) throw new Error('play screen did not render');
        const real = a.problems.filter((x) => /pageerror/.test(x));
        if (real.length) throw new Error(real.join('; '));
        const onGameTab = await a.page.evaluate(() => document.querySelector('[role=tab][data-state=active]')?.innerText.trim());
        if (onGameTab !== 'Game') throw new Error(`should open on the Game tab, got ${onGameTab}`);
        await L.clickText(a, /^My Crew$/i, '[role=tab]');
        await L.expectText(a, /Crew not tracked/i);
        await L.shot(a, '295-play-no-crew');
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
