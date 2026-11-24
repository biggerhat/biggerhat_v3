/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const fs = require('fs');
const path = require('path');
const L = require('./lib.cjs');
const axePath = process.env.AXE || path.resolve('node_modules/axe-core/axe.min.js');
// Collection: a collection saved the old way (a quantity and built/painted flags per miniature) shows every copy with its old state,
// each copy can be given its own stage and note, copies can be added and removed, and a group can be set to a stage. Desktop and phone.
(async () => {
    const browser = await L.launch();
    const hscroll = (s) => s.page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    const rowText = (s, name) =>
        s.page.evaluate((n) => {
            const el = [...document.querySelectorAll('div.px-3')].find(
                (e) => e.innerText.includes(n) && e.querySelector('[data-testid=collection-models-toggle]'),
            );
            return el ? el.innerText.replace(/\s+/g, ' ') : '';
        }, name);
    const waitRow = async (s, name, re, timeout = 9000) => {
        const end = Date.now() + timeout;
        let t = '';
        while (Date.now() < end) {
            t = await rowText(s, name);
            if (re.test(t)) return t;
            await L.sleep(250);
        }
        throw new Error(`row "${name}" did not match ${re}: ${t}`);
    };
    const inRow = (s, name, selector) =>
        s.page.evaluate(
            (n, sel) => {
                const row = [...document.querySelectorAll('div.px-3')].find(
                    (e) => e.innerText.includes(n) && e.querySelector('[data-testid=collection-models-toggle]'),
                );
                const el = row && row.querySelector(sel);
                if (!el) throw new Error(`no ${sel} in the ${n} row`);
                el.scrollIntoView({ block: 'center' });
                el.click();
            },
            name,
            selector,
        );
    const summary = (s) => s.page.evaluate(() => document.body.innerText.replace(/\s+/g, ' '));
    const axeScan = async (s, label) => {
        if (!fs.existsSync(axePath)) return;
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
    const openFirst = async (s) => {
        await s.page.$eval('[data-testid=collection-models-toggle]', (b) => {
            b.scrollIntoView({ block: 'center' });
            b.click();
        });
        await s.page.waitForSelector('[data-testid=collection-model]', { timeout: 5000 });
    };

    for (const [label, vp, who] of [
        ['desktop', { width: 1280, height: 900 }, 'carol@example.test'],
        ['phone', { width: 390, height: 844 }, 'dave@example.test'],
    ]) {
        const me = await L.session(browser, who, vp);

        await L.step(`old data (${label}): every old row shows its copies with the state it had`, me, async () => {
            await L.visit(me, '/collection');
            await waitRow(me, 'Collection Guard Alpha', /Built 3\/3 · Painted 3\/3/);
            const t = await summary(me);
            if (!/3 \/ 5/.test(t)) throw new Error(`summary should count 3 of 5 models built: ${t.slice(0, 400)}`);
            if (!(await rowText(me, 'Collection Guard Beta')).includes('Painted 0/2')) throw new Error('Beta should be 2 unbuilt copies');
            if ((await hscroll(me)) > 1) throw new Error('page scrolls sideways');
        });

        await L.step(`one model (${label}): a copy gets its own stage and the row shows partial progress`, me, async () => {
            await openFirst(me);
            if ((await me.page.$$('[data-testid=collection-model]')).length !== 3) throw new Error('expected 3 copies listed');
            await L.pickSelect(me, 0, /^Unbuilt$/, '[data-testid^=collection-copies-]');
            await waitRow(me, 'Collection Guard Alpha', /Built 2\/3 · Painted 2\/3/);
            const t = await summary(me);
            if (!/2 \/ 5/.test(t)) throw new Error('summary should now count 2 of 5 built');
            await axeScan(me, label);
            if ((await hscroll(me)) > 1) throw new Error('page scrolls sideways with the copies open');
            await L.shot(me, `collection-models-${label}`);
        });

        await L.step(`note (${label}): a note on a copy is saved and still there after a reload`, me, async () => {
            const sel = '[data-testid=collection-model-note]';
            await me.page.click(sel);
            await me.page.type(sel, 'Rust-red coat');
            await me.page.keyboard.press('Enter');
            await L.sleep(900);
            await L.visit(me, '/collection');
            await openFirst(me);
            const v = await me.page.$eval(sel, (e) => e.value);
            if (v !== 'Rust-red coat') throw new Error(`note not kept: "${v}"`);
        });

        await L.step(`add and remove (${label}): add a copy, then take one out`, me, async () => {
            await me.page.$eval('[data-testid=collection-model-add]', (b) => {
                b.scrollIntoView({ block: 'center' });
                b.click();
            });
            await waitRow(me, 'Collection Guard Alpha', /Built 2\/4 · Painted 2\/4/);
            const pips = await me.page.evaluate(() => {
                const row = [...document.querySelectorAll('div.px-3')].find(
                    (e) => e.innerText.includes('Collection Guard Alpha') && e.querySelector('[data-testid=collection-models-toggle]'),
                );
                return row.querySelector('[data-testid=stage-pips]').children.length;
            });
            if (pips !== 4) throw new Error(`the row should show one dot per model (4), shows ${pips}`);
            // the first copy has a note, so taking it out asks first
            await me.page.$eval('[data-testid=collection-model-remove]', (b) => {
                b.scrollIntoView({ block: 'center' });
                b.click();
            });
            await L.sleep(400);
            await L.clickText(me, /^Remove$/, '[role=dialog] button, [role=alertdialog] button');
            await waitRow(me, 'Collection Guard Alpha', /Built \d\/3 · Painted \d\/3/);
        });

        await L.step(`all of them (${label}): the hammer marks every copy built, and the quantity button adds an unbuilt copy`, me, async () => {
            await inRow(me, 'Collection Guard Beta', 'button[title="Mark every model as built"]');
            await waitRow(me, 'Collection Guard Beta', /Built 2\/2 · Painted 0\/2/);
            await inRow(me, 'Collection Guard Beta', 'button[aria-label="Increase quantity"]');
            await waitRow(me, 'Collection Guard Beta', /Built 2\/3 · Painted 0\/3/);
        });

        await L.step(`group (${label}): select both rows and set every copy to Based`, me, async () => {
            await L.clickText(me, /^Select$/);
            await me.page.evaluate(() => {
                const box = [...document.querySelectorAll('[role=checkbox]')].find((e) => e.offsetParent !== null);
                box.click();
            });
            await L.sleep(300);
            await me.page.click('[data-testid=collection-bulk-stage]');
            await L.sleep(300);
            await L.clickText(me, /Based/, '[role=option]');
            await L.sleep(900);
            const t = await summary(me);
            if (!/6 \/ 6/.test(t)) throw new Error(`all 6 models should be built: ${t.slice(0, 300)}`);
        });

        await L.step(`themes (${label}): light mode and large text keep the rows readable and inside the screen`, me, async () => {
            if (!(await me.page.$('[data-testid=collection-model]'))) await openFirst(me);
            await me.page.evaluate(() => document.documentElement.classList.remove('dark'));
            await L.sleep(300);
            await axeScan(me, `${label} light`);
            await L.shot(me, `collection-light-${label}`);
            await me.page.evaluate(() => document.documentElement.setAttribute('data-text-size', 'xlarge'));
            await L.sleep(400);
            if ((await hscroll(me)) > 1) throw new Error('page scrolls sideways at the largest text size');
            await L.shot(me, `collection-xlarge-${label}`);
            await me.page.evaluate(() => {
                document.documentElement.removeAttribute('data-text-size');
                document.documentElement.classList.add('dark');
            });
        });
    }

    // a big collection (40 miniatures) for a player who has not touched the page before
    for (const [label, vp] of [
        ['desktop', { width: 1280, height: 900 }],
        ['phone', { width: 390, height: 844 }],
    ]) {
        const big = await L.session(browser, 'alice@example.test', vp);
        await L.step(`big collection (${label}): 40 miniatures list, scroll and filter without breaking the layout`, big, async () => {
            await L.visit(big, '/collection');
            await L.expectText(big, /Big Squad 40/);
            const rows = await big.page.$$eval('[data-testid=collection-models-toggle]', (els) => els.length);
            if (rows < 40) throw new Error(`expected 40 rows, got ${rows}`);
            if ((await hscroll(big)) > 1) throw new Error('page scrolls sideways');
            await L.shot(big, `collection-big-${label}`);
            await big.page.type('input[placeholder="Search collection..."]', 'Big Squad 07');
            await L.sleep(400);
            const left = await big.page.$$eval('[data-testid=collection-models-toggle]', (els) => els.length);
            if (left !== 1) throw new Error(`search should leave 1 row, left ${left}`);
        });
    }

    // sharing: the stages show, the notes never do
    {
        const owner = await L.session(browser, 'carol@example.test', { width: 1280, height: 900 });
        const viewer = await L.session(browser, 'bob@example.test', { width: 1280, height: 900 });
        await L.step('share: another player sees the stages but not the notes', owner, async () => {
            await L.visit(owner, '/collection');
            await L.clickText(owner, /^Private$/);
            await L.sleep(700);
            const code = await owner.page.evaluate(() => JSON.parse(document.getElementById('app').dataset.page).props.share_code);
            await L.visit(viewer, `/collection/share/${code}`);
            await L.expectText(viewer, /Collection Guard Alpha/);
            if (/Rust-red coat/.test(await L.text(viewer))) throw new Error('a note shows on the shared collection');
            if ((await viewer.page.$$('[data-testid=stage-pips]')).length < 2) throw new Error('the shared collection shows no stage dots');
            if (await viewer.page.$('[data-testid=collection-models-toggle]')) throw new Error('a viewer has the owner controls');
            await L.shot(viewer, 'collection-shared');
        });
    }

    // help: the Help button on the page opens the collection topic, and its page loads
    {
        const me = await L.session(browser, 'carol@example.test', { width: 1280, height: 900 });
        await L.step('help: the Help button opens the collection help, and /help/collection loads', me, async () => {
            await L.visit(me, '/collection');
            await me.page.click('[data-testid=help-open-collection]');
            await L.expectText(me, /Adding what you own/);
            await L.expectText(me, /Add to Collection/);
            await me.page.keyboard.press('Escape');
            await L.sleep(300);
            await L.visit(me, '/help/collection');
            await L.expectText(me, /Tracking each model/);
            await L.expectText(me, /Based \(table ready\)/);
            await axeScan(me, 'help collection');
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
