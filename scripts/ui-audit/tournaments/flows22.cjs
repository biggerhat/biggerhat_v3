/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const fs = require('fs');
const path = require('path');
const L = require('./lib.cjs');
// Accessibility pass (axe-core) over the tournament pages: light and dark, desktop and phone.
// Needs axe-core, which is not a project dependency: `npm i --no-save axe-core` (or set AXE to a path).
// AXE_REPORT=1 also prints the minor ones; anything moderate or worse fails the step.
const axePath = process.env.AXE || path.resolve('node_modules/axe-core/axe.min.js');
const REPORT = !!process.env.AXE_REPORT;

async function axeScan(s, label) {
    await s.page.evaluate(fs.readFileSync(axePath, 'utf8'));
    const res = await s.page.evaluate(async () => {
        const r = await window.axe.run(document, { resultTypes: ['violations'] });
        return r.violations.map((v) => ({
            id: v.id,
            impact: v.impact,
            help: v.help,
            nodes: v.nodes.slice(0, 3).map((n) => `${n.target.join(' ')} :: ${(n.failureSummary || '').split('\n')[1] || ''}`.slice(0, 240)),
            count: v.nodes.length,
        }));
    });
    const bad = res.filter((v) => v.impact !== 'minor');
    if (REPORT || bad.length) {
        for (const v of REPORT ? res : bad)
            console.log(`   [${label}] ${v.impact} ${v.id} ×${v.count}: ${v.help}\n      ${v.nodes.join('\n      ')}`);
    }
    if (bad.length) throw new Error(`${bad.length} axe rule(s) (moderate or worse): ${bad.map((v) => `${v.id}×${v.count}`).join(', ')}`);
}

(async () => {
    if (!fs.existsSync(axePath)) {
        console.warn('SKIP flows22: axe-core not found — run `npm i --no-save axe-core` to include the accessibility pass');
        process.exit(0);
    }
    const browser = await L.launch();
    const { ids } = L;

    // One league with a finished event in it, so the league pages are scanned with real rows (made through the app, once).
    let leagueUuid = null;
    const dave = await L.session(browser, 'dave@example.test');
    await L.step('a11y setup: dave starts a league and adds his finished event', dave, async () => {
        await L.visit(dave, '/tournaments/series/create');
        await dave.page.type('#s-name', 'A11y League');
        await L.clickText(dave, /^Create league$/i, 'button');
        await dave.page.waitForFunction(() => /\/tournaments\/series\/[0-9a-f-]{36}$/.test(location.pathname), { timeout: 9000 });
        leagueUuid = dave.page.url().split('/').pop();
        const status = await dave.page.evaluate(
            async (t, series) => {
                const token = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
                const res = await fetch(`/tournaments/${t}/series`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': token },
                    body: JSON.stringify({ series }),
                });
                return res.status;
            },
            ids.t7,
            leagueUuid,
        );
        if (status !== 200) throw new Error(`could not attach the event (${status})`);
    });

    for (const theme of ['light', 'dark']) {
        for (const [vpName, vp] of [
            ['desktop', { width: 1280, height: 900 }],
            ['phone', { width: 390, height: 844 }],
        ]) {
            const tag = `${theme}/${vpName}`;
            const open = async (email) => {
                const s = await L.session(browser, email, vp);
                await s.page.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: theme }]);
                await s.page.setCookie({ name: 'appearance', value: theme, url: L.BASE });
                return s;
            };
            const guest = await open(null);
            const org = await open('org@example.test');

            for (const [name, url] of [
                ['list', '/tournaments'],
                ['public event', `/tournaments/${ids.t1}/view`],
                ['finished event', `/tournaments/${ids.t7}/view`],
                ['display mode', `/tournaments/${ids.t1}/display`],
            ]) {
                await L.step(`a11y ${tag}: ${name}`, guest, async () => {
                    await L.visit(guest, url);
                    await L.sleep(600);
                    await axeScan(guest, `${tag} ${name}`);
                });
            }
            for (const [name, url] of [
                ['leagues list', '/tournaments/series'],
                ['league page', `/tournaments/series/${leagueUuid}`],
            ]) {
                await L.step(`a11y ${tag}: ${name}`, guest, async () => {
                    await L.visit(guest, url);
                    await L.sleep(500);
                    await axeScan(guest, `${tag} ${name}`);
                });
            }
            await L.step(`a11y ${tag}: league form`, org, async () => {
                await L.visit(org, '/tournaments/series/create');
                await L.sleep(400);
                await axeScan(org, `${tag} league form`);
            });
            await L.step(`a11y ${tag}: create form`, org, async () => {
                await L.visit(org, '/tournaments/create');
                await L.sleep(400);
                await axeScan(org, `${tag} create`);
            });
            await L.step(`a11y ${tag}: import form`, org, async () => {
                await L.visit(org, '/tournaments/import');
                await L.sleep(400);
                await axeScan(org, `${tag} import`);
            });
            for (const tab of ['Standings', 'Players', 'Rounds', 'Details', 'Settings']) {
                await L.step(`a11y ${tag}: manage ${tab}`, org, async () => {
                    await L.visit(org, `/tournaments/${ids.t1}`);
                    await L.clickText(org, new RegExp(`^${tab}`, 'i'), '[role=tab]');
                    await L.sleep(500);
                    await axeScan(org, `${tag} manage/${tab}`);
                });
            }
            await L.step(`a11y ${tag}: score dialog`, org, async () => {
                await L.visit(org, `/tournaments/${ids.t1}`);
                await L.clickText(org, /^Rounds/i, '[role=tab]');
                await L.clickText(org, /^(Enter|Edit) score$/i, 'button');
                await L.expectText(org, /Game Score/i);
                await L.sleep(400);
                await axeScan(org, `${tag} score dialog`);
            });
            await L.step(`a11y ${tag}: top cut offer and bracket`, org, async () => {
                await L.visit(org, `/tournaments/${ids.t9}`);
                await L.clickText(org, /^Rounds/i, '[role=tab]');
                await org.page.waitForSelector('[data-testid="top-cut"]', { timeout: 9000 });
                await axeScan(org, `${tag} cut offer`);
                await L.visit(org, `/tournaments/${ids.t10}`);
                await L.clickText(org, /^Rounds/i, '[role=tab]');
                await L.sleep(400);
                await axeScan(org, `${tag} cut advance`);
            });
            await L.step(`a11y ${tag}: scoring desk`, org, async () => {
                await L.visit(org, `/tournaments/${ids.t1}/score`);
                await L.sleep(500);
                await axeScan(org, `${tag} desk`);
            });
        }
    }

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
    if (bad.length) process.exit(1);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
