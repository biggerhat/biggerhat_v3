/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Play screen crew rows (GameCrewMemberRow): health stepper and pips, activation, the token dialog and the opponent side of a solo game, at phone width.
(async () => {
    const browser = await L.launch();
    const c = await L.session(browser, 'carol@example.test', { width: 390, height: 844 });
    // An in-progress game asks "leave site?" on navigation; accept it so the reload below is not blocked.
    c.page.on('dialog', (d) => d.accept().catch(() => {}));
    // Run inside the page: find the visible crew row whose name is `name`, then act on it.
    const inRow = (name, fn, arg) =>
        c.page.evaluate(
            (n, f, a) => {
                const rows = [...document.querySelectorAll('div.rounded-md.border.border-white\\/20')].filter((r) => r.offsetParent !== null);
                const row = rows.find((r) => r.innerText.split('\n')[0].trim() === n || r.innerText.includes(n));
                if (!row) return `no row for ${n}`;
                const vis = (sel) => [...row.querySelectorAll(sel)].find((b) => b.offsetParent !== null);
                if (f === 'label') {
                    const b = vis(`button[aria-label="${a}"]`);
                    if (!b) return `no button ${a}`;
                    b.click();
                    return 'ok';
                }
                if (f === 'title') {
                    const b = vis(`button[title="${a}"]`);
                    if (!b) return `no button titled ${a}`;
                    b.click();
                    return 'ok';
                }
                if (f === 'text') return row.innerText.replace(/\s+/g, ' ');
                return 'bad';
            },
            name,
            fn,
            arg,
        );
    const expectRow = async (name, re) => {
        await L.sleep(900);
        const t = await inRow(name, 'text');
        if (!re.test(t)) throw new Error(`row "${name}" text was "${t}", wanted ${re}`);
    };
    const act = async (name, fn, arg) => {
        const r = await inRow(name, fn, arg);
        if (r !== 'ok') throw new Error(r);
    };

    await L.step('crew: opens with both crews and a count of models left to activate', c, async () => {
        await L.visit(c, '/games/' + L.ids.play_game);
        await L.clickText(c, /^My Crew$/i, '[role=tab]');
        await L.expectText(c, /Pandora/);
        await L.expectText(c, /6\s*\/\s*6 remaining/);
    });
    await L.step('crew: the minus button lowers a model’s health', c, async () => {
        await act('Silurid', 'label', 'Decrease health of Silurid');
        await expectRow('Silurid', /5\/6/);
    });
    await L.step('crew: tapping a health pip sets health directly', c, async () => {
        await act('Swampfiend', 'label', 'Set health to 2 of 7');
        await expectRow('Swampfiend', /2\/7/);
    });
    await L.step('crew: the plus button raises it again', c, async () => {
        await act('Swampfiend', 'label', 'Increase health of Swampfiend');
        await expectRow('Swampfiend', /3\/7/);
    });
    await L.step('crew: marking a model activated updates the remaining count', c, async () => {
        await act('Mad Gremlin', 'title', 'Mark activated');
        await L.expectText(c, /5\s*\/\s*6 remaining/);
    });
    await L.step('crew: a row is compact on a phone (about 80px, down from about 105px)', c, async () => {
        const h = await c.page.evaluate(() => {
            const rows = [...document.querySelectorAll('div.rounded-md.border.border-white\\/20')].filter((r) => r.offsetParent !== null);
            const row = rows.find((r) => r.innerText.includes('Silurid'));
            return Math.round(row.getBoundingClientRect().height);
        });
        if (h > 90) throw new Error(`row is ${h}px tall`);
        await L.shot(c, '303-compact-rows');
    });
    await L.step('crew: a model marked activated moves to the bottom of the list', c, async () => {
        await act('Pandora', 'title', 'Mark activated');
        await L.sleep(700);
        const names = await c.page.evaluate(() =>
            [...document.querySelectorAll('div.rounded-md.border.border-white\\/20')]
                .filter((r) => r.offsetParent !== null)
                .map((r) => r.innerText.split('\n')[0].trim()),
        );
        const tail = names.slice(-2);
        if (!(tail.includes('Pandora') && tail.includes('Mad Gremlin')))
            throw new Error(`activated models should be last, order was ${names.join(', ')}`);
        if (names[0] === 'Pandora') throw new Error('Pandora should no longer be first');
    });
    await L.step('crew: the tokens button opens the token dialog for that model', c, async () => {
        await act('Pandora', 'title', 'Tokens');
        await c.page.waitForSelector('[role=dialog]', { timeout: 6000 });
        await L.shot(c, '300-token-dialog');
        await c.page.keyboard.press('Escape');
    });
    await L.step('crew: the name opens the card preview', c, async () => {
        await c.page.evaluate(() => {
            const rows = [...document.querySelectorAll('div.rounded-md.border.border-white\\/20')].filter((r) => r.offsetParent !== null);
            rows.find((r) => r.innerText.includes('Silurid'))
                ?.querySelector('button.font-semibold')
                ?.click();
        });
        await L.sleep(800);
        await L.shot(c, '301-preview');
        await c.page.keyboard.press('Escape');
    });
    await L.step('opponent: in a solo game the other crew is editable too', c, async () => {
        await L.clickText(c, /^Opponent$/i, '[role=tab]');
        await L.expectText(c, /Guild Guard/);
        await act('Guild Hound', 'label', 'Decrease health of Guild Hound');
        await expectRow('Guild Hound', /4\/5/);
        await L.shot(c, '302-opponent-crew');
    });
    await L.step('persistence: the changes survive a reload', c, async () => {
        // The page keeps a socket retry going, so network-idle never settles after the earlier interactions.
        await c.page.goto(`${L.BASE}/games/${L.ids.play_game}`, { waitUntil: 'domcontentloaded' });
        await L.sleep(1500);
        await L.clickText(c, /^My Crew$/i, '[role=tab]');
        await expectRow('Silurid', /5\/6/);
        await expectRow('Swampfiend', /3\/7/);
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
