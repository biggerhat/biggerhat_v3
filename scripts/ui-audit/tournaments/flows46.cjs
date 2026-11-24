/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const fs = require('fs');
const path = require('path');
const L = require('./lib.cjs');
const axePath = process.env.AXE || path.resolve('node_modules/axe-core/axe.min.js');
// Collection tabs: Packages, Factions, Keywords and Stats with data (Carol), and with an empty collection (Bob). Desktop and phone.
(async () => {
    const browser = await L.launch();
    const hscroll = (s) => s.page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
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
    const openTab = async (s, name) => {
        await s.page.click(`[role=tab][aria-label="${name}"]`);
        await L.sleep(1500); // the deferred props stream in after the tab opens
    };

    for (const [label, vp, who, owner] of [
        ['desktop', { width: 1280, height: 900 }, 'carol@example.test', 'carol'],
        ['phone', { width: 390, height: 844 }, 'carol@example.test', 'carol'],
        ['desktop', { width: 1280, height: 900 }, 'bob@example.test', 'empty'],
        ['phone', { width: 390, height: 844 }, 'bob@example.test', 'empty'],
    ]) {
        const me = await L.session(browser, who, vp);
        await L.visit(me, '/collection');
        for (const tab of ['Packages', 'Factions', 'Keywords', 'Stats']) {
            await L.step(`${tab} (${owner} ${label}): shows, fits the screen, passes axe`, me, async () => {
                await openTab(me, tab);
                if ((await hscroll(me)) > 1) throw new Error('page scrolls sideways');
                await axeScan(me, `${tab} ${owner} ${label}`);
                await L.shot(me, `collection-tab-${tab.toLowerCase()}-${owner}-${label}`);
            });
        }
    }

    // behaviour, desktop
    {
        const me = await L.session(browser, 'carol@example.test', { width: 1280, height: 900 });
        const body = () => L.text(me);
        const tab = async (name) => {
            await me.page.click(`[role=tab][aria-label="${name}"]`);
            await L.sleep(1500);
        };

        await L.step('packages: progress against what the package holds, its contents, and a confirm before removing', me, async () => {
            await L.visit(me, '/collection');
            await tab('Packages');
            await L.expectText(me, /You have 3 of 3 models/);
            await L.expectText(me, /2 built · 2 painted/);
            await me.page.click('[data-testid=collection-package-contents-toggle]');
            await L.expectText(me, /Collection Guard Alpha\s+2\/2/);
            if ((await me.page.$$('[data-testid=collection-package-progress]')).length !== 1)
                throw new Error('a package with no contents should show no progress');
            await me.page.click('[data-testid=collection-package-remove]');
            await L.sleep(400);
            await L.expectText(me, /The miniatures it added stay in your collection/);
            await L.clickText(me, /^Cancel$/, '[role=dialog] button, [role=alertdialog] button');
            await L.sleep(300);
            if ((await me.page.$$('[data-testid=collection-package]')).length !== 2) throw new Error('cancelling should keep both packages');
        });

        await L.step('tab in the address: it survives a reload', me, async () => {
            if (!/[?&]tab=packages/.test(me.page.url())) throw new Error(`address should carry the tab: ${me.page.url()}`);
            await me.page.reload({ waitUntil: 'networkidle2' });
            await L.sleep(800);
            await L.expectText(me, /You have 3 of 3 models/);
        });

        await L.step('factions: empty factions are hidden, a click opens the collection for that faction', me, async () => {
            await tab('Factions');
            const cards = await me.page.$$eval('[data-testid=collection-faction]', (els) => els.map((e) => e.innerText));
            if (cards.length === 0 || cards.some((t) => /\/ 0 characters/.test(t)))
                throw new Error('a faction with no characters should not be listed');
            if (/(^|[^\d])1 characters/.test(await body())) throw new Error('"1 characters"');
            await me.page.evaluate(() => {
                const card = [...document.querySelectorAll('[data-testid=collection-faction]')].find((b) => /^Guild/.test(b.innerText.trim()));
                card.click();
            });
            await L.sleep(800);
            await L.expectText(me, /Collection Guard Alpha/);
            if (/tab=/.test(me.page.url())) throw new Error('the collection tab is the default, so the address needs no tab');
        });

        await L.step('keywords: owned first, not-started hidden until asked, search finds any', me, async () => {
            await tab('Keywords');
            const names = await me.page.$$eval('[data-testid=collection-keyword]', (els) => els.map((e) => e.innerText.split('\n')[0].trim()));
            if (names.includes('Patrol')) throw new Error('a keyword with nothing owned should be hidden by default');
            if (names[0] !== 'Collection Guard Corps') throw new Error(`most complete first expected, got ${names[0]}`);
            await me.page.click('[data-testid=collection-keywords-all]');
            await L.sleep(400);
            await L.expectText(me, /Patrol/);
            await me.page.type('[data-testid=collection-keyword-search]', 'Lone');
            await L.sleep(400);
            const found = await me.page.$$eval('[data-testid=collection-keyword]', (els) => els.length);
            if (found !== 1) throw new Error(`search for Lone should find 1, found ${found}`);
        });

        await L.step('stats: models by stage, what is next, and a click opens the collection for it', me, async () => {
            await tab('Stats');
            await L.expectText(me, /Models by stage/i);
            await L.expectText(me, /Ready to paint/i);
            await L.expectText(me, /Still in the box/i);
            if (!/Collection Guard Beta/.test(await me.page.$eval('[data-testid=collection-next-build]', (e) => e.innerText)))
                throw new Error('Beta (2 unbuilt) should be listed as still in the box');
            await me.page.click('[data-testid=collection-next-build] button');
            await L.sleep(800);
            await L.expectText(me, /Collection Guard Beta/);
            const q = await me.page.$eval('input[placeholder="Search collection..."]', (e) => e.value);
            if (!/Collection Guard Beta/.test(q)) throw new Error(`the search should be filled in, is "${q}"`);
        });
    }

    // an empty collection
    {
        const me = await L.session(browser, 'bob@example.test', { width: 1280, height: 900 });
        await L.step('empty collection: a welcome with where to start, and the tabs say so', me, async () => {
            await L.visit(me, '/collection');
            await L.expectText(me, /Start your collection/);
            await me.page.click('[role=tab][aria-label="Stats"]');
            await L.sleep(800);
            await L.expectText(me, /Nothing to show yet/);
            await me.page.click('[role=tab][aria-label="Keywords"]');
            await L.sleep(1500);
            await L.expectText(me, /No keywords started yet/);
            await L.shot(me, 'collection-empty-welcome');
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
