/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const fs = require('fs');
const path = require('path');
const L = require('./lib.cjs');
// The pictures in the in-app help (public/images/help/*.webp). Not a test: it writes files, so it only runs when asked.
//   HELP_SHOTS=1 FLOWS=flows38.cjs CHROME=... scripts/ui-audit/tournaments/run.sh
// Each picture is a light-theme screenshot of a region of a real screen with numbered callouts drawn on the real elements; the numbers match
// the `callouts` legend of the help item that uses it (resources/js/lib/help/topics/*.ts). If a screen changes so that a callout's element
// is gone, the step fails here rather than leaving a picture that points at nothing. Sizes and callout counts go in
// resources/js/lib/help/images.json, which the help test checks against the topics. See docs/features/help.md.
const OUT = process.env.HELP_OUT || path.resolve('public/images/help');
const MANIFEST = path.resolve('resources/js/lib/help/images.json');
const manifest = fs.existsSync(MANIFEST) ? JSON.parse(fs.readFileSync(MANIFEST, 'utf8')) : {};

/** Find a visible element: a CSS selector, or { text, tag } for the first visible element of that tag whose text starts with it. */
const FIND = `
    (spec) => {
        const vis = (el) => { const r = el.getBoundingClientRect(); const st = getComputedStyle(el); return r.width > 0 && r.height > 0 && st.visibility !== 'hidden' && st.display !== 'none'; };
        if (typeof spec === 'string') return [...document.querySelectorAll(spec)].find(vis) ?? null;
        const want = spec.text.toLowerCase();
        const found = [...document.querySelectorAll(spec.tag ?? '*')].find((e) => vis(e) && (e.innerText || e.textContent || '').trim().toLowerCase().startsWith(want) && (spec.tag || e.children.length === 0)) ?? null;
        return found && spec.closest ? found.closest(spec.closest) : found;
    }`;

