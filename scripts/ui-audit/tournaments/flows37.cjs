/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const fs = require('fs');
const path = require('path');
const L = require('./lib.cjs');
// Tabletop audit: the sandbox, a game's board and the play screen, light and dark, desktop and phone. For each state it takes a screenshot
// and measures what a person would trip over: axe-core violations, tap targets under 24px (WCAG 2.2) or 44px (comfortable on a phone),
// text under 11px, horizontal overflow, and fixed elements covering each other. It reports; it fails only on serious axe violations.
// Needs axe-core (`npm i --no-save axe-core`). Run alone: FLOWS=flows37.cjs scripts/ui-audit/tournaments/run.sh
const axePath = process.env.AXE || path.resolve('node_modules/axe-core/axe.min.js');
const HAVE_AXE = fs.existsSync(axePath);

async function measure(s, label) {
    const m = await s.page.evaluate(() => {
        const vis = (el) => {
            const r = el.getBoundingClientRect();
            const st = getComputedStyle(el);
            return r.width > 0 && r.height > 0 && st.visibility !== 'hidden' && st.display !== 'none' && Number(st.opacity) > 0;
        };
        const name = (el) => (el.getAttribute('data-testid') || el.getAttribute('aria-label') || el.innerText || el.tagName).replace(/\s+/g, ' ').trim().slice(0, 40);
        const targets = [...document.querySelectorAll('button, a[href], input:not([type=hidden]), select, textarea, [role=button], [role=tab]')]
            .filter(vis)
            .filter((el) => !el.closest('svg'))
            .map((el) => {
                const r = el.getBoundingClientRect();
                return { n: name(el), w: Math.round(r.width), h: Math.round(r.height), tag: el.tagName.toLowerCase() };
            });
        const small = targets.filter((t) => t.w < 24 || t.h < 24);
        const tight = targets.filter((t) => (t.w < 44 || t.h < 44) && !(t.w < 24 || t.h < 24));
        const tiny = [...document.querySelectorAll('body *')]
            .filter((el) => el.childNodes.length && [...el.childNodes].some((c) => c.nodeType === 3 && c.textContent.trim()) && vis(el) && !el.closest('svg'))
            .filter((el) => parseFloat(getComputedStyle(el).fontSize) < 11)
            .map((el) => `${name(el)} (${getComputedStyle(el).fontSize})`);
        // fixed elements that overlap each other
        const fixed = [...document.querySelectorAll('body *')].filter((el) => getComputedStyle(el).position === 'fixed' && vis(el)).map((el) => ({ n: name(el), r: el.getBoundingClientRect() }));
        const overlaps = [];
        for (let i = 0; i < fixed.length; i++)
            for (let j = i + 1; j < fixed.length; j++) {
                const a = fixed[i].r;
                const b = fixed[j].r;
                const w = Math.min(a.right, b.right) - Math.max(a.left, b.left);
                const h = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
                if (w > 8 && h > 8 && a.width * a.height < innerWidth * innerHeight * 0.6 && b.width * b.height < innerWidth * innerHeight * 0.6)
                    overlaps.push(`${fixed[i].n} / ${fixed[j].n}`);
            }
        return {
            targets: targets.length,
            small: small.slice(0, 12).map((t) => `${t.n} ${t.w}x${t.h}`),
            smallCount: small.length,
            tightCount: tight.length,
            tiny: [...new Set(tiny)].slice(0, 10),
            overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
            overlaps: [...new Set(overlaps)].slice(0, 6),
        };
    });
    const bits = [];
    if (m.smallCount) bits.push(`${m.smallCount} targets under 24px: ${m.small.join('; ')}`);
    if (m.tightCount) bits.push(`${m.tightCount} targets under 44px`);
    if (m.tiny.length) bits.push(`text under 11px: ${m.tiny.join('; ')}`);
    if (m.overflow > 0) bits.push(`horizontal overflow ${m.overflow}px`);
    if (m.overlaps.length) bits.push(`fixed elements overlap: ${m.overlaps.join('; ')}`);
    console.log(`   [audit ${label}] ${m.targets} targets${bits.length ? ' | ' + bits.join(' | ') : ' | clean'}`);
}

