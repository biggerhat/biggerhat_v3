/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Virtual tabletop spike: the board renders, a model dragged onto raised terrain climbs to that level, the ruler measures
// edge to edge, the level filter and deployment overlay work, and the page never scrolls sideways on a phone.
(async () => {
    const browser = await L.launch();
    const hscroll = (s) => s.page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

    for (const [label, vp] of [
        ['desktop', { width: 1280, height: 900 }],
        ['phone', { width: 390, height: 844 }],
    ]) {
        const s = await L.session(browser, 'alice@example.test', vp);
        // Board inches to client pixels, read from the live SVG so the test does not depend on layout.
        const pixel = (x, y) =>
            s.page.evaluate(
                ([bx, by]) => {
                    const svg = document.querySelector('[data-testid=tabletop-board] svg');
                    const r = svg.getBoundingClientRect();
                    const vb = svg.viewBox.baseVal;
                    return { x: r.left + ((bx - vb.x) / vb.width) * r.width, y: r.top + ((by - vb.y) / vb.height) * r.height };
                },
                [x, y],
            );
        // Click a control after centring it: on a phone the fixed tab bar can sit over a button scrolled to the very bottom.
        const hit = async (selector) => {
            await s.page.$eval(selector, (b) => b.scrollIntoView({ block: 'center' }));
            await s.page.click(selector);
        };
        const toBoardTop = () => s.page.evaluate(() => document.querySelector('[data-testid=tabletop-board]').scrollIntoView());
        // Sight guidelines are switched on from a model's panel: select the model at a spot on the table, then press its toggle.
        const guideOn = async (x, y) => {
            await toBoardTop();
            const m = await pixel(x, y);
            await s.page.mouse.click(m.x, m.y);
            await s.page.waitForSelector('[data-testid=model-guide]');
            await hit('[data-testid=model-guide]');
            await toBoardTop();
        };
        // The sandbox ships no models of its own (only database characters and markers can be added), so the browser flows lay a known
        // set on the table through the page's storage, then reload.
        const DEMO = [
            { id: 'a1', sp: 5, sz: 3, name: 'Master', side: 'a', baseMm: 50, x: 18, y: 4 },
            { id: 'a2', sp: 5, sz: 2, name: 'Henchman', side: 'a', baseMm: 40, x: 12, y: 5 },
            { id: 'a3', sp: 5, sz: 1, name: 'Minion', side: 'a', baseMm: 30, x: 22, y: 4 },
            { id: 'b1', sp: 5, sz: 3, name: 'Master', side: 'b', baseMm: 50, x: 18, y: 32 },
            { id: 'b2', sp: 5, sz: 2, name: 'Henchman', side: 'b', baseMm: 40, x: 24, y: 31 },
            { id: 'b3', sp: 5, sz: 1, name: 'Minion', side: 'b', baseMm: 30, x: 12, y: 32 },
        ];
        const openSandbox = async (models = DEMO) => {
            await L.visit(s, '/tools/tabletop');
            await s.page.evaluate((tokens) => {
                let saved = {};
                try {
                    saved = JSON.parse(localStorage.getItem('tabletop-spike-v2')) || {};
                } catch {
                    // start from nothing
                }
                localStorage.setItem('tabletop-spike-v2', JSON.stringify({ ...saved, tokens }));
            }, models);
            await L.visit(s, '/tools/tabletop');
        };
        const dragTo = async (from, to) => {
            const a = await pixel(...from);
            const b = await pixel(...to);
            await s.page.mouse.move(a.x, a.y);
            await s.page.mouse.down();
            await s.page.mouse.move((a.x + b.x) / 2, (a.y + b.y) / 2, { steps: 4 });
            await s.page.mouse.move(b.x, b.y, { steps: 4 });
            await s.page.mouse.up();
        };

        await L.step(`tabletop (${label}): board renders with terrain levels and both crews`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            await L.expectText(s, /Ht 5/);
            if ((await s.page.$$('[data-testid^=token-]')).length !== 6) throw new Error('expected 6 demo models');
            if ((await hscroll(s)) > 1) throw new Error(`page scrolls sideways by ${await hscroll(s)}px`);
            await L.shot(s, `350-tabletop-${label}`);
        });
        await L.step(`tabletop (${label}): dragging a model onto the tower roof raises it to Ht 3`, s, async () => {
            await dragTo([22, 4], [10, 8]); // the minion, onto the Ht 3 roof
            await L.expectText(s, /climbed 3"/i);
            // the move is drawn: a dashed line from where it started, labelled with the distance
            await s.page.waitForSelector('[data-testid=move-trail]');
            const dist = await s.page.$eval('[data-testid=move-trail-label]', (e) => e.textContent.trim());
            if (!/^\d+(\.\d)?" of 5"$/.test(dist)) throw new Error(`unexpected trail label: ${dist}`);
            if (!(await s.page.$('[data-testid=walk-reach]'))) throw new Error('a model with a Sp shows how far a Walk reaches');
            await L.expectText(s, /more than a Walk \(Sp 5\)/i); // 12 across and 3 up is more than a Walk of 5
            await dragTo([10, 8], [5.5, 5.5]); // then up onto the Ht 5 upper level
            await L.expectText(s, /climbed 2"/i);
            await dragTo([5.5, 5.5], [20, 12]); // and off to the table: a drop of 5
            await L.expectText(s, /1 irreducible damage/i);
        });
        await L.step(`tabletop (${label}): a model dropped on another base, or on impassable ground, is flagged`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            await s.page.click('[data-testid=mode-move]');
            await dragTo([12, 32], [24, 31.3]); // the minion onto the red henchman
            await L.expectText(s, /overlaps Henchman's base/i);
            await s.page.waitForSelector('[data-problem=true]');
            await s.page.waitForSelector('[data-testid=placement-warning]');
            await dragTo([24, 31.3], [12, 32]); // put it back
            await s.page.waitForFunction(() => !document.querySelector('[data-problem=true]'), { timeout: 5000 });
        });
        await L.step(`tabletop (${label}): a stepped move adds its legs: 2 inches then 5 inches is 7`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            await s.page.click('[data-testid=mode-move]');
            await s.page.$eval('[data-testid=step-toggle]', (b) => b.scrollIntoView({ block: 'center' }));
            await s.page.click('[data-testid=step-toggle]');
            await s.page.waitForSelector('[data-testid=step-help]');
            await dragTo([18, 4], [18, 6]); // the master: 2" down
            await dragTo([18, 6], [18, 11]); // then 5" more
            await s.page.waitForSelector('[data-testid=move-trail-path]');
            const legs = await s.page.$$eval('[data-testid=move-trail-leg]', (e) => e.map((x) => x.textContent.trim()));
            if (legs.length !== 2) throw new Error(`expected two leg labels, saw ${legs}`);
            const total = await s.page.$eval('[data-testid=move-trail-label]', (e) => e.textContent.trim());
            if (!/^7(\.\d)?" of 5"$/.test(total)) throw new Error(`the stepped total should be 7" of 5", saw ${total}`);
            await L.expectText(s, /moved 7(\.\d)?" \(2(\.\d)?" \+ 5(\.\d)?"\)/);
            await L.expectText(s, /more than a Walk \(Sp 5\)/i);
            // without Step, a second drag starts a fresh measure
            await s.page.click('[data-testid=step-toggle]');
            await dragTo([18, 11], [18, 13]);
            await L.expectText(s, /moved 2(\.\d)?"/);
            if ((await s.page.$$('[data-testid=move-trail-leg]')).length) throw new Error('a plain drag should not keep earlier legs');
            await s.page.click('[data-testid=reset-table]');
        });
        await L.step(`tabletop (${label}): the ruler measures edge to edge between two models`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            await s.page.click('[data-testid=mode-ruler]');
            await dragTo([12, 5], [18, 4]); // henchman (40mm) to master (50mm), centres 6.08" apart
            await s.page.waitForSelector('[data-testid=ruler-readout]');
            const t = await s.page.$eval('[data-testid=ruler-readout]', (e) => e.innerText);
            // 6.08 - 0.787 - 0.984 = 4.3
            if (!/4\.3"/.test(t)) throw new Error(`ruler readout is not 4.3": ${t}`);
        });
        await L.step(`tabletop (${label}): line of sight is clear in the open and blocked by a wall for a shorter model`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            await s.page.click('[data-testid=mode-move]');
            await dragTo([12, 32], [18, 20]); // the Sz 1 minion steps up behind the Ht 2 wall
            await guideOn(18, 4);
            await dragTo([18, 4], [18, 32]); // master to master, Sz 3 each: the Ht 2 wall is shorter than both
            await s.page.waitForSelector('[data-testid=sight-readout]');
            let t = await s.page.$eval('[data-testid=sight-readout]', (e) => e.innerText);
            if (!/Master can see Master/.test(t)) throw new Error(`expected a clear line in the open: ${t}`);
            await dragTo([18, 4], [18, 20]); // master to the minion behind the wall
            await L.sleep(200);
            t = await s.page.$eval('[data-testid=sight-readout]', (e) => e.innerText);
            if (!/cannot see Minion: blocked by Wall/.test(t)) throw new Error(`expected the wall to block: ${t}`);
            await L.shot(s, `357-sight-${label}`);
            await s.page.click('[data-testid=mode-move]');
            await dragTo([18, 20], [12, 32]); // put the minion back
        });
        await L.step(`tabletop (${label}): a line leaves each side of the base straight in the direction of the finger`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            await s.page.click('[data-testid=mode-move]');
            await dragTo([12, 32], [18, 20]); // the Sz 1 minion steps up behind the Ht 2 wall
            await guideOn(18, 4);
            const summaries = () => s.page.$$eval('[data-testid=side-summary]', (els) => els.map((e) => e.innerText));
            await dragTo([18, 4], [28, 14]); // aim at empty ground
            await s.page.waitForSelector('[data-testid=side-line-left]');
            await s.page.waitForSelector('[data-testid=side-line-right]');
            let t = await summaries();
            if (t.length !== 2 || !t.every((x) => /Reaches no model/.test(x))) throw new Error(`empty ground should reach nothing: ${t.join(' | ')}`);
            // each line starts on the base edge, a 50 mm base's radius (0.98") from the centre, square to the aim
            for (const side of ['left', 'right']) {
                const d = await s.page.$eval(`[data-testid=side-line-${side}]`, (l) =>
                    Math.hypot(Number(l.getAttribute('x1')) - 18, Number(l.getAttribute('y1')) - 4),
                );
                if (Math.abs(d - 0.984) > 0.05) throw new Error(`the ${side} line should start on the base edge, ${d}" from the centre`);
            }
            // the lines run parallel, straight off the base: they do not close in on the aim
            const gap = async () =>
                s.page.evaluate(() => {
                    const [l, r] = ['left', 'right'].map((k) => document.querySelector(`[data-testid=side-line-${k}]`));
                    const g = (n) => Number(l.getAttribute(n)) - Number(r.getAttribute(n));
                    return { start: Math.hypot(g('x1'), g('y1')), end: Math.hypot(g('x2'), g('y2')) };
                });
            await dragTo([18, 4], [19, 20]); // aim just beside the minion behind the wall: one line crosses it
            await L.sleep(200);
            const g1 = await gap();
            if (Math.abs(g1.start - g1.end) > 0.05) throw new Error(`the lines should stay parallel, gap ${g1.start}" then ${g1.end}"`);
            t = await summaries();
            if (!t.some((x) => /Minion \(hidden by Wall/.test(x)))
                throw new Error(`expected a line to reach the minion behind the wall: ${t.join(' | ')}`);
            await s.page.waitForSelector('[data-testid=side-hidden]');
            await dragTo([18, 4], [18, 32]); // dropped on the other master: both lines run down its sides, and the full check still appears
            await L.sleep(200);
            await s.page.waitForSelector('[data-testid=sight-readout]');
            const g2 = await gap();
            if (Math.abs(g2.start - g2.end) > 0.05) throw new Error(`the lines should stay parallel, gap ${g2.start}" then ${g2.end}"`);
            await L.shot(s, `358-side-lines-${label}`);
            await dragTo([2, 30], [3, 31]); // empty ground: the lines stay, aimed there
            await s.page.waitForSelector('[data-testid=side-lines]');
            await toBoardTop();
            await hit('[data-testid=model-guide]'); // the toggle switches them off
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=side-lines]'), { timeout: 5000 });
            await hit('[data-testid=reset-table]');
        });
        await L.step(`tabletop (${label}): sight guidelines can be switched on from a model's panel and stay on, aimed by dragging`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            await toBoardTop();
            const m = await pixel(18, 4); // the first master
            await s.page.mouse.click(m.x, m.y); // Move mode: selects it
            await s.page.waitForSelector('[data-testid=model-guide]');
            await hit('[data-testid=model-guide]');
            await s.page.waitForSelector('[data-testid=side-lines]');
            await s.page.waitForSelector('[data-testid=guide-hint]');
            const lineEnd = () => s.page.$eval('[data-testid=side-line-left]', (l) => `${l.getAttribute('x2')},${l.getAttribute('y2')}`);
            const first = await lineEnd();
            // pressing empty ground in Sight mode re-aims them, and they stay after the finger lifts
            await toBoardTop();
            await dragTo([2, 20], [3, 21]);
            const second = await lineEnd();
            if (first === second) throw new Error('aiming should move the lines');
            await s.page.waitForSelector('[data-testid=side-lines]');
            // they follow the model when it moves
            await hit('[data-testid=mode-move]');
            await toBoardTop();
            await dragTo([18, 4], [18, 8]);
            await s.page.waitForSelector('[data-testid=side-lines]');
            const moved = await s.page.$eval('[data-testid=side-line-left]', (l) => Number(l.getAttribute('y1')));
            if (moved < 6) throw new Error(`the lines should follow the model down the table, saw y1=${moved}`);
            await hit('[data-testid=model-guide]');
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=side-lines]'), { timeout: 5000 });
            await L.shot(s, `363-sight-guide-${label}`);
            await hit('[data-testid=reset-table]');
        });
        await L.step(`tabletop (${label}): a place is measured as a place, a Flight walk ignores terrain height, and a Walk is unchanged`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            const note = () => s.page.$eval('[data-testid=tabletop-note]', (e) => e.innerText);
            // a place: the minion is put down 4" away over the wall, ignoring it
            await s.page.select('[data-testid=move-kind]', 'place');
            await s.page.waitForSelector('[data-testid=place-range]');
            await s.page.$eval('[data-testid=place-range]', (i) => {
                i.value = '6';
                i.dispatchEvent(new Event('input', { bubbles: true }));
            });
            await toBoardTop();
            await dragTo([12, 32], [12, 28]);
            await s.page.waitForSelector('[data-testid=place-reach]');
            let t = await note();
            if (!/placed .* within the 6" place/.test(t) || !/nothing is spent climbing or falling/.test(t)) throw new Error(`expected a place readout: ${t}`);
            if (/Walk/.test(t)) throw new Error(`a place is not measured against a Walk: ${t}`);
            // too far for the range
            await toBoardTop();
            await dragTo([12, 28], [12, 18]);
            t = await note();
            if (!/beyond the 6" place/.test(t)) throw new Error(`expected the place to be too far: ${t}`);
            // the whole base must be within range: the same short drop still passes, and the toggle is pressed
            await hit('[data-testid=place-whole]');
            if ((await s.page.$eval('[data-testid=place-whole]', (b) => b.getAttribute('aria-pressed'))) !== 'true') throw new Error('whole base did not toggle');
            // a Flight walk: height is ignored, the Walk still counts
            await s.page.select('[data-testid=move-kind]', 'fly');
            await toBoardTop();
            await dragTo([12, 18], [12, 22]);
            t = await note();
            if (!/ignoring terrain height/.test(t) || !/of a Walk/.test(t)) throw new Error(`expected a Flight readout against a Walk: ${t}`);
            if (await s.page.$('[data-testid=place-reach]')) throw new Error('a Flight walk has no place ring');
            // and a plain Walk reads as before
            await s.page.select('[data-testid=move-kind]', 'walk');
            await toBoardTop();
            await dragTo([12, 22], [12, 26]);
            t = await note();
            if (!/moved .*of a Walk/.test(t) || /ignoring terrain height/.test(t)) throw new Error(`expected a plain Walk readout: ${t}`);
            await L.shot(s, `364-place-walk-${label}`);
            await hit('[data-testid=reset-table]');
        });
        await L.step(`tabletop (${label}): a model can go under a bridge's archway, climb onto the deck, and a tall one does not fit`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            await s.page.waitForSelector('[data-testid=archway]');
            const under = (id) => s.page.$eval(`[data-testid=token-${id}] circle`, (c) => c.getAttribute('data-under'));
            const note = () => s.page.$eval('[data-testid=tabletop-note]', (e) => e.innerText);
            // the Sz 1 minion walks from the ground under the Ht 3 deck: beneath it, still on the ground
            await toBoardTop();
            await dragTo([12, 32], [12, 28]);
            if ((await under('b3')) !== 'true') throw new Error('a model that came from the ground should be under the bridge');
            await s.page.waitForSelector('[data-testid=under-toggle]');
            await L.expectText(s, /Elevation 0\s+under Bridge/);
            // climb onto the deck with the toggle
            await hit('[data-testid=model-on]');
            await s.page.waitForFunction(() => document.querySelector('[data-testid=token-b3] circle')?.getAttribute('data-under') === null, { timeout: 5000 });
            await L.expectText(s, /Elevation 3 on Bridge/);
            await hit('[data-testid=model-under]');
            await L.expectText(s, /Elevation 0\s+under Bridge/);
            // the Sz 3 master does not fit under a 2" archway
            await toBoardTop();
            await dragTo([18, 32], [12, 28]);
            const t = await note();
            if (!/too tall/.test(t)) throw new Error(`expected a too-tall warning: ${t}`);
            await L.shot(s, `365-bridge-${label}`);
            await hit('[data-testid=reset-table]');
        });
        await L.step(`tabletop (${label}): a building can be made enterable, shown cut away, and a model walks in by the door but not through a wall`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            await hit('[data-testid=mode-terrain]');
            await s.page.select('[data-testid=terrain-preset]', 'building');
            await hit('[data-testid=terrain-add]');
            await s.page.waitForSelector('[data-testid=terrain-enterable]');
            await hit('[data-testid=terrain-enterable]');
            await s.page.waitForSelector('[data-testid=terrain-floor]');
            await hit('[data-testid=terrain-add-window]');
            await s.page.waitForFunction(() => document.querySelectorAll('[data-testid=terrain-opening]').length === 2, { timeout: 5000 });
            await s.page.waitForSelector('[data-testid=building-door]');
            await s.page.waitForSelector('[data-testid=building-window]');
            const door = await s.page.$eval('[data-testid="handle-opening-0"]', (e) => ({ x: Number(e.dataset.x), y: Number(e.dataset.y) }));
            // the roof fades and walls show when the inside is shown
            if (await s.page.$('[data-testid=building-wall]')) throw new Error('walls should only draw in the cutaway');
            await hit('[data-testid=terrain-show-inside]');
            await s.page.waitForSelector('[data-testid=building-wall]');
            await s.page.waitForSelector('[data-testid=building-floor]');
            await L.shot(s, `366-building-cutaway-${label}`);
            // walk the Sz 1 minion in through the door, which is in the top wall
            await hit('[data-testid=mode-move]');
            await toBoardTop();
            const outside = { x: door.x, y: door.y - 3 };
            await dragTo([12, 32], [outside.x, outside.y]);
            await dragTo([outside.x, outside.y], [door.x, door.y + 1.5]);
            const t1 = await s.page.$eval('[data-testid=tabletop-note]', (e) => e.innerText);
            if (/through the wall/.test(t1)) throw new Error(`the door is a way in: ${t1}`);
            if ((await s.page.$eval('[data-testid=token-b3] circle', (c) => c.getAttribute('data-under'))) !== 'true') throw new Error('a model in the building should be inside it');
            await L.expectText(s, /inside Building/);
            // and out through the left wall, which has no opening
            await toBoardTop();
            await dragTo([door.x, door.y + 1.5], [door.x - 6, door.y + 1.5]);
            const t2 = await s.page.$eval('[data-testid=tabletop-note]', (e) => e.innerText);
            if (!/through the wall of Building/.test(t2)) throw new Error(`expected a wall warning: ${t2}`);
            await hit('[data-testid=reset-table]');
        });
        await L.step(`tabletop (${label}): terrain is drawn with its texture, and a table surface can be chosen and is kept`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            const textured = await s.page.$$eval('[data-testid=terrain-texture]', (els) =>
                [...new Set(els.map((e) => e.getAttribute('data-kind')))].sort(),
            );
            if (!['crates', 'hill', 'tower', 'wall'].every((k) => textured.includes(k)))
                throw new Error(`expected textures on the demo terrain, saw ${textured}`);
            const surface = () => s.page.$eval('[data-testid=table-surface]', (e) => e.getAttribute('data-surface'));
            if ((await surface()) !== 'grass') throw new Error(`the table should start as grass, saw ${await surface()}`);
            await hit('[data-testid=table-select]');
            const options = await s.page.$$eval('[data-testid=table-select] option', (os) => os.map((o) => o.value));
            if (options.length !== 6 || options[0] !== 'grass') throw new Error(`expected six built-in surfaces, grass first, saw ${options}`);
            await s.page.select('[data-testid=table-select]', 'felt');
            await s.page.waitForFunction(() => document.querySelector('[data-testid=table-surface]')?.getAttribute('data-surface') === 'felt', {
                timeout: 5000,
            });
            await toBoardTop();
            await L.shot(s, `359-table-surface-${label}`);
            await openSandbox();
            if ((await surface()) !== 'felt') throw new Error(`the surface should be kept on this device, saw ${await surface()}`);
            await hit('[data-testid=table-select]');
            await s.page.select('[data-testid=table-select]', 'grass');
            await s.page.waitForFunction(() => document.querySelector('[data-testid=table-surface]')?.getAttribute('data-surface') === 'grass', {
                timeout: 5000,
            });
        });
        await L.step(`tabletop (${label}): trees, rivers, marsh, hazards and the other ground terrain can be added and are textured`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            await hit('[data-testid=mode-terrain]');
            const kinds = ['woods', 'tree', 'brambles', 'river', 'deepwater', 'marsh', 'tallgrass', 'rubble', 'acid', 'fire'];
            for (const k of kinds) {
                await s.page.select('[data-testid=terrain-preset]', k);
                await hit('[data-testid=terrain-add]');
            }
            await s.page.waitForFunction(() => /15 pieces/.test(document.querySelector('[data-testid=terrain-stats]').innerText), { timeout: 5000 });
            const seen = await s.page.$$eval('[data-testid=terrain-texture]', (els) => els.map((e) => e.getAttribute('data-kind')));
            const missing = kinds.filter((k) => !seen.includes(k));
            if (missing.length) throw new Error(`no texture drawn for ${missing.join(', ')}`);
            await s.page.waitForSelector('[data-testid=trait-severe]');
            await L.shot(s, `360-ground-terrain-${label}`);
            await hit('[data-testid=reset-table]');
        });
        await L.step(`tabletop (${label}): a piece can be reshaped with handles, turned to any angle, and an outline gains and loses corners`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            await hit('[data-testid=mode-terrain]');
            const handle = (id) => s.page.$eval(`[data-testid="handle-${id}"]`, (e) => ({ x: Number(e.dataset.x), y: Number(e.dataset.y) }));
            const add = async (key) => {
                await s.page.select('[data-testid=terrain-preset]', key);
                await hit('[data-testid=terrain-add]');
                await s.page.waitForSelector('[data-testid=terrain-handles]');
            };
            const val = (id) => s.page.$eval(`[data-testid=${id}]`, (i) => Number(i.value));
            const drag = async (from, to) => {
                await toBoardTop();
                await dragTo(from, to);
            };
            // a rectangle: drag its bottom-right corner out
            await add('block');
            const w0 = await val('terrain-w');
            const c = await handle('corner-2');
            await drag([c.x, c.y], [c.x + 3, c.y + 2]);
            const w1 = await val('terrain-w');
            if (w1 !== w0 + 3) throw new Error(`dragging a corner should widen the block by 3", ${w0} then ${w1}`);
            // the turn buttons and the angle box
            await hit('[data-testid=terrain-rotate-on]');
            if ((await val('terrain-angle')) !== 15) throw new Error(`turning 15 degrees should set the angle to 15, saw ${await val('terrain-angle')}`);
            await s.page.$eval('[data-testid=terrain-angle]', (i) => {
                i.value = '40';
                i.dispatchEvent(new Event('change', { bubbles: true }));
            });
            await s.page.waitForFunction(() => document.querySelector('[data-testid=terrain-angle]').value === '40', { timeout: 5000 });
            // dragging the rotate dot sideways turns the piece about its middle
            const dot = await handle('rotate');
            await drag([dot.x, dot.y], [dot.x + 8, dot.y + 8]);
            if ((await val('terrain-angle')) === 40) throw new Error('the rotate dot did not turn the piece');
            await hit('[data-testid=terrain-undo]');
            // an oval shows its own radii
            await add('pond');
            await s.page.waitForSelector('[data-testid=terrain-rx]');
            const rx0 = await val('terrain-rx');
            const e = await handle('edge-0');
            await drag([e.x, e.y], [e.x + 2, e.y]);
            if ((await val('terrain-rx')) <= rx0) throw new Error('dragging the end of an oval should lengthen it');
            // an outline: add a corner from the middle of an edge, pick one and take it away
            await add('lbuilding');
            const count = () => s.page.$$eval('[data-testid^="handle-vertex-"]', (e) => e.length);
            const before = await count();
            const m = await handle('mid-0');
            await drag([m.x, m.y], [m.x, m.y - 2]);
            if ((await count()) !== before + 1) throw new Error(`dragging an edge middle should add a corner, ${before} then ${await count()}`);
            const v = await handle('vertex-1');
            await drag([v.x, v.y], [v.x, v.y]);
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=terrain-remove-corner]').disabled, { timeout: 5000 });
            await hit('[data-testid=terrain-remove-corner]');
            await s.page.waitForFunction((n) => document.querySelectorAll('[data-testid^="handle-vertex-"]').length === n, { timeout: 5000 }, before);
            // any piece, an outline included, can be given a background
            const textured = (kind) =>
                s.page.waitForFunction(
                    (k, want) => [...document.querySelectorAll('[data-testid=terrain-texture]')].some((e) => e.getAttribute('data-kind') === k) === want,
                    { timeout: 5000 },
                    kind,
                    true,
                );
            await s.page.select('[data-testid=terrain-background]', 'woods');
            await textured('woods');
            await s.page.select('[data-testid=terrain-background]', '');
            await s.page.waitForFunction(
                () => ![...document.querySelectorAll('[data-testid=terrain-texture]')].some((e) => e.getAttribute('data-kind') === 'woods'),
                { timeout: 5000 },
            );
            await L.shot(s, `361-shape-handles-${label}`);
            await hit('[data-testid=reset-table]');
        });
        await L.step(`tabletop (${label}): a table can be saved, loaded back, shared by link and opened from the link`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=layout-library]');
            const items = () => s.page.$$eval('[data-testid=layout-item]', (e) => e.length);
            const openLibrary = () => s.page.$eval('[data-testid=layout-library]', (d) => (d.open = true));
            await openLibrary();
            const had = await items();
            await s.page.type('[data-testid=layout-name]', 'Harness table');
            await hit('[data-testid=layout-save]');
            await s.page.waitForFunction((n) => document.querySelectorAll('[data-testid=layout-item]').length > n, { timeout: 8000 }, had);
            const saved = await items();
            // clear the table, then load the saved one back
            await hit('[data-testid=mode-terrain]');
            await hit('[data-testid=terrain-clear]');
            await s.page.waitForFunction(() => /0 pieces/.test(document.querySelector('[data-testid=terrain-stats]').innerText), { timeout: 5000 });
            await openLibrary();
            await hit('[data-testid=layout-load]');
            await s.page.waitForFunction(() => /5 pieces/.test(document.querySelector('[data-testid=terrain-stats]').innerText), { timeout: 5000 });
            // share it, and open it by its link
            await openLibrary();
            await hit('[data-testid=layout-share]');
            await s.page.waitForSelector('[data-testid=layout-link]', { timeout: 8000 });
            const uuid = await s.page.$eval('[data-testid=layout-item]', (e) => e.dataset.uuid);
            await L.visit(s, `/tools/tabletop?layout=${uuid}`);
            await s.page.waitForSelector('[data-testid=incoming-layout]');
            await L.expectText(s, /Harness table/);
            await L.shot(s, `362-shared-layout-${label}`);
            await hit('[data-testid=incoming-leave]');
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=incoming-layout]'), { timeout: 8000 });
            if ((await items()) !== saved) throw new Error(`expected ${saved} saved tables, saw ${await items()}`);
            await hit('[data-testid=reset-table]');
        });
        await L.step(`tabletop (${label}): level filter and deployment overlay`, s, async () => {
            await s.page.select('[data-testid=level-select]', '0');
            await s.page.select('[data-testid=deployment-select]', 'standard');
            await L.shot(s, `351-tabletop-filter-${label}`);
            if ((await hscroll(s)) > 1) throw new Error(`page scrolls sideways by ${await hscroll(s)}px`);
        });
        await L.step(`tabletop (${label}): all four deployments draw their real shapes and a centerline`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            // What each deployment draws for side A: a rectangle strip, an L-shaped flank, a quarter circle, a triangle.
            const expected = { standard: 'polygon', flank: 'polygon', corner: 'circle', wedge: 'polygon' };
            const pointsOf = () =>
                s.page.evaluate(() => (document.querySelector('[data-testid=zone-a]').getAttribute('points') || '').trim().split(/\s+/).length);
            const wanted = { standard: 4, flank: 6, wedge: 3 };
            for (const kind of ['standard', 'flank', 'corner', 'wedge']) {
                await s.page.select('[data-testid=deployment-select]', kind);
                await s.page.waitForSelector('[data-testid=zone-a]');
                const tag = await s.page.$eval('[data-testid=zone-a]', (e) => e.tagName.toLowerCase());
                if (tag !== expected[kind]) throw new Error(`${kind} draws a <${tag}>, expected <${expected[kind]}>`);
                if (wanted[kind] && (await pointsOf()) !== wanted[kind])
                    throw new Error(`${kind} has ${await pointsOf()} corners, expected ${wanted[kind]}`);
                if (!(await s.page.$('[data-testid=centerline]'))) throw new Error(`${kind} has no centerline`);
                const variants = await s.page.$$eval('[data-testid=deployment-variant] option', (o) => o.length);
                if (variants !== 4) throw new Error(`${kind} offers ${variants} placements, expected 4`);
                await L.shot(s, `354-deployment-${kind}-${label}`);
            }
            // Turning the table moves the zone: the corner circle goes from the top-right corner to the bottom-right.
            const centre = () =>
                s.page.$eval('[data-testid=zone-a]', (e) => ({ cx: Number(e.getAttribute('cx')), cy: Number(e.getAttribute('cy')) }));
            await s.page.select('[data-testid=deployment-select]', 'corner');
            const first = await centre();
            await s.page.select('[data-testid=deployment-variant]', '1');
            const second = await centre();
            if (!(first.cx === 36 && first.cy === 0 && second.cx === 36 && second.cy === 36)) {
                throw new Error(`corner did not turn: ${JSON.stringify(first)} then ${JSON.stringify(second)}`);
            }
            if ((await hscroll(s)) > 1) throw new Error(`page scrolls sideways by ${await hscroll(s)}px`);
        });
        await L.step(`tabletop (${label}): an aura starts at the base edge and shows who is inside`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            await s.page.evaluate(() => document.querySelector('[data-testid=tabletop-board]').scrollIntoView()); // the controls sit below the board on a phone
            const a = await pixel(18, 4); // the blue master, 50 mm base, with the henchman (12, 5) and minion (22, 4) around it
            await s.page.mouse.click(a.x, a.y);
            await hit('[data-testid=aura-add]');
            await s.page.waitForSelector('[data-testid=aura-a1]');
            const radius = () => s.page.$eval('[data-testid=aura-a1] circle', (c) => Number(c.getAttribute('r')));
            const r6 = await radius();
            // 50 mm base: radius 0.984", so a 6" aura is 6.984" from the centre
            if (Math.abs(r6 - (6 + 25 / 25.4)) > 0.01) throw new Error(`6" aura radius is ${r6}, expected ${6 + 25 / 25.4}`);
            const inside = await s.page.$eval('[data-testid=aura-inside]', (e) => e.innerText);
            if (!/Henchman/.test(inside) || !/Minion/.test(inside)) throw new Error(`expected both nearby models inside the 6" aura: ${inside}`);
            await hit('[data-testid=aura-minus]');
            await hit('[data-testid=aura-minus]');
            await hit('[data-testid=aura-minus]');
            await hit('[data-testid=aura-minus]');
            await hit('[data-testid=aura-minus]');
            await hit('[data-testid=aura-minus]');
            await hit('[data-testid=aura-minus]');
            await hit('[data-testid=aura-minus]'); // 6" down to 2"
            const r2 = await radius();
            if (Math.abs(r2 - (2 + 25 / 25.4)) > 0.01) throw new Error(`2" aura radius is ${r2}`);
            const inside2 = await s.page.$eval('[data-testid=aura-inside]', (e) => e.innerText);
            if (/Henchman|Minion/.test(inside2)) throw new Error(`nobody should be within 2": ${inside2}`);
            // Lock it where it is, then walk the master 10" away: the locked aura stays put.
            const circleOf = (n) =>
                s.page.evaluate((i) => {
                    const c = document.querySelectorAll('[data-testid=aura-a1] circle')[i];
                    return c ? { cx: Number(c.getAttribute('cx')), cy: Number(c.getAttribute('cy')) } : null;
                }, n);
            const toBoard = () => s.page.evaluate(() => document.querySelector('[data-testid=tabletop-board]').scrollIntoView());
            await hit('[data-testid=aura-lock]');
            await toBoard();
            await dragTo([18, 4], [18, 14]);
            const stayed = await circleOf(0);
            if (!stayed || Math.abs(stayed.cx - 18) > 0.05 || Math.abs(stayed.cy - 4) > 0.05)
                throw new Error(`locked aura moved: ${JSON.stringify(stayed)}`);
            const walked = await s.page.$eval('[data-testid=token-a1] circle', (c) => Number(c.getAttribute('cy')));
            if (Math.abs(walked - 14) > 0.3) throw new Error(`master did not walk to y=14 (cy ${walked})`);
            // A second and a third aura on the same model; then no more.
            await s.page.$eval('[data-testid=aura-add]', (b) => b.scrollIntoView({ block: 'center' }));
            await hit('[data-testid=aura-add]');
            await s.page.$eval('[data-testid=aura-add]', (b) => b.scrollIntoView({ block: 'center' }));
            await hit('[data-testid=aura-add]');
            await s.page.waitForFunction(() => document.querySelectorAll('[data-testid=aura-a1]').length === 3, { timeout: 5000 });
            if (!(await s.page.$eval('[data-testid=aura-add]', (b) => b.disabled))) throw new Error('a fourth aura should not be allowed');
            // The new auras follow the master (now at y=14); the first stays at y=4.
            const followed = await s.page.evaluate(() =>
                [...document.querySelectorAll('[data-testid=aura-a1]')].map((g) => Number(g.querySelector('circle').getAttribute('cy'))),
            );
            if (followed.filter((y) => Math.abs(y - 14) < 0.3).length !== 2 || followed.filter((y) => Math.abs(y - 4) < 0.3).length !== 1) {
                throw new Error(`expected one locked aura at y=4 and two following at y=14: ${followed}`);
            }
            await L.shot(s, `353-tabletop-auras-${label}`);
            // Unlock the first: it jumps back to the model.
            await s.page.$eval('[data-testid=aura-row-0] [data-testid=aura-lock]', (b) => b.scrollIntoView({ block: 'center' }));
            await hit('[data-testid=aura-row-0] [data-testid=aura-lock]');
            await L.sleep(200);
            const after = await s.page.evaluate(() =>
                [...document.querySelectorAll('[data-testid=aura-a1]')].map((g) => Number(g.querySelector('circle').getAttribute('cy'))),
            );
            if (after.some((y) => Math.abs(y - 14) > 0.3)) throw new Error(`unlocked aura did not follow the model: ${after}`);
            // Leave one aura for the removal check below.
            await hit('[data-testid=aura-row-2] [data-testid=aura-remove]');
            await hit('[data-testid=aura-row-1] [data-testid=aura-remove]');
            await L.shot(s, `352-tabletop-aura-${label}`);
            if ((await hscroll(s)) > 1) throw new Error(`page scrolls sideways by ${await hscroll(s)}px`);
            await hit('[data-testid=aura-remove]');
            if (await s.page.$('[data-testid=aura-a1]')) throw new Error('aura was not removed');
        });
        await L.step(`tabletop (${label}): markers from the list can be added, at their own base size`, s, async () => {
            await openSandbox();
            // The picker lives in a dialog, so the page itself carries no list.
            await s.page.waitForSelector('[data-testid=marker-open]');
            if (await s.page.$('[data-testid=marker-picker]')) throw new Error('the marker list should not sit on the page');
            const openPicker = async () => {
                await hit('[data-testid=marker-open]');
                await s.page.waitForSelector('[data-testid=marker-picker]');
            };
            await openPicker();
            const results = () => s.page.$$eval('[data-testid=marker-result]', (rows) => rows.map((r) => r.innerText.replace(/\s+/g, ' ').trim()));
            if ((await results()).length !== 6) throw new Error(`expected all 6 markers listed: ${await results()}`);
            // Search: every word must match, in any order.
            await s.page.type('[data-testid=marker-search]', 'marker blast');
            const found = await results();
            if (found.length !== 1 || !/Blast Marker/.test(found[0]) || !/50 mm/.test(found[0]))
                throw new Error(`searching "marker blast" gave: ${found}`);
            await hit('[data-testid=marker-add]');
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=marker-picker]'), { timeout: 5000 });
            await s.page.waitForFunction(() => document.querySelectorAll('[data-marker=true]').length === 1, { timeout: 5000 });
            // It is drawn to scale: a 50 mm base is 0.984" in radius.
            const r = await s.page.$eval('[data-marker=true]', (c) => Number(c.getAttribute('r')));
            if (Math.abs(r - 25 / 25.4) > 0.01) throw new Error(`a 50 mm marker has radius ${r}, expected ${25 / 25.4}`);
            // Several of the same marker are fine, and a 30 mm one is smaller.
            for (let i = 0; i < 2; i++) {
                await openPicker();
                await s.page.type('[data-testid=marker-search]', 'scheme');
                await hit('[data-testid=marker-add]');
                await s.page.waitForFunction(() => !document.querySelector('[data-testid=marker-picker]'), { timeout: 5000 });
                await s.page.waitForFunction((n) => document.querySelectorAll('[data-marker=true]').length === n, { timeout: 5000 }, i + 2);
            }
            const radii = await s.page.$$eval('[data-marker=true]', (cs) => cs.map((c) => Number(c.getAttribute('r'))).sort());
            if (Math.abs(radii[0] - 15 / 25.4) > 0.01) throw new Error(`a 30 mm marker has radius ${radii[0]}, expected ${15 / 25.4}`);
            // Each side can have five of each marker: the fifth Scheme is fine, then it cannot add a sixth while Blast still can.
            for (let i = 0; i < 3; i++) {
                await openPicker();
                await s.page.type('[data-testid=marker-search]', 'scheme');
                await hit('[data-testid=marker-add]');
                await s.page.waitForFunction(() => !document.querySelector('[data-testid=marker-picker]'), { timeout: 5000 });
                await s.page.waitForFunction((n) => document.querySelectorAll('[data-marker=true]').length === n, { timeout: 5000 }, i + 4);
            }
            await openPicker();
            await s.page.type('[data-testid=marker-search]', 'scheme');
            if (!(await s.page.$eval('[data-testid=marker-add]', (b) => b.disabled))) throw new Error('Add should be disabled at five of one marker');
            const used = await s.page.$eval('[data-testid=marker-used]', (e) => e.innerText.trim());
            if (used !== '5/5') throw new Error(`expected 5/5 used, saw ${used}`);
            // Another kind still has all five.
            await s.page.$eval('[data-testid=marker-search]', (i) => {
                i.value = '';
                i.dispatchEvent(new Event('input'));
            });
            await s.page.type('[data-testid=marker-search]', 'blast');
            if (await s.page.$eval('[data-testid=marker-add]', (b) => b.disabled)) throw new Error('a different marker should still be addable');
            await L.shot(s, `356-markers-${label}`);
            await s.page.keyboard.press('Escape');
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=marker-picker]'), { timeout: 5000 });
            if ((await hscroll(s)) > 1) throw new Error(`page scrolls sideways by ${await hscroll(s)}px`);
            // Select one, see its base size, pick it up.
            await toBoardTop();
            const ids = await s.page.$$eval('[data-marker=true]', (cs) =>
                cs.map((c) => c.parentElement.getAttribute('data-testid').replace('token-', '')),
            );
            const first = await s.page.$eval(`[data-testid=token-${ids[0]}] circle`, (c) => ({
                x: Number(c.getAttribute('cx')),
                y: Number(c.getAttribute('cy')),
            }));
            const at = await pixel(first.x, first.y);
            await s.page.mouse.click(at.x, at.y);
            await s.page.waitForSelector('[data-testid=selected-panel]');
            await s.page.waitForFunction(() => /mm base/.test(document.querySelector('[data-testid=selected-panel]').innerText), { timeout: 5000 });
            await hit('[data-testid=remove-token]');
            await s.page.waitForFunction(() => document.querySelectorAll('[data-marker=true]').length === 5, { timeout: 5000 });
            // Leave the table as the next step expects it.
            for (let i = 0; i < 5; i++) {
                await toBoardTop();
                const remaining = await s.page.$eval('[data-marker=true]', (c) => ({
                    x: Number(c.getAttribute('cx')),
                    y: Number(c.getAttribute('cy')),
                }));
                const p = await pixel(remaining.x, remaining.y);
                await s.page.mouse.click(p.x, p.y);
                await hit('[data-testid=remove-token]');
            }
            await s.page.waitForFunction(() => document.querySelectorAll('[data-marker=true]').length === 0, { timeout: 5000 });
        });
        await L.step(`tabletop (${label}): a character card can be searched for and added at its own size, Sz and Sp`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=character-open]');
            await s.page.$eval('[data-testid=character-open]', (b) => b.scrollIntoView({ block: 'center' }));
            await s.page.click('[data-testid=character-open]');
            await s.page.waitForSelector('[data-testid=character-search]');
            await s.page.type('[data-testid=character-search]', 'seedlady');
            await s.page.waitForFunction(() => document.querySelectorAll('[data-testid=character-result]').length === 2, { timeout: 5000 });
            await s.page.$eval('[data-testid=character-search]', (i) => {
                i.value = '';
                i.dispatchEvent(new Event('input'));
            });
            await s.page.type('[data-testid=character-search]', 'alpha seedlady');
            await s.page.waitForFunction(() => document.querySelectorAll('[data-testid=character-result]').length === 1, { timeout: 5000 });
            const stats = await s.page.$eval('[data-testid=character-stats]', (e) => e.innerText);
            if (!/50 mm/.test(stats) || !/Sz 3/.test(stats) || !/Sp 6/.test(stats)) throw new Error(`unexpected stats: ${stats}`);
            await s.page.click('[data-testid=character-add]');
            await s.page
                .waitForFunction(() => document.querySelectorAll('[data-testid^=token-]').length === 7, { timeout: 5000 })
                .catch(async () => {
                    throw new Error(`after adding there are ${(await s.page.$$('[data-testid^=token-]')).length} models`);
                });
            await L.expectText(s, /Seedlady Alpha added \(50 mm base, Sz 3, Sp 6, 9 health\)/);
            // The card's health comes with it, and the model panel tracks it: health, a token and activation, kept on the device.
            const lastCircle = async () => {
                await s.page.evaluate(() => document.querySelector('[data-testid=tabletop-board]').scrollIntoView());
                return s.page.evaluate(() => {
                    const cs = document.querySelectorAll('[data-testid^=token-] > circle');
                    const c = cs[cs.length - 1];
                    const r = c.getBoundingClientRect();
                    return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
                });
            };
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=character-picker]'), { timeout: 5000 }); // the dialog has finished closing
            const at = await lastCircle();
            await s.page.mouse.click(at.x, at.y);
            await s.page.waitForSelector('[data-testid=model-info]', { timeout: 4000 }).catch(async () => {
                const panel = await s.page.evaluate(() => document.querySelector('[data-testid=selected-panel]')?.innerText ?? 'no panel');
                throw new Error(`no model panel after clicking at ${Math.round(at.x)},${Math.round(at.y)}: ${panel.slice(0, 200)}`);
            });
            const hp = () => s.page.$eval('[data-testid=health-value]', (e) => e.innerText.trim());
            if ((await hp()) !== '9 / 9') throw new Error(`expected 9 / 9, saw ${await hp()}`);
            const press = async (id) => {
                await s.page.$eval(`[data-testid=${id}]`, (b) => b.scrollIntoView({ block: 'center' }));
                await s.page.click(`[data-testid=${id}]`);
            };
            await press('health-minus');
            await press('health-minus');
            await s.page.waitForFunction(() => document.querySelector('[data-testid=health-value]').innerText.trim() === '7 / 9', { timeout: 5000 });
            await s.page.$eval('[data-testid=token-add]', (b) => b.scrollIntoView({ block: 'center' }));
            const focus = await s.page.$$eval('[data-testid=token-add] option', (os) => os.find((o) => o.textContent === 'Focus')?.value);
            await s.page.select('[data-testid=token-add]', focus);
            await s.page.waitForFunction(() => document.querySelectorAll('[data-testid=model-token]').length === 1, { timeout: 5000 });
            await press('model-activated');
            await s.page.waitForFunction(() => document.querySelector('[data-testid=model-activated]').innerText.trim() === 'Activated', {
                timeout: 5000,
            });
            if (!(await s.page.$('[data-testid^=hp-]'))) throw new Error('the board should draw a health bar on the model');
            await L.shot(s, `359-model-info-${label}`);
            // drawn at its real base size: the new model has a 50 mm (0.984") radius
            const radii = await s.page.$$eval('[data-testid^=token-] > circle', (cs) => cs.map((c) => Number(c.getAttribute('r'))));
            if (Math.abs(radii[radii.length - 1] - 25 / 25.4) > 0.01) throw new Error(`the new model should have a 50 mm base: ${radii}`);
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=character-picker]'), { timeout: 5000 }); // the dialog has finished closing
            await s.page.$eval('[data-testid=reset-table]', (b) => b.scrollIntoView({ block: 'center' }));
            await s.page.click('[data-testid=reset-table]');
            // reset clears the models (nothing blank comes back) and the hint says how to start again
            await s.page
                .waitForFunction(() => document.querySelectorAll('[data-testid^=token-]').length === 0, { timeout: 5000 })
                .catch(async () => {
                    throw new Error(`reset left ${(await s.page.$$('[data-testid^=token-]')).length} models`);
                });
            await s.page.waitForSelector('[data-testid=empty-table]');
        });
        await L.step(`tabletop (${label}): the sandbox offers no blank models and starts empty`, s, async () => {
            await s.page.evaluate(() => localStorage.removeItem('tabletop-spike-v2'));
            await L.visit(s, '/tools/tabletop');
            await s.page.waitForSelector('[data-testid=empty-table]');
            if ((await s.page.$$('[data-testid^=token-]')).length !== 0) throw new Error('the sandbox should start with no models');
            if (await s.page.$('[data-testid=add-token]')) throw new Error('there should be no blank "Add model" button');
            await s.page.waitForSelector('[data-testid=character-open]');
            await L.expectText(s, /Add a character/);
        });
        await L.step(`tabletop (${label}): the terrain editor selects, edits, moves, adds, turns, undoes and clears pieces`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            const toBoard = () => s.page.evaluate(() => document.querySelector('[data-testid=tabletop-board]').scrollIntoView());
            const centreOn = (testid) => s.page.$eval(`[data-testid=${testid}]`, (b) => b.scrollIntoView({ block: 'center' }));
            const click = async (testid) => {
                await centreOn(testid);
                await s.page.click(`[data-testid=${testid}]`);
            };
            const stats = () => s.page.$eval('[data-testid=terrain-stats]', (e) => e.innerText);
            const levels = () => s.page.$$eval('[data-testid=terrain-level]', (e) => e.length);
            await click('mode-terrain');
            await L.expectText(s, /Add a piece/);
            if (!/5 pieces/.test(await stats())) throw new Error(`the demo table should have 5 pieces: ${await stats()}`);
            // Select the tower by tapping its roof.
            await toBoard();
            const roof = await pixel(10, 8);
            await s.page.mouse.click(roof.x, roof.y);
            await s.page.waitForSelector('[data-testid=terrain-piece]');
            if ((await levels()) !== 2) throw new Error(`the tower has 2 levels, the editor shows ${await levels()}`);
            // Raise the roof by half an inch.
            await click('terrain-ht-plus');
            const ht = await s.page.$eval('[data-testid=terrain-ht]', (i) => i.value);
            if (ht !== '3.5') throw new Error(`roof height is ${ht}, expected 3.5`);
            await L.expectText(s, /Ht 3\.5/);
            // Drag the whole tower 8" right and 10" down: both levels move together.
            await toBoard();
            const rectX = () =>
                s.page.evaluate(() => {
                    const g = [...document.querySelectorAll('[data-testid=tabletop-board] svg g')].find(
                        (x) => /Ht 3\.5/.test(x.textContent) && x.querySelector('rect'),
                    );
                    return Number(g.querySelector('rect').getAttribute('x'));
                });
            const before = await rectX();
            await dragTo([10, 8], [18, 18]);
            const after = await rectX();
            if (Math.abs(after - before - 8) > 0.6) throw new Error(`tower moved from x=${before} to x=${after}, expected about +8`);
            // Add a wall, turn it a quarter, check the sides swapped.
            await s.page.select('[data-testid=terrain-preset]', 'wall');
            await click('terrain-add');
            await s.page.waitForFunction(() => /6 pieces/.test(document.querySelector('[data-testid=terrain-stats]').innerText), { timeout: 5000 });
            const w0 = await s.page.$eval('[data-testid=terrain-w]', (i) => i.value);
            await click('terrain-rotate');
            const w1 = await s.page.$eval('[data-testid=terrain-w]', (i) => i.value);
            if (!(w0 === '8' && w1 === '1.5')) throw new Error(`turning the wall should swap 8 x 1.5 to 1.5 x 8, got ${w0} then ${w1}`);
            // Traits toggle, a level can be added, then undone.
            await click('trait-hazardous');
            if (!(await s.page.$eval('[data-testid=trait-hazardous]', (i) => i.checked))) throw new Error('trait did not toggle');
            await click('terrain-add-level');
            if ((await levels()) !== 2) throw new Error(`adding a level should give 2, got ${await levels()}`);
            await click('terrain-undo');
            if ((await levels()) !== 1) throw new Error(`undo should take the extra level away, got ${await levels()}`);
            await L.shot(s, `355-terrain-editor-${label}`);
            if ((await hscroll(s)) > 1) throw new Error(`page scrolls sideways by ${await hscroll(s)}px`);
            // Draw an outline: corners tapped on the board close into a polygon piece.
            const piecesBefore = Number(((await stats()).match(/(\d+) pieces?/) || [])[1]);
            const polygons = () => s.page.$$eval('[data-testid=tabletop-board] svg polygon', (p) => p.length);
            const polysBefore = await polygons();
            await click('terrain-draw');
            await s.page.waitForSelector('[data-testid=outline-help]');
            for (const [x, y] of [
                [31, 30],
                [35, 30],
                [34, 34],
                [31, 34.5],
            ]) {
                const at = await pixel(x, y);
                await s.page.mouse.click(at.x, at.y);
            }
            await s.page.waitForSelector('[data-testid=outline-preview]');
            await click('outline-finish');
            await s.page.waitForFunction(
                (n) => new RegExp(`${n + 1} pieces`).test(document.querySelector('[data-testid=terrain-stats]').innerText),
                { timeout: 5000 },
                piecesBefore,
            );
            if ((await polygons()) !== polysBefore + 1)
                throw new Error(`the drawn outline should add one polygon to the board (${polysBefore} -> ${await polygons()})`);
            await click('terrain-undo');
            await s.page.waitForFunction(
                (n) => new RegExp(`${n} pieces?`).test(document.querySelector('[data-testid=terrain-stats]').innerText),
                { timeout: 5000 },
                piecesBefore,
            );
            // Clear the table, then bring the demo table back.
            await click('terrain-clear');
            await s.page.waitForFunction(() => /0 pieces/.test(document.querySelector('[data-testid=terrain-stats]').innerText), { timeout: 5000 });
            if (/Covered: [1-9]/.test(await stats())) throw new Error(`an empty table has no coverage: ${await stats()}`);
            // A ready-made table fills it in one go and is undoable.
            await s.page.select('[data-testid=terrain-layout]', 'hills');
            await click('terrain-layout-load');
            await s.page.waitForFunction(() => /7 pieces/.test(document.querySelector('[data-testid=terrain-stats]').innerText), { timeout: 5000 });
            const covered = Number(((await stats()).match(/Covered: (\d+)%/) || [])[1]);
            if (covered < 45 || covered > 65) throw new Error(`a ready-made table should cover 45-65%: ${await stats()}`);
            await L.shot(s, `358-terrain-layout-${label}`);
            await click('terrain-undo');
            await s.page.waitForFunction(() => /0 pieces/.test(document.querySelector('[data-testid=terrain-stats]').innerText), { timeout: 5000 });
            await click('terrain-demo');
            await s.page.waitForFunction(() => /5 pieces/.test(document.querySelector('[data-testid=terrain-stats]').innerText), { timeout: 5000 });
            // It is all kept on this device: reload and the demo table is still there, still in terrain mode's reach.
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            await L.expectText(s, /Ht 5/);
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