/** Draw numbered callouts next to the given elements (the number is where the legend says), screenshot the region, and take them off again. */
async function snap(s, name, { region, pad = 10, callouts = [], maxHeight = 700, scrollTo }) {
    const page = s.page;
    if (scrollTo) {
        await page.evaluate(`(${FIND})(${JSON.stringify(scrollTo)})?.scrollIntoView({ block: 'center' })`);
        await L.sleep(150);
    }
    const placed = await page.evaluate(
        `((find, callouts, region, pad, maxHeight) => {
            const docRect = (el) => { const r = el.getBoundingClientRect(); return { x: r.left + scrollX, y: r.top + scrollY, w: r.width, h: r.height }; };
            const missing = [];
            callouts.forEach((spec, i) => {
                const target = spec && spec.sel ? spec.sel : spec;
                const at = spec && spec.sel ? spec.at : undefined;
                const el = find(target);
                if (!el) { missing.push(JSON.stringify(target)); return; }
                const r = docRect(el);
                // where the number sits: beside the element, on the named side, so it never covers what it points at (and numbers on one object do not stack)
                const D = 22;
                const pos = { top: [r.x + r.w / 2 - D / 2, r.y - D + 2], bottom: [r.x + r.w / 2 - D / 2, r.y + r.h - 2], left: [r.x - D + 2, r.y + r.h / 2 - D / 2], right: [r.x + r.w - 2, r.y + r.h / 2 - D / 2] }[at] ?? [r.x - 14, r.y - 14];
                const dot = document.createElement('div');
                dot.className = 'help-callout';
                dot.textContent = String(i + 1);
                dot.style.cssText = 'position:absolute;z-index:99999;width:22px;height:22px;border-radius:9999px;background:#f59e0b;color:#000;font:700 13px/22px sans-serif;text-align:center;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.55);pointer-events:none;left:' + Math.round(pos[0]) + 'px;top:' + Math.round(pos[1]) + 'px';
                document.body.appendChild(dot);
            });
            if (missing.length) return { missing };
            let box;
            if (region && region.board) {
                const svg = document.querySelector('[data-testid=tabletop-board] svg');
                const r = svg.getBoundingClientRect(), vb = svg.viewBox.baseVal;
                const px = (x) => r.left + scrollX + ((x - vb.x) / vb.width) * r.width, py = (y) => r.top + scrollY + ((y - vb.y) / vb.width) * r.width;
                box = { x: px(region.board.x), y: py(region.board.y), w: (region.board.w / vb.width) * r.width, h: (region.board.h / vb.width) * r.width };
            } else if (region === 'callouts') {
                const rs = callouts.map((c) => docRect(find(c && c.sel ? c.sel : c)));
                const x0 = Math.min(...rs.map((r) => r.x)), y0 = Math.min(...rs.map((r) => r.y));
                box = { x: x0, y: y0, w: Math.max(...rs.map((r) => r.x + r.w)) - x0, h: Math.max(...rs.map((r) => r.y + r.h)) - y0 };
            } else if (region === 'viewport') box = { x: scrollX, y: scrollY, w: innerWidth, h: innerHeight };
            else if (region && region.rect) box = region.rect;
            else {
                const el = find(region);
                if (!el) return { missing: [JSON.stringify(region)] };
                box = docRect(el);
            }
            const x = Math.max(0, Math.floor(box.x - pad));
            const y = Math.max(0, Math.floor(box.y - pad));
            const clip = { x, y, width: Math.ceil(box.w + pad * 2), height: Math.min(maxHeight, Math.ceil(box.h + pad * 2)) };
            // every number must be inside the picture, or the legend lists something that cannot be seen
            const outside = [...document.querySelectorAll('.help-callout')]
                .filter((d) => {
                    const l = parseFloat(d.style.left), t = parseFloat(d.style.top);
                    return l < clip.x - 2 || t < clip.y - 2 || l + 22 > clip.x + clip.width + 2 || t + 22 > clip.y + clip.height + 2;
                })
                .map((d) => d.textContent);
            return outside.length ? { outside } : { clip };
        })(${FIND}, ${JSON.stringify(callouts)}, ${JSON.stringify(region)}, ${pad}, ${maxHeight})`,
    );
    try {
        if (placed.missing) throw new Error(name + ': nothing found for ' + placed.missing.join(', '));
        if (placed.outside)
            throw new Error(name + ': callout ' + placed.outside.join(', ') + ' falls outside the picture (raise maxHeight or move the region)');
        await L.sleep(120);
        const buf = await page.screenshot({ type: 'webp', quality: 84, clip: placed.clip, captureBeyondViewport: true });
        const file = path.join(OUT, name);
        fs.mkdirSync(path.dirname(file), { recursive: true });
        fs.writeFileSync(file, buf);
        manifest[name] = { w: Math.round(placed.clip.width), h: Math.round(placed.clip.height), callouts: callouts.length };
    } finally {
        await page.evaluate(() => document.querySelectorAll('.help-callout').forEach((e) => e.remove()));
    }
}

const click = (s, spec) => s.page.evaluate(`(${FIND})(${JSON.stringify(spec)})?.click()`).then(() => L.sleep(250));