async function axeScan(s, label) {
    if (!HAVE_AXE) return;
    await s.page.evaluate(fs.readFileSync(axePath, 'utf8'));
    const res = await s.page.evaluate(async () => {
        const r = await window.axe.run(document, { resultTypes: ['violations'] });
        return r.violations.map((v) => ({
            id: v.id,
            impact: v.impact,
            help: v.help,
            count: v.nodes.length,
            nodes: v.nodes.slice(0, 3).map((n) => `${n.target.join(' ')} :: ${(n.failureSummary || '').split('\\n')[1] || ''}`.slice(0, 200)),
        }));
    });
    for (const v of res) console.log(`   [axe ${label}] ${v.impact} ${v.id} x${v.count}: ${v.help}\n      ${v.nodes.join('\n      ')}`);
    const serious = res.filter((v) => v.impact === 'serious' || v.impact === 'critical');
    if (serious.length) throw new Error(`${serious.length} serious axe rule(s): ${serious.map((v) => `${v.id}x${v.count}`).join(', ')}`);
}

(async () => {
    const browser = await L.launch();
    const DEMO = [
        { id: 'a1', sp: 5, sz: 3, name: 'Master', side: 'a', baseMm: 50, x: 18, y: 18, info: { health: 7, maxHealth: 9, tokens: [{ id: 1, name: 'Focus' }], activated: true } },
        { id: 'a2', sp: 5, sz: 2, name: 'Henchman', side: 'a', baseMm: 40, x: 12, y: 22 },
        { id: 'b1', sp: 5, sz: 3, name: 'Master', side: 'b', baseMm: 50, x: 19.7, y: 18 },
        { id: 'b2', sp: 5, sz: 2, name: 'Henchman', side: 'b', baseMm: 40, x: 24, y: 28 },
    ];
    for (const [label, vp] of [
        ['desktop', { width: 1280, height: 900 }],
        ['phone', { width: 390, height: 844 }],
    ]) {
        for (const theme of ['light', 'dark']) {
            const s = await L.session(browser, 'carol@example.test', vp);
            await s.page.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: theme }]);
            await s.page.setCookie({ name: 'appearance', value: theme, url: L.BASE });
            // the app keeps its own theme choice (localStorage, read on load), not just the browser's
            await s.page.evaluateOnNewDocument((t) => localStorage.setItem('appearance', t), theme);
            const tag = `${label}-${theme}`;
            const toTable = () => s.page.evaluate(() => document.querySelector('[data-testid=tabletop-board]')?.scrollIntoView());

            await L.step(`audit (${tag}): the sandbox, with models`, s, async () => {
                await L.visit(s, '/tools/tabletop');
                await s.page.evaluate((tokens) => localStorage.setItem('tabletop-spike-v2', JSON.stringify({ tokens })), DEMO);
                await L.visit(s, '/tools/tabletop');
                await s.page.waitForSelector('[data-testid=tabletop-board] svg');
                await L.sleep(500);
                await L.shot(s, `audit-${tag}-sandbox`);
                await measure(s, `${tag} sandbox`);
                await axeScan(s, `${tag} sandbox`);
            });
            if (theme === 'dark') {
                await L.step(`audit (${tag}): the Fate deck works from the keyboard, with a visible focus`, s, async () => {
                    await L.visit(s, '/tools/tabletop');
                    await s.page.waitForSelector('[data-testid=fate-open]');
                    await s.page.$eval('[data-testid=fate-open]', (b) => b.focus());
                    await s.page.keyboard.press('Enter');
                    await s.page.waitForSelector('[data-testid=fate-panel]', { timeout: 5000 });
                    const phone = await s.page.evaluate(() => !!document.querySelector('[data-testid=fate-sheet]'));
                    if (phone) {
                        // a sheet takes focus when it opens
                        const inSheet = await s.page.evaluate(() => document.activeElement === document.querySelector('[data-testid=fate-sheet]'));
                        if (!inSheet) throw new Error('opening the Fate sheet should move focus into it');
                    }
                    // tab forward until the deck button has focus, then draw with Enter
                    let reached = false;
                    for (let i = 0; i < 25 && !reached; i++) {
                        await s.page.keyboard.press('Tab');
                        reached = await s.page.evaluate(() => document.activeElement?.getAttribute('data-testid') === 'fate-draw');
                    }
                    if (!reached) throw new Error('the deck button could not be reached by Tab');
                    const outlined = await s.page.evaluate(() => {
                        const st = getComputedStyle(document.activeElement);
                        return st.outlineStyle !== 'none' || st.boxShadow !== 'none';
                    });
                    if (!outlined) throw new Error('the focused deck button has no visible focus ring');
                    await s.page.keyboard.press('Enter');
                    await s.page.waitForFunction(() => document.querySelector('[data-testid=fate-side-1] [data-testid=fate-hand-count]').innerText.trim() === '1/7', { timeout: 4000 });
                    // Tab carries on to the next control in the panel (it does not fall off the page)
                    await s.page.keyboard.press('Tab');
                    const next = await s.page.evaluate(() => {
                        const el = document.activeElement;
                        return { inPanel: !!el?.closest('[data-testid=fate-panel]'), id: el?.getAttribute('data-testid') };
                    });
                    if (!next.inPanel) throw new Error(`Tab after the deck should stay in the Fate panel, but reached ${next.id}`);
                    if (phone) {
                        // Escape closes the sheet and gives focus back to the Fate button
                        await s.page.keyboard.press('Escape');
                        await s.page.waitForFunction(() => !document.querySelector('[data-testid=fate-sheet]'), { timeout: 3000 });
                        const back = await s.page.evaluate(() => document.activeElement?.getAttribute('data-testid'));
                        if (back !== 'fate-open') throw new Error(`closing the sheet should return focus to the Fate button, not ${back}`);
                    }
                });
            }
            await L.step(`audit (${tag}): a selected model with its options`, s, async () => {
                await toTable();
                const at = await s.page.evaluate(() => {
                    const c = document.querySelectorAll('[data-testid^=token-] > circle')[0];
                    const r = c.getBoundingClientRect();
                    return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
                });
                await s.page.mouse.click(at.x, at.y);
                await s.page.waitForSelector('[data-testid=selected-panel]');
                await s.page.$eval('[data-testid=selected-panel]', (e) => e.scrollIntoView({ block: 'start' }));
                await L.sleep(400);
                await L.shot(s, `audit-${tag}-selected`);
                await measure(s, `${tag} selected`);
                await axeScan(s, `${tag} selected`);
            });
            await L.step(`audit (${tag}): the Fate deck open`, s, async () => {
                // the keyboard step may have opened it already: only open it if it is shut
                if (!(await s.page.$('[data-testid=fate-panel]'))) await s.page.$eval('[data-testid=fate-open]', (b) => b.click());
                await s.page.waitForSelector('[data-testid=fate-panel]');
                await s.page.$eval('[data-testid=fate-draw]', (b) => b.click());
                await s.page.$eval('[data-testid=fate-refill]', (b) => b.click());
                await L.sleep(500);
                await L.shot(s, `audit-${tag}-fate`);
                await measure(s, `${tag} fate`);
                await axeScan(s, `${tag} fate`);
            });
            await L.step(`audit (${tag}): the terrain tab`, s, async () => {
                await s.page.evaluate(() => document.querySelector('[data-testid=fate-close]')?.click());
                await toTable();
                await s.page.$eval('[data-testid=mode-terrain]', (b) => b.click());
                await s.page.waitForSelector('[data-testid=terrain-stats]');
                await s.page.$eval('[data-testid=mode-tabs]', (e) => e.scrollIntoView({ block: 'start' }));
                await L.sleep(400);
                await L.shot(s, `audit-${tag}-terrain`);
                await measure(s, `${tag} terrain`);
                await axeScan(s, `${tag} terrain`);
            });
            await L.step(`audit (${tag}): the tables list`, s, async () => {
                await L.visit(s, '/tools/tabletop/tables');
                await L.sleep(600);
                await L.shot(s, `audit-${tag}-tables`);
                await measure(s, `${tag} tables`);
                await axeScan(s, `${tag} tables`);
            });
            await L.step(`audit (${tag}): the game board`, s, async () => {
                await L.visit(s, `/games/${L.ids.play_game}/board`);
                await s.page.waitForSelector('[data-testid=crew-list] [data-testid^=crew-]', { timeout: 9000 });
                await L.sleep(600);
                await L.shot(s, `audit-${tag}-board`);
                await measure(s, `${tag} board`);
                await axeScan(s, `${tag} board`);
            });
            await L.step(`audit (${tag}): the play screen`, s, async () => {
                await L.visit(s, `/games/${L.ids.play_game}`);
                await s.page.waitForSelector('[data-testid=open-board]', { timeout: 9000 });
                await L.sleep(600);
                await L.shot(s, `audit-${tag}-play`);
                await measure(s, `${tag} play`);
                await axeScan(s, `${tag} play`);
            });
            await s.page.close();
        }
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
