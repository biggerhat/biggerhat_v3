/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const fs = require('fs');
const path = require('path');
const L = require('./lib.cjs');
const axePath = process.env.AXE || path.resolve('node_modules/axe-core/axe.min.js');
// Battle reports: name a logged game at the top of the form, write a report about it in the article editor, keep it private, publish it,
// read it as a guest, and the entry points on the game pages. Desktop and phone.
(async () => {
    const browser = await L.launch();
    const hscroll = (s) => s.page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
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
    const axeScan = async (s, label) => {
        if (!fs.existsSync(axePath)) return;
        // the page-load progress bar (third party, role="bar") can still be on screen right after a navigation
        await s.page.waitForFunction(() => !document.getElementById('nprogress'), { timeout: 5000 }).catch(() => {});
        await s.page.evaluate(fs.readFileSync(axePath, 'utf8'));
        const res = await s.page.evaluate(async () => {
            const r = await window.axe.run(document, { resultTypes: ['violations'] });
            return r.violations.map((v) => ({ id: v.id, impact: v.impact, count: v.nodes.length, first: v.nodes[0].target.join(' ') }));
        });
        for (const v of res) console.log(`   [axe ${label}] ${v.impact} ${v.id} x${v.count}: ${v.first}`);
        const serious = res.filter((v) => v.impact === 'serious' || v.impact === 'critical');
        if (serious.length) throw new Error(`${serious.length} serious axe rule(s): ${serious.map((v) => `${v.id}x${v.count}`).join(', ')}`);
    };

    const shared = {};
    for (const [label, vp, who] of [
        ['desktop', { width: 1280, height: 900 }, 'carol@example.test'],
        ['phone', { width: 390, height: 844 }, 'dave@example.test'],
    ]) {
        const me = await L.session(browser, who, vp);
        const name = `League round 3 ${label}`;

        await L.step(`name (${label}): the game name is at the top of the Log form, not hidden, and shows in the list`, me, async () => {
            await L.visit(me, '/games/log');
            await me.page.waitForSelector('[data-testid=log-form]');
            const top = await me.page.$eval('#log-name', (el) => {
                const r = el.getBoundingClientRect();
                return { visible: r.width > 0 && r.height > 0, inExtras: !!el.closest('[data-testid=log-extras-card]') };
            });
            if (!top.visible || top.inExtras) throw new Error(`the name field is ${top.visible ? 'visible' : 'hidden'}, in extras: ${top.inExtras}`);
            await type(me, '#log-name', name);
            await press(me, 'log-result-win');
            await pickById(me, 'me-faction', /^Guild$/);
            await type(me, '#me-master', 'Free Text Master');
            await type(me, '#me-vp', '9');
            await type(me, '#opp-name', 'Sam');
            await pickById(me, 'opp-faction', /Bayou/);
            await type(me, '#opp-master', 'Some Bayou Boss');
            await type(me, '#opp-vp', '4');
            await press(me, 'log-submit');
            await me.page.waitForFunction(() => /\/games\/logged\/[0-9a-f-]{36}$/.test(location.pathname), { timeout: 12000 });
            await L.expectText(me, new RegExp(name));
            shared[label] = me.page.url();
            await L.visit(me, '/games');
            await L.expectText(me, new RegExp(name));
        });

        await L.step(`report (${label}): write one from the logged game, private by default`, me, async () => {
            await me.page.goto(shared[label], { waitUntil: 'networkidle2' });
            await me.page.waitForSelector('[data-testid=logged-report]');
            await press(me, 'logged-report');
            await me.page.waitForSelector('[data-testid=report-form]');
            const title = await me.page.$eval('#report-title', (i) => i.value);
            if (title !== name) throw new Error(`title was "${title}", wanted the game's name`);
            await me.page.waitForSelector('.ProseMirror', { timeout: 8000 });
            await me.page.$eval('.ProseMirror', (el) => el.scrollIntoView({ block: 'center' }));
            await me.page.click('.ProseMirror');
            await me.page.keyboard.type('The Marshals held the line until turn four.');
            await me.page.keyboard.press('Enter');
            await me.page.keyboard.type('Then the swamp rose.');
            if ((await hscroll(me)) > 1) throw new Error(`the form scrolls sideways by ${await hscroll(me)}px`);
            await L.shot(me, `440-report-form-${label}`);
            if (label === 'desktop') await axeScan(me, 'report form');
            await press(me, 'report-save');
            await me.page.waitForFunction(() => /\/battle-reports\/[0-9a-f-]{36}$/.test(location.pathname), { timeout: 12000 });
            await me.page.waitForSelector('[data-testid=report-show]');
            await L.expectText(me, /held the line until turn four/);
            await L.expectText(me, /Then the swamp rose/);
            await L.expectText(me, /Free Text Master/);
            await L.expectText(me, /Private: only you can see this/);
            if ((await hscroll(me)) > 1) throw new Error(`the report scrolls sideways by ${await hscroll(me)}px`);
            shared[`report-${label}`] = me.page.url();
            await L.shot(me, `440-report-show-${label}`);
            if (label === 'desktop') await axeScan(me, 'report page');
        });

        await L.step(`private (${label}): a stranger and a guest do not find a private report`, me, async () => {
            for (const email of ['alice@example.test', null]) {
                const other = await L.session(browser, email, vp);
                const res = await other.page.goto(shared[`report-${label}`], { waitUntil: 'domcontentloaded' });
                if (res.status() !== 404) throw new Error(`${email ?? 'a guest'} got ${res.status()} for a private report`);
            }
        });

        await L.step(`publish (${label}): asking to publish waits for a moderator; nobody else sees it`, me, async () => {
            await me.page.goto(shared[`report-${label}`], { waitUntil: 'networkidle2' });
            await press(me, 'report-edit');
            await me.page.waitForSelector('[data-testid=report-public]');
            await me.page.$eval('[data-testid=report-public]', (b) => b.click());
            await L.sleep(200);
            await press(me, 'report-save');
            await me.page.waitForSelector('[data-testid=report-pending-note]');
            await L.expectText(me, /Waiting for a moderator/);
            const guest = await L.session(browser, null, vp);
            const res = await guest.page.goto(shared[`report-${label}`], { waitUntil: 'domcontentloaded' });
            if (res.status() !== 404) throw new Error(`a guest got ${res.status()} for a report waiting for approval`);
            await guest.page.goto(`${L.BASE}/battle-reports`, { waitUntil: 'networkidle2' });
            if (await guest.page.evaluate((n) => document.body.innerText.includes(n), name)) throw new Error('the waiting report is listed');
        });

        await L.step(`approve (${label}): a moderator opens the queue, reads it and approves; a guest can then read it`, me, async () => {
            const mod = await L.session(browser, 'mod@example.test', vp);
            await mod.page.goto(`${L.BASE}/admin/battle-reports`, { waitUntil: 'networkidle2' });
            await mod.page.waitForSelector('[data-testid=reports-queue]');
            await L.expectText(mod, new RegExp(name));
            if ((await hscroll(mod)) > 1) throw new Error(`the queue scrolls sideways by ${await hscroll(mod)}px`);
            await L.shot(mod, `441-report-queue-${label}`);
            if (label === 'desktop') await axeScan(mod, 'report queue');
            // the moderator can read the waiting report
            const res = await mod.page.goto(shared[`report-${label}`], { waitUntil: 'networkidle2' });
            if (res.status() !== 200) throw new Error(`the moderator got ${res.status()} opening a waiting report`);
            await mod.page.waitForSelector('[data-testid=report-moderate]');
            await mod.page.goto(`${L.BASE}/admin/battle-reports`, { waitUntil: 'networkidle2' });
            await press(mod, 'report-approve');
            await mod.page.waitForFunction(() => document.querySelector('[data-testid=reports-empty]'), { timeout: 10000 });

            const guest = await L.session(browser, null, vp);
            const read = await guest.page.goto(shared[`report-${label}`], { waitUntil: 'networkidle2' });
            if (read.status() !== 200) throw new Error(`guest got ${read.status()} for an approved report`);
            await L.expectText(guest, /held the line until turn four/);
            await L.expectText(guest, /Free Text Master/);
            await guest.page.goto(`${L.BASE}/battle-reports`, { waitUntil: 'networkidle2' });
            await L.expectText(guest, new RegExp(name));
            if ((await hscroll(guest)) > 1) throw new Error(`the list scrolls sideways by ${await hscroll(guest)}px`);
            await L.shot(guest, `440-report-list-${label}`);
        });

        await L.step(`scoreboard (${label}): turning it off hides the game card and the report stays public`, me, async () => {
            await me.page.goto(shared[`report-${label}`], { waitUntil: 'networkidle2' });
            await press(me, 'report-edit');
            await me.page.waitForSelector('[data-testid=report-show-game]');
            await me.page.$eval('[data-testid=report-show-game]', (b) => b.click());
            await L.sleep(200);
            await press(me, 'report-save');
            await me.page.waitForSelector('[data-testid=report-show]');
            await L.sleep(300);
            if (await me.page.$('[data-testid=report-game]')) throw new Error('the scoreboard is still shown with the switch off');
            if (await me.page.$('[data-testid=report-pending-note]')) throw new Error('hiding the scoreboard sent the report back to a moderator');
        });

        await L.step(
            `comment (${label}): a reader comments and says nice report, the writer's profile lists it, the writer removes the comment`,
            me,
            async () => {
                const reader = await L.session(browser, 'alice@example.test', vp);
                await reader.page.goto(shared[`report-${label}`], { waitUntil: 'networkidle2' });
                await reader.page.waitForSelector('#comment-body');
                await type(reader, '#comment-body', 'Great read, the swamp turn was brutal.');
                await L.shot(reader, `443-report-comments-${label}`);
                await press(reader, 'report-comment-send');
                await reader.page.waitForFunction(() => document.querySelector('[data-testid=report-comment]'), { timeout: 10000 });
                await press(reader, 'report-react');
                await reader.page.waitForFunction(() => /Nice report · 1/.test(document.body.innerText), { timeout: 10000 });
                if ((await hscroll(reader)) > 1) throw new Error(`the comments scroll sideways by ${await hscroll(reader)}px`);
                if (label === 'desktop') await axeScan(reader, 'report comments');

                // the writer's profile lists the public report
                const profile = await reader.page.$eval('a[href*="/stats/"]', (a) => a.getAttribute('href'));
                await reader.page.goto(`${L.BASE}${profile.replace(/^https?:\/\/[^/]+/, '')}`, { waitUntil: 'networkidle2' });
                await reader.page.waitForSelector('[data-testid=profile-battle-reports]');
                await L.expectText(reader, new RegExp(name));

                await me.page.goto(shared[`report-${label}`], { waitUntil: 'networkidle2' });
                await L.expectText(me, /the swamp turn was brutal/);
                await press(me, 'report-comment-delete');
                await L.sleep(300);
                await L.clickText(me, /^Remove$/, '[role=dialog] button, [role=alertdialog] button');
                await me.page.waitForFunction(() => !document.querySelector('[data-testid=report-comment]'), { timeout: 10000 });
            },
        );

        await L.step(
            `flag (${label}): a reader reports it, a moderator takes it down with a reason, the writer asks for a second look`,
            me,
            async () => {
                const reader = await L.session(browser, 'alice@example.test', vp);
                await reader.page.goto(shared[`report-${label}`], { waitUntil: 'networkidle2' });
                await press(reader, 'report-flag-open');
                await reader.page.waitForSelector('#flag-reason');
                await type(reader, '#flag-reason', 'This looks like an advert.');
                await L.shot(reader, `442-report-flag-${label}`);
                await press(reader, 'report-flag-send');
                await reader.page.waitForSelector('[data-testid=report-flagged]', { timeout: 10000 });

                const mod = await L.session(browser, 'mod@example.test', vp);
                await mod.page.goto(`${L.BASE}/admin/battle-reports?status=reported`, { waitUntil: 'networkidle2' });
                await mod.page.waitForSelector('[data-testid=report-flags]');
                await L.expectText(mod, /This looks like an advert/);
                if ((await hscroll(mod)) > 1) throw new Error(`the reported tab scrolls sideways by ${await hscroll(mod)}px`);
                await L.shot(mod, `442-report-reported-${label}`);
                await type(mod, 'input[aria-label^="Reason for"]', 'Please keep adverts out.');
                await press(mod, 'report-reject');
                await L.sleep(300);
                await L.clickText(mod, /^Take down$/, '[role=dialog] button, [role=alertdialog] button');
                await mod.page.waitForFunction(() => document.querySelector('[data-testid=reports-empty]'), { timeout: 10000 });

                await me.page.goto(shared[`report-${label}`], { waitUntil: 'networkidle2' });
                await me.page.waitForSelector('[data-testid=report-rejected-note]');
                await L.expectText(me, /Please keep adverts out/);
                await press(me, 'report-appeal-open');
                await me.page.waitForSelector('#appeal-note');
                await type(me, '#appeal-note', 'It is a game write-up, no link.');
                await L.shot(me, `442-report-appeal-${label}`);
                await press(me, 'report-appeal-send');
                await me.page.waitForSelector('[data-testid=report-pending-note]', { timeout: 10000 });
                await L.expectText(me, /You asked for a second look/);
                if ((await hscroll(me)) > 1) throw new Error(`the report scrolls sideways by ${await hscroll(me)}px`);
            },
        );

        await L.step(`entry (${label}): the games list pen opens the existing report`, me, async () => {
            await L.visit(me, '/games');
            const href = await me.page.$eval('[data-testid=game-report]', (a) => a.getAttribute('href'));
            if (!/\/battle-reports\/[0-9a-f-]{36}\/edit$/.test(href)) throw new Error(`the pen linked to ${href}`);
        });

        await L.step(`delete (${label}): deleting the report leaves the game`, me, async () => {
            await me.page.goto(shared[`report-${label}`], { waitUntil: 'networkidle2' });
            await press(me, 'report-delete');
            await L.sleep(300);
            await L.clickText(me, /^Delete$/, '[role=dialog] button, [role=alertdialog] button');
            await me.page.waitForFunction(() => location.pathname === '/battle-reports', { timeout: 10000 });
            const res = await me.page.goto(shared[label], { waitUntil: 'domcontentloaded' });
            if (res.status() !== 200) throw new Error(`the game is gone after deleting its report (${res.status()})`);
        });
    }

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
    for (const r of L.results) console.log(`${r.status} ${r.name}${r.detail ? '\n       ' + r.detail : ''}`);
    if (bad.length) process.exit(1);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