(async () => {
    if (!process.env.HELP_SHOTS) {
        console.log('flows38 makes the help pictures; set HELP_SHOTS=1 to run it (see docs/features/help.md)');
        return;
    }
    const browser = await L.launch();
    const session = async (email) => {
        const s = await L.session(browser, email, { width: 1280, height: 1300 });
        await s.page.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: 'light' }]);
        await s.page.setCookie({ name: 'appearance', value: 'light', url: L.BASE });
        await s.page.evaluateOnNewDocument(() => localStorage.setItem('appearance', 'light'));
        return s;
    };
    const carol = await session('carol@example.test');
    // the play screen asks before leaving; accept so the next page loads
    carol.page.on('dialog', (d) => d.accept().catch(() => {}));
    const wait = (sel) => carol.page.waitForSelector(sel, { timeout: 9000 });

    // ---------- Crew Builder ----------
    /** From the crews list, open Demo Patrol with Edit. */
    const openDemoCrew = async () => {
        await L.visit(carol, '/tools/crew-builder/editor');
        await carol.page.waitForFunction(() => /My Crews/.test(document.body.innerText), { timeout: 9000 });
        await click(carol, { text: 'Edit', tag: 'button' });
        await carol.page.waitForSelector('input[placeholder="Search models..."]', { timeout: 12000 });
    };
    await L.step('help picture: Crew Builder, your crews', carol, async () => {
        await L.visit(carol, '/tools/crew-builder/editor');
        await carol.page.waitForFunction(() => /My Crews/.test(document.body.innerText), { timeout: 9000 });
        await L.sleep(400);
        await snap(carol, 'crew-builder/my-crews.webp', {
            region: { rect: { x: 264, y: 150, w: 990, h: 400 } },
            callouts: [
                { text: 'Import', tag: 'button' },
                { text: 'New Crew', tag: 'button' },
                { text: 'Active', tag: 'button' },
                '[aria-label^="More actions for"]',
            ],
        });
    });
    await L.step('help picture: Crew Builder, the hiring pool', carol, async () => {
        await openDemoCrew();
        await carol.page.waitForSelector('input[placeholder="Search models..."]', { timeout: 6000 });
        await L.sleep(500);
        await snap(carol, 'crew-builder/hiring-pool.webp', {
            region: { rect: { x: 272, y: 240, w: 590, h: 420 } },
            callouts: [
                'input[placeholder="Search models..."]',
                { text: 'Keyword', tag: 'button' },
                { text: 'Cost', tag: 'button' },
                '[aria-label="Add to crew"]',
            ],
            maxHeight: 520,
        });
    });
    await L.step('help picture: Crew Builder, the toolbar and export menu', carol, async () => {
        await openDemoCrew();
        await L.sleep(400);
        const trigger = { text: 'Export', tag: 'button' };
        await click(carol, trigger);
        await carol.page.waitForSelector('[role=menuitem]', { timeout: 4000 });
        await snap(carol, 'crew-builder/toolbar.webp', {
            region: { rect: { x: 272, y: 72, w: 984, h: 340 } },
            callouts: [
                'input[placeholder="Crew name..."]',
                '#encounter_size',
                { text: 'Allow over-hiring', tag: 'label' },
                { text: 'Export', tag: 'button' },
                { text: 'Copy as Text', tag: '[role=menuitem]' },
            ],
            maxHeight: 360,
        });
        await carol.page.keyboard.press('Escape');
    });

    // ---------- Game Tracker ----------
    await L.step('help picture: Game Tracker, creating a game', carol, async () => {
        await L.visit(carol, '/games/create');
        await carol.page.waitForFunction(() => /New Encounter/.test(document.body.innerText), { timeout: 9000 });
        await L.sleep(300);
        await snap(carol, 'game-tracker/create.webp', {
            region: { text: 'New Encounter', tag: 'h2', closest: '[class*="rounded-"][class*="border"]' },
            callouts: [
                'input[placeholder^="e.g. League"]',
                '[role=combobox]',
                '[aria-label="Encounter size (soulstones)"]',
                '[aria-label="Solo mode"]',
                { text: 'Create Game', tag: 'button' },
            ],
            maxHeight: 780,
        });
    });
    await L.step('help picture: Game Tracker, the play screen', carol, async () => {
        await L.visit(carol, `/games/${L.ids.play_game}`);
        await wait('[data-testid=open-board]');
        await L.sleep(700);
        await snap(carol, 'game-tracker/play-screen.webp', {
            region: { rect: { x: 256, y: 64, w: 1024, h: 836 } },
            pad: 0,
            callouts: [
                '[data-testid=open-board]',
                { text: 'Fate deck', tag: 'button' },
                '[aria-label="Game settings"]',
                '[title="Soulstone pool"]',
                { text: 'Submit Turn', tag: 'button' },
            ],
            maxHeight: 840,
        });
    });
    await L.step('help picture: Game Tracker, a crew row', carol, async () => {
        await L.visit(carol, `/games/${L.ids.play_game}`);
        await wait('[aria-label^="Decrease health"]');
        await L.sleep(500);
        const row = '[aria-label^="Decrease health"]';
        await snap(carol, 'game-tracker/crew-row.webp', {
            region: 'callouts',
            pad: 26,
            scrollTo: row,
            callouts: [
                { sel: '[title="Mark activated"]', at: 'top' },
                { sel: '[title="Upgrades"]', at: 'top' },
                { sel: '[title="Tokens"]', at: 'top' },
                { sel: '[title="Replace"]', at: 'top' },
                { sel: '[aria-label="Toggle card preview"]', at: 'top' },
                { sel: '[aria-label^="Decrease health"]', at: 'bottom' },
                { sel: '[aria-label^="Increase health"]', at: 'bottom' },
            ],
            maxHeight: 220,
        });
    });
    await L.step('help picture: Game Tracker, game settings', carol, async () => {
        await L.visit(carol, `/games/${L.ids.play_game}`);
        await wait('[aria-label="Game settings"]');
        await click(carol, '[aria-label="Game settings"]');
        await wait('[role=dialog]');
        await L.sleep(500);
        // the spectate link text is a local address in this run: hide it (the QR code stays as it is for any game)
        await carol.page.evaluate(() =>
            document.querySelectorAll('[role=dialog] p, [role=dialog] div').forEach((e) => {
                if (e.children.length === 0 && /^https?:\/\//.test((e.textContent || '').trim())) e.style.visibility = 'hidden';
            }),
        );
        await snap(carol, 'game-tracker/game-settings.webp', {
            region: '[role=dialog]',
            callouts: [
                { text: 'Spectate Mode', tag: 'div' },
                { text: 'Auto Soulstone on Kill', tag: 'div' },
                { text: 'Mark Game Complete', tag: 'div' },
                { text: 'Abandon Game', tag: 'div' },
            ],
            maxHeight: 900,
        });
        await carol.page.keyboard.press('Escape');
    });

    // ---------- Tabletop ----------
    const tokens = [
        {
            id: 'a1',
            sp: 5,
            sz: 3,
            name: 'Master',
            side: 'a',
            baseMm: 50,
            x: 10,
            y: 16,
            card: { faction: 'guild' },
            info: { health: 7, maxHealth: 9, tokens: [{ id: 1, name: 'Focus' }], activated: true },
        },
        {
            id: 'a2',
            sp: 5,
            sz: 2,
            name: 'Henchman',
            side: 'a',
            baseMm: 40,
            x: 8,
            y: 22,
            card: { faction: 'guild' },
            info: { health: 8, maxHealth: 8, tokens: [] },
        },
        {
            id: 'b1',
            sp: 5,
            sz: 3,
            name: 'Master',
            side: 'b',
            baseMm: 50,
            x: 19,
            y: 15,
            card: { faction: 'arcanists' },
            info: { health: 9, maxHealth: 9, tokens: [] },
        },
        {
            id: 'b2',
            sp: 5,
            sz: 2,
            name: 'Henchman',
            side: 'b',
            baseMm: 40,
            x: 21,
            y: 24,
            card: { faction: 'arcanists' },
            info: { health: 3, maxHealth: 8, tokens: [] },
        },
    ];
    const openSandbox = async () => {
        // a larger, sharper board for the pictures
        await carol.page.setViewport({ width: 1600, height: 1500, deviceScaleFactor: 2 });
        await L.visit(carol, '/tools/tabletop');
        await carol.page.evaluate((t) => localStorage.setItem('tabletop-spike-v2', JSON.stringify({ tokens: t })), tokens);
        await L.visit(carol, '/tools/tabletop');
        await wait('[data-testid=tabletop-board] svg');
        await L.sleep(600);
    };
    await L.step('help picture: Tabletop, the board', carol, async () => {
        await openSandbox();
        await snap(carol, 'tabletop/board.webp', {
            region: { board: { x: 3, y: 9, w: 26, h: 21 } },
            scrollTo: '[data-testid=tabletop-board]',
            callouts: [
                { sel: '[data-testid=hp-a1]', at: 'bottom' },
                { sel: '[data-testid=side-ring-a1]', at: 'left' },
                { sel: '[data-testid=badges-a1]', at: 'top' },
                { sel: '[data-testid=hp-b2]', at: 'right' },
            ],
            maxHeight: 900,
            pad: 0,
        });
    });
    await L.step("help picture: Tabletop, a model's options", carol, async () => {
        await openSandbox();
        const board = await carol.page.$eval('[data-testid=tabletop-board] svg', (e) => {
            const r = e.getBoundingClientRect();
            return { left: r.left, top: r.top, w: r.width, vx: e.viewBox.baseVal.x, vy: e.viewBox.baseVal.y, vw: e.viewBox.baseVal.width };
        });
        await carol.page.evaluate(() => document.querySelector('[data-testid=tabletop-board]').scrollIntoView());
        const at = await carol.page.$eval('[data-testid=tabletop-board] svg', (e) => e.getBoundingClientRect().top);
        void board;
        await carol.page.mouse.click(board.left + ((10 - board.vx) / board.vw) * board.w, at + ((16 - board.vy) / board.vw) * board.w);
        await wait('[data-testid=selected-panel]');
        await L.sleep(400);
        await snap(carol, 'tabletop/model-options.webp', {
            region: '[data-testid=selected-panel]',
            scrollTo: '[data-testid=selected-panel]',
            callouts: ['[data-testid=model-guide]', { sel: '[data-testid=ignores-section]', at: 'right' }, '[data-testid=model-kill]'],
            maxHeight: 820,
        });
    });
    await L.step('help picture: Tabletop, the terrain editor', carol, async () => {
        await openSandbox();
        await carol.page.click('[data-testid=mode-terrain]');
        await wait('[data-testid=terrain-editor]');
        await carol.page.$eval('[data-testid=tabletop-board] svg', (el) => el.focus());
        await carol.page.keyboard.press(']');
        await L.sleep(500);
        await snap(carol, 'tabletop/terrain-editor.webp', {
            region: '[data-testid=terrain-editor]',
            scrollTo: '[data-testid=terrain-editor]',
            callouts: [
                '[data-testid=terrain-preset]',
                '[data-testid=terrain-draw]',
                '[data-testid=terrain-rotate]',
                { text: 'Traits', tag: 'summary' },
            ],
            maxHeight: 760,
        });
    });
    await L.step('help picture: Tabletop, the Fate deck', carol, async () => {
        await openSandbox();
        await carol.page.$eval('[data-testid=fate-open]', (el) => {
            el.scrollIntoView({ block: 'center' });
            el.click();
        });
        await wait('[data-testid=fate-panel]');
        await carol.page.$eval('[data-testid=fate-draw]', (b) => b.click());
        await L.sleep(300);
        await carol.page.$eval('[data-testid=fate-draw]', (b) => b.click());
        await L.sleep(500);
        await snap(carol, 'tabletop/fate-deck.webp', {
            region: '[data-testid=fate-docked]',
            scrollTo: '[data-testid=fate-docked]',
            callouts: ['[data-testid=fate-draw]', '[data-testid=fate-flip]', '[data-testid=fate-hand-card]'],
            maxHeight: 720,
        });
    });

    await browser.close();
    const sorted = Object.fromEntries(Object.entries(manifest).sort(([a], [b]) => a.localeCompare(b)));
    fs.writeFileSync(MANIFEST, JSON.stringify(sorted, null, 4) + '\n');
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} pictures made (public/images/help)`);
    for (const r of L.results) console.log(`${r.status} ${r.name}${r.detail ? '\n       ' + r.detail : ''}`);
    if (bad.length) process.exit(1);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
