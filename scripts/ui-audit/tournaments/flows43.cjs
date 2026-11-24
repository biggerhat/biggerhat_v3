/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const fs = require('fs');
const path = require('path');
const L = require('./lib.cjs');
const axePath = process.env.AXE || path.resolve('node_modules/axe-core/axe.min.js');
// Logging a game played elsewhere, and the analytics over it: the quick log, a detailed log (turns, a saved crew, an invited opponent),
// the opponent's confirmation, the analytics page and its filters, and the form's own checks. Desktop and phone.
(async () => {
    const browser = await L.launch();
    const hscroll = (s) => s.page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    /** Open a Radix select by its id and choose an option by its words. */
    const pickById = async (s, id, optionRe) => {
        await s.page.$eval(`#${id}`, (el) => el.scrollIntoView({ block: 'center' }));
        await s.page.click(`#${id}`);
        await L.sleep(250);
        const ok = await s.page.evaluate((src) => {
            const rx = new RegExp(src, 'i');
            const el = [...document.querySelectorAll('[role=option]')].find((e) => rx.test(e.innerText.trim()));
            if (!el) return false;
            el.setAttribute('data-h-click', '1');
            return true;
        }, optionRe.source);
        if (!ok) throw new Error(`no option ${optionRe} in #${id}`);
        await s.page.click('[data-h-click]');
        await L.sleep(200);
    };
    const type = async (s, sel, text) => {
        await s.page.$eval(sel, (el) => el.scrollIntoView({ block: 'center' }));
        await s.page.click(sel, { clickCount: 3 });
        await s.page.keyboard.press('Backspace');
        await s.page.type(sel, text);
    };
    const press = (s, testid) =>
        s.page.$eval(`[data-testid=${testid}]`, (b) => {
            b.scrollIntoView({ block: 'center' });
            b.click();
        });
    const axeScan = async (s, label) => {
        if (!fs.existsSync(axePath)) return;
        await s.page.evaluate(fs.readFileSync(axePath, 'utf8'));
        const res = await s.page.evaluate(async () => {
            const r = await window.axe.run(document, { resultTypes: ['violations'] });
            return r.violations.map((v) => ({ id: v.id, impact: v.impact, count: v.nodes.length, first: v.nodes[0].target.join(' ') }));
        });
        for (const v of res) console.log(`   [axe ${label}] ${v.impact} ${v.id} x${v.count}: ${v.first}`);
        const serious = res.filter((v) => v.impact === 'serious' || v.impact === 'critical');
        if (serious.length) throw new Error(`${serious.length} serious axe rule(s): ${serious.map((v) => `${v.id}x${v.count}`).join(', ')}`);
    };
    const submitAndLand = async (s) => {
        await press(s, 'log-submit');
        await s.page.waitForFunction(() => /\/games\/logged\/[0-9a-f-]{36}$/.test(location.pathname), { timeout: 12000 });
    };

    for (const [label, vp] of [
        ['desktop', { width: 1280, height: 900 }],
        ['phone', { width: 390, height: 844 }],
    ]) {
        const carol = await L.session(browser, 'carol@example.test', vp);
        carol.page.on('dialog', (d) => d.accept().catch(() => {}));

        await L.step(`log (${label}): the quick log needs a result, the factions and the points, and saves a game`, carol, async () => {
            await L.visit(carol, '/games/log');
            await carol.page.waitForSelector('[data-testid=log-form]');
            if (!(await carol.page.$eval('[data-testid=log-submit]', (b) => b.disabled)))
                throw new Error('Log game was enabled with nothing filled in');
            await press(carol, 'log-result-win');
            await pickById(carol, 'me-faction', /^Guild$/);
            // the master is searched by name and picked
            await type(carol, '#me-master', 'Marshal');
            await carol.page.waitForSelector('#me-master-list button', { visible: true, timeout: 6000 });
            await carol.page.$eval('#me-master-list button', (b) =>
                b.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true })),
            );
            if ((await carol.page.$eval('#me-master', (i) => i.value)) !== 'Marshal Hale') throw new Error('the picked master did not fill the box');
            await type(carol, '#me-vp', '8');
            await type(carol, '#opp-name', 'Sam');
            await pickById(carol, 'opp-faction', /Bayou/);
            await type(carol, '#opp-master', 'Some Bayou Boss');
            await type(carol, '#opp-vp', '5');
            if (await carol.page.$eval('[data-testid=log-submit]', (b) => b.disabled))
                throw new Error('Log game stayed disabled with everything filled in');
            if ((await hscroll(carol)) > 1) throw new Error(`the form scrolls sideways by ${await hscroll(carol)}px`);
            await L.shot(carol, `430-log-quick-${label}`);
            await submitAndLand(carol);
            await carol.page.waitForSelector('[data-testid=logged-show]');
            await L.expectText(carol, /Carol won/);
            await L.expectText(carol, /Sam/);
            await L.expectText(carol, /Marshal Hale/);
            if ((await hscroll(carol)) > 1) throw new Error(`the summary scrolls sideways by ${await hscroll(carol)}px`);
            await L.shot(carol, `430-logged-show-${label}`);
        });
    }

    const carol = await L.session(browser, 'carol@example.test');
    carol.page.on('dialog', (d) => d.accept().catch(() => {}));
    let detailUuid = '';
    await L.step('log: a detailed game with turns, a saved crew and an invited opponent', carol, async () => {
        await L.visit(carol, '/games/log');
        await carol.page.waitForSelector('[data-testid=log-form]');
        await press(carol, 'log-result-win');
        await pickById(carol, 'me-faction', /^Guild$/);
        await type(carol, '#me-master', 'Marshal');
        await type(carol, '#opp-name', 'Dave');
        await pickById(carol, 'opp-faction', /Neverborn/);
        await type(carol, '#opp-master', 'Pandora');
        await pickById(carol, 'me-role', /Attacker/);
        await pickById(carol, 'opp-role', /Defender/);
        // the opponent's account
        await type(carol, '#opp-user', 'Dav');
        await carol.page.waitForSelector('#opp-user ~ ul button', { visible: true, timeout: 6000 });
        await carol.page.$eval('#opp-user ~ ul button', (b) => b.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true })));
        await carol.page.waitForSelector('[data-testid=opp-linked]');
        // turns
        await press(carol, 'log-turns-toggle');
        await press(carol, 'log-add-turn');
        await press(carol, 'log-add-turn');
        await type(carol, '[data-testid=t1-me-strategy]', '2');
        await type(carol, '[data-testid=t1-me-schemevp]', '3');
        await type(carol, '[data-testid=t1-opponent-strategy]', '1');
        await type(carol, '[data-testid=t2-me-strategy]', '1');
        await type(carol, '[data-testid=t2-opponent-strategy]', '2');
        await L.sleep(300);
        const vps = await carol.page.evaluate(() => [
            document.querySelector('#me-vp').value,
            document.querySelector('#opp-vp').value,
            document.querySelector('#me-vp').readOnly,
        ]);
        if (vps[0] !== '6' || vps[1] !== '3' || !vps[2]) throw new Error(`the totals were not added up from the turns: ${JSON.stringify(vps)}`);
        // a saved crew
        await press(carol, 'log-crews-toggle');
        await pickById(carol, 'log-saved-crew', /Demo Patrol/);
        const models = await carol.page.$$eval('[data-testid=log-models-me] li input[type=checkbox]', (e) => e.length);
        if (models !== 3) throw new Error(`the saved crew gave ${models} models, expected 3`);
        await carol.page.$eval('[data-testid=log-models-me] li input[type=checkbox]', (c) => c.click());
        await press(carol, 'log-extras-toggle');
        await type(carol, '[data-testid=log-venue]', 'Friday league');
        await type(carol, '[data-testid=log-notes]', 'Close until turn 4');
        await L.shot(carol, '430-log-detail');
        await axeScan(carol, 'log form');
        await submitAndLand(carol);
        detailUuid = carol.page.url().split('/games/logged/')[1];
        await carol.page.waitForSelector('[data-testid=logged-turns]');
        await carol.page.waitForSelector('[data-testid=logged-crews]');
        await L.expectText(carol, /waiting to confirm/);
        await L.expectText(carol, /Friday league/);
        await L.expectText(carol, /Close until turn 4/);
        await L.shot(carol, '430-logged-detail');
    });

    await L.step('log: the form refuses two attackers and says why, with the section open', carol, async () => {
        await L.visit(carol, '/games/log');
        await carol.page.waitForSelector('[data-testid=log-form]');
        await press(carol, 'log-result-win');
        await pickById(carol, 'me-faction', /^Guild$/);
        await type(carol, '#me-master', 'Some Master');
        await type(carol, '#me-vp', '7');
        await pickById(carol, 'opp-faction', /Bayou/);
        await type(carol, '#opp-name', 'Pat');
        await type(carol, '#opp-vp', '3');
        await pickById(carol, 'me-role', /Attacker/);
        await pickById(carol, 'opp-role', /Attacker/);
        await press(carol, 'log-submit');
        await carol.page.waitForSelector('[data-testid=log-errors]', { timeout: 8000 });
        await L.expectText(carol, /Both sides cannot be the attacker/);
        if (/\/games\/logged\/[0-9a-f-]{36}$/.test(carol.page.url())) throw new Error('a game with two attackers was saved');
    });

    const dave = await L.session(browser, 'dave@example.test');
    dave.page.on('dialog', (d) => d.accept().catch(() => {}));
    await L.step('opponent: the invited player is asked, confirms, and the game joins their stats from their side', dave, async () => {
        await L.visit(dave, '/my-stats');
        await dave.page.waitForSelector('[data-testid=awaiting-confirmation]');
        // a stranger's game is not in their stats yet
        const before = await dave.page.evaluate(
            () => JSON.parse(document.querySelector('[data-page]').getAttribute('data-page')).props.stats.total_games,
        );
        await dave.page.$eval('[data-testid=awaiting-confirmation] a', (a) => a.click());
        await dave.page.waitForSelector('[data-testid=logged-confirm]');
        await L.expectText(dave, /Carol won/);
        await L.shot(dave, '430-logged-invited');
        await press(dave, 'logged-confirm-yes');
        await dave.page.waitForFunction(() => !document.querySelector('[data-testid=logged-confirm]'), { timeout: 8000 });
        await L.visit(dave, '/my-stats');
        const after = await dave.page.evaluate(() => JSON.parse(document.querySelector('[data-page]').getAttribute('data-page')).props.stats);
        if (after.total_games !== before + 1 || after.losses < 1)
            throw new Error(`stats were ${before} then ${JSON.stringify({ g: after.total_games, l: after.losses })}`);
        if (await dave.page.$('[data-testid=awaiting-confirmation]')) throw new Error('the confirmed game is still waiting');
    });

    await L.step('games: the list marks logged games and offers to log one', carol, async () => {
        await L.visit(carol, '/games');
        await carol.page.waitForSelector('[data-testid=games-log]');
        await carol.page.waitForSelector('[data-testid=logged-badge]');
        const count = await carol.page.$$eval('[data-testid=logged-badge]', (e) => e.length);
        if (count < 2) throw new Error(`${count} logged badges`);
        await L.shot(carol, '430-games-index');
    });

    for (const [label, vp] of [
        ['desktop', { width: 1280, height: 900 }],
        ['phone', { width: 390, height: 844 }],
    ]) {
        const s = await L.session(browser, 'carol@example.test', vp);
        await L.step(`analytics (${label}): the numbers, the filters and the table`, s, async () => {
            await L.visit(s, '/my-stats');
            await s.page.waitForSelector('[data-testid=stats-analytics]');
            await s.page.$eval('[data-testid=stats-analytics]', (a) => a.click());
            await s.page.waitForSelector('[data-testid=analytics-tiles]');
            const count = () => s.page.$eval('[data-testid=filter-count]', (e) => e.innerText);
            if (!/\d+ of \d+ games/.test(await count())) throw new Error(`filter count said ${await count()}`);
            const total = Number((await count()).match(/of (\d+)/)[1]);
            if (total < 3) throw new Error(`only ${total} games in the analytics, expected the logged ones and Carol's others`);
            if ((await hscroll(s)) > 1) throw new Error(`analytics scrolls sideways by ${await hscroll(s)}px`);
            await L.shot(s, `431-analytics-${label}`);
            // filter to games logged by hand
            await press(s, 'filters-toggle');
            await press(s, 'source-logged');
            await s.page.waitForFunction(() => /source|sources/.test(location.search) && location.search.includes('logged'), { timeout: 8000 });
            await s.page.waitForSelector('[data-testid=filter-count]');
            await L.sleep(600);
            const afterText = await count();
            const n = Number(afterText.match(/^(\d+) of/)[1]);
            if (n !== 3) throw new Error(`filtered to logged games, expected the 3 Carol logged, got: ${afterText}`);
            const rows = await s.page.$$eval('[data-testid^=game-row-]', (e) => e.map((r) => r.getAttribute('data-testid')));
            if (rows.some((r) => r !== 'game-row-logged')) throw new Error(`rows from other sources: ${rows.join(',')}`);
            await L.shot(s, `431-analytics-filtered-${label}`);
            // a link in the table opens the logged game
            const href = await s.page.$eval('[data-testid=game-row-logged] a', (a) => a.getAttribute('href'));
            if (!/\/games\/logged\//.test(href)) throw new Error(`row linked to ${href}`);
            const csv = await s.page.$eval('[data-testid=analytics-csv]', (a) => a.getAttribute('href'));
            if (!/analytics\.csv/.test(csv)) throw new Error(`csv link was ${csv}`);
            if (label === 'desktop') await axeScan(s, 'analytics');
        });
    }

    await L.step('privacy: a private logged game is not found by another player', carol, async () => {
        const guest = await L.session(browser, 'alice@example.test');
        const res = await guest.page.goto(`${process.env.BASE}/games/logged/${detailUuid}`, { waitUntil: 'domcontentloaded' });
        if (res.status() !== 404) throw new Error(`a stranger got ${res.status()} for a private logged game`);
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
