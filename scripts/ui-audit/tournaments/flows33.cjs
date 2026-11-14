/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const fs = require('fs');
const path = require('path');
const L = require('./lib.cjs');
// Accessibility pass (axe-core) over every Game Tracker pre-game screen at phone width, dark and light: create form, lobby, faction,
// master, crew, scheme, the first play screen, and the solo variant. Needs `npm i --no-save axe-core` (skips itself without it).
// AXE_REPORT=1 prints minor findings too; moderate or worse fails the step.
const axePath = process.env.AXE || path.resolve('node_modules/axe-core/axe.min.js');
const REPORT = !!process.env.AXE_REPORT;

const findings = [];

async function runAxe(o) {
    await o.page.evaluate(fs.readFileSync(axePath, 'utf8'));
    return o.page.evaluate(async () => {
        const r = await window.axe.run(document, { resultTypes: ['violations'] });
        return r.violations.map((v) => ({
            id: v.id,
            impact: v.impact,
            help: v.help,
            count: v.nodes.length,
            nodes: v.nodes.slice(0, 3).map((n) => `${n.target.join(' ')} :: ${(n.failureSummary || '').split('\n')[1] || ''}`.slice(0, 220)),
        }));
    });
}

/**
 * Scans the current screen in dark (the site default), then, unless the screen has UI state a reload would lose
 * ({ transient: true }), again in light by switching the real `appearance` cookie and reloading. Findings are collected
 * rather than thrown, so one bad screen does not stop the walk through the wizard; the last step reports them all.
 */
// Chrome logs this when a page with a beforeunload handler is reloaded by the script rather than by a person; not an app problem.
const dropReloadNoise = (o) => o.problems.splice(0, o.problems.length, ...o.problems.filter((p) => !/beforeunload/.test(p)));

async function scan(o, label, { transient = false } = {}) {
    const modes = transient ? ['dark'] : ['dark', 'light'];
    for (const mode of modes) {
        if (mode === 'light') {
            await o.page.setCookie({ name: 'appearance', value: 'light', url: L.BASE });
            await o.page.reload({ waitUntil: 'networkidle2' });
            dropReloadNoise(o);
            await L.sleep(400);
        }
        for (const v of await runAxe(o)) {
            if (REPORT || v.impact !== 'minor')
                console.log(`   [${label} ${mode}] ${v.impact} ${v.id} ×${v.count}: ${v.help}\n      ${v.nodes.join('\n      ')}`);
            if (v.impact !== 'minor') findings.push(`${label} ${mode}: ${v.id}×${v.count}`);
        }
        if (mode === 'light') {
            await o.page.setCookie({ name: 'appearance', value: 'dark', url: L.BASE });
            await o.page.reload({ waitUntil: 'networkidle2' });
            dropReloadNoise(o);
            await L.sleep(400);
        }
    }
}

(async () => {
    if (!fs.existsSync(axePath)) {
        console.warn('SKIP flows33: axe-core not found — run `npm i --no-save axe-core` to include the accessibility pass');
        process.exit(0);
    }
    const browser = await L.launch();
    const a = await L.session(browser, 'alice@example.test', { width: 390, height: 844 });
    const b = await L.session(browser, 'bob@example.test', { width: 390, height: 844 });
    // In-progress games raise a beforeunload dialog; left open it would hang the reload used for the light-mode pass.
    for (const o of [a, b]) o.page.on('dialog', (d) => d.accept().catch(() => {}));
    let url;

    await L.step('a11y create form', a, async () => {
        await L.visit(a, '/games/create');
        await L.sleep(600);
        await scan(a, 'create');
        await L.clickText(a, /^Create Game$/i, 'button');
        await L.sleep(2200);
        url = a.page.url().replace(/^https?:\/\/[^/]+/, '');
    });
    await L.step('a11y lobby (waiting for an opponent)', a, async () => {
        await L.expectText(a, /assigned at random when they join/i);
        await scan(a, 'lobby');
    });
    await L.step('a11y faction step', b, async () => {
        await L.visit(b, url);
        await L.expectText(b, /Select Your Faction/i);
        await scan(b, 'faction');
        await L.clickText(b, /^Arcanists$/i, 'button');
        await L.sleep(400);
        await scan(b, 'faction + confirm bar', { transient: true });
        await L.clickText(b, /^Confirm$/i, 'button');
        await L.sleep(1200);
        await L.expectText(b, /Waiting/i);
        await scan(b, 'faction waiting');
    });
    await L.step('a11y master step', a, async () => {
        await L.visit(a, url);
        await L.clickText(a, /^Arcanists$/i, 'button');
        await L.clickText(a, /^Confirm$/i, 'button');
        await L.sleep(1200);
        await L.visit(a, url);
        await L.expectText(a, /Select Your Master/i);
        await scan(a, 'master');
        await L.clickText(a, /Rasputina/i, '[role=button]');
        await L.sleep(300);
        await scan(a, 'master selected', { transient: true });
        await L.clickText(a, /^Confirm$/i, 'button');
        await L.sleep(1200);
    });
    await L.step('a11y crew step', b, async () => {
        await L.visit(b, url);
        await L.clickText(b, /Rasputina/i, '[role=button]');
        await L.clickText(b, /^Confirm$/i, 'button');
        await L.sleep(1200);
        await L.visit(b, url);
        await L.expectText(b, /Select Your Crew/i);
        await scan(b, 'crew');
        await L.clickText(b, /Skip crew, track points only/i, 'button');
        await L.sleep(1200);
    });
    await L.step('a11y scheme step', a, async () => {
        await L.visit(a, url);
        await L.clickText(a, /Skip crew, track points only/i, 'button');
        await L.sleep(1500);
        await L.visit(a, url);
        await L.expectText(a, /Scheme/i);
        await scan(a, 'scheme');
        await L.clickText(a, /^Select$/i, 'button');
        await L.sleep(500);
        await scan(a, 'scheme form', { transient: true });
    });
    await L.step('a11y first play screen', a, async () => {
        await L.clickText(a, /^Confirm Scheme$/i, 'button');
        await L.sleep(1500);
        await L.visit(b, url);
        await L.clickText(b, /^Select$/i, 'button');
        await L.clickText(b, /^Confirm Scheme$/i, 'button');
        await L.sleep(1500);
        await L.visit(a, url);
        await L.sleep(800);
        await scan(a, 'play');
    });
    await L.step('a11y solo: create then the first setup screen', a, async () => {
        await L.visit(a, '/games/create');
        await L.sleep(500);
        await a.page.click('button[aria-label="Solo mode"]');
        await L.sleep(300);
        await scan(a, 'create (solo)', { transient: true });
        await L.clickText(a, /^Create Game$/i, 'button');
        await L.sleep(2200);
        await L.expectText(a, /Faction/i);
        await scan(a, 'solo faction');
    });

    await L.step('a11y: no moderate-or-worse findings on any setup screen', a, async () => {
        if (findings.length) throw new Error(findings.join(' | '));
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
