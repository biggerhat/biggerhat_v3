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
        const hitRaw = async (selector) => {
            // the Table section is collapsed until opened: open whatever the control sits in
            await s.page.$eval(selector, (b) => {
                const d = b.closest('details');
                if (d && !d.open) d.open = true;
                b.scrollIntoView({ block: 'center' });
            });
            await s.page.click(selector);
            if (selector.includes('reset-table')) {
                // Reset asks first
                await s.page.waitForFunction(
                    () => [...document.querySelectorAll('[role=dialog] button')].some((b) => /Reset table/.test(b.innerText)),
                    { timeout: 5000 },
                );
                await s.page.evaluate(() =>
                    [...document.querySelectorAll('[role=dialog] button')].find((b) => /Reset table/.test(b.innerText)).click(),
                );
                await s.page.waitForFunction(() => !document.querySelector('[role=dialog]'), { timeout: 5000 });
            }
        };
        const hit = async (selector) => {
            const back = await L.revealTable(s.page, selector);
            try {
                await hitRaw(selector);
            } finally {
                await back();
            }
        };
        const addItem = async (id) => {
            await s.page.waitForFunction(() => document.body.style.pointerEvents !== 'none', { timeout: 5000 });
            await hit('[data-testid=add-menu]');
            await s.page.waitForSelector(`[data-testid=${id}]`);
            await s.page.$eval(`[data-testid=${id}]`, (e) => e.click());
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
        const openSandbox = async (models = DEMO, terrain = null) => {
            await L.visit(s, '/tools/tabletop');
            await s.page.evaluate(
                ({ tokens, pieces }) => {
                    let saved = {};
                    try {
                        saved = JSON.parse(localStorage.getItem('tabletop-spike-v2')) || {};
                    } catch {
                        // start from nothing
                    }
                    localStorage.setItem('tabletop-spike-v2', JSON.stringify({ ...saved, tokens, ...(pieces ? { terrain: pieces } : {}) }));
                },
                { tokens: models, pieces: terrain },
            );
            await L.visit(s, '/tools/tabletop');
        };
        const dragTo = async (from, to) => {
            await toBoardTop();
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
            await s.page.$eval('[data-testid=step-toggle]', (b) => {
                const d = b.closest('details');
                if (d && !d.open) d.open = true;
                b.scrollIntoView({ block: 'center' });
            });
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
            // without Step, a second drag starts a fresh measure (a script click: on a phone the sticky tab bar can sit over the button after a scroll)
            await s.page.$eval('[data-testid=step-toggle]', (b) => b.click());
            await dragTo([18, 11], [18, 13]);
            await L.expectText(s, /moved 2(\.\d)?"/);
            if ((await s.page.$$('[data-testid=move-trail-leg]')).length) throw new Error('a plain drag should not keep earlier legs');
            await hit('[data-testid=reset-table]');
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
            // the ruler is a switch: pressing it again puts it away, with what it measured, and models move again
            await s.page.click('[data-testid=mode-ruler]');
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=ruler-readout]'), { timeout: 5000 });
            if ((await s.page.$eval('[data-testid=mode-ruler]', (b) => b.getAttribute('aria-pressed'))) !== 'false')
                throw new Error('the ruler button should be off');
            if ((await s.page.$eval('[data-testid=mode-move]', (b) => b.getAttribute('aria-selected'))) !== 'true')
                throw new Error('turning the ruler off should go back to Move');
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
        await L.step(
            `tabletop (${label}): a place is measured as a place, a Flight walk ignores terrain height, and a Walk is unchanged`,
            s,
            async () => {
                await openSandbox();
                await s.page.waitForSelector('[data-testid=tabletop-board] svg');
                const note = () => s.page.$eval('[data-testid=tabletop-note]', (e) => e.innerText);
                // a place: the minion is put down 4" away over the wall, ignoring it
                await hit('[data-testid=move-kind-place]');
                await s.page.waitForSelector('[data-testid=place-range]');
                await s.page.$eval('[data-testid=place-range]', (i) => {
                    i.value = '6';
                    i.dispatchEvent(new Event('input', { bubbles: true }));
                });
                await toBoardTop();
                await dragTo([12, 32], [12, 28]);
                await s.page.waitForSelector('[data-testid=place-reach]');
                let t = await note();
                if (!/placed .* within the 6" place/.test(t) || !/nothing is spent climbing or falling/.test(t))
                    throw new Error(`expected a place readout: ${t}`);
                if (/Walk/.test(t)) throw new Error(`a place is not measured against a Walk: ${t}`);
                // too far for the range
                await toBoardTop();
                await dragTo([12, 28], [12, 18]);
                // the note is cleared when a drag starts and set as it ends: wait for it rather than read it at once
                await s.page
                    .waitForFunction(() => /beyond the 6" place/.test(document.querySelector('[data-testid=tabletop-note]')?.innerText ?? ''), {
                        timeout: 4000,
                    })
                    .catch(() => {});
                t = await note();
                if (!/beyond the 6" place/.test(t)) throw new Error(`expected the place to be too far: ${t}`);
                // the whole base must be within range: the same short drop still passes, and the toggle is pressed
                await s.page.$eval('[data-testid=place-whole]', (b) => b.click());
                await s.page
                    .waitForFunction(() => document.querySelector('[data-testid=place-whole]')?.getAttribute('aria-pressed') === 'true', {
                        timeout: 3000,
                    })
                    .catch(() => {
                        throw new Error('whole base did not toggle');
                    });
                // a Flight walk: height is ignored, the Walk still counts
                await hit('[data-testid=move-kind-fly]');
                await toBoardTop();
                await dragTo([12, 18], [12, 22]);
                t = await note();
                if (!/ignoring terrain height/.test(t) || !/of a Walk/.test(t)) throw new Error(`expected a Flight readout against a Walk: ${t}`);
                if (await s.page.$('[data-testid=place-reach]')) throw new Error('a Flight walk has no place ring');
                // and a plain Walk reads as before
                await hit('[data-testid=move-kind-walk]');
                await toBoardTop();
                await dragTo([12, 22], [12, 26]);
                t = await note();
                if (!/moved .*of a Walk/.test(t) || /ignoring terrain height/.test(t)) throw new Error(`expected a plain Walk readout: ${t}`);
                await L.shot(s, `364-place-walk-${label}`);
                await hit('[data-testid=reset-table]');
            },
        );
        await L.step(
            `tabletop (${label}): a model can go under a bridge's archway, climb onto the deck, and a tall one does not fit`,
            s,
            async () => {
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
                await s.page.waitForFunction(() => document.querySelector('[data-testid=token-b3] circle')?.getAttribute('data-under') === null, {
                    timeout: 5000,
                });
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
            },
        );
        await L.step(
            `tabletop (${label}): a building can be made enterable, shown cut away, and a model walks in by the door but not through a wall`,
            s,
            async () => {
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
                if ((await s.page.$eval('[data-testid=token-b3] circle', (c) => c.getAttribute('data-under'))) !== 'true')
                    throw new Error('a model in the building should be inside it');
                await L.expectText(s, /inside Building/);
                // and out through the left wall, which has no opening
                await toBoardTop();
                await dragTo([door.x, door.y + 1.5], [door.x - 6, door.y + 1.5]);
                const t2 = await s.page.$eval('[data-testid=tabletop-note]', (e) => e.innerText);
                if (!/through the wall of Building/.test(t2)) throw new Error(`expected a wall warning: ${t2}`);
                await hit('[data-testid=reset-table]');
            },
        );
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
        await L.step(
            `tabletop (${label}): a piece can be reshaped with handles, turned to any angle, and an outline gains and loses corners`,
            s,
            async () => {
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
                if ((await val('terrain-angle')) !== 15)
                    throw new Error(`turning 15 degrees should set the angle to 15, saw ${await val('terrain-angle')}`);
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
                await s.page.waitForFunction(
                    (n) => document.querySelectorAll('[data-testid^="handle-vertex-"]').length === n,
                    { timeout: 5000 },
                    before,
                );
                // any piece, an outline included, can be given a background
                const textured = (kind) =>
                    s.page.waitForFunction(
                        (k, want) =>
                            [...document.querySelectorAll('[data-testid=terrain-texture]')].some((e) => e.getAttribute('data-kind') === k) === want,
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
            },
        );
        await L.step(`tabletop (${label}): a table can be saved from the board and loaded back from the Load table dialog`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=table-save]');
            await hit('[data-testid=table-save]');
            await s.page.waitForSelector('[data-testid=save-dialog]');
            await s.page.type('[data-testid=save-table-name]', 'Harness table');
            await s.page.$eval('[data-testid=save-table-confirm]', (b) => b.click());
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=save-dialog]') && document.body.style.pointerEvents !== 'none', {
                timeout: 8000,
            });
            // clear the table, then bring it back: the dialog lists the ready-made tables and mine
            await hit('[data-testid=mode-terrain]');
            await hit('[data-testid=terrain-clear]');
            await s.page.waitForFunction(() => /0 pieces/.test(document.querySelector('[data-testid=terrain-stats]').innerText), { timeout: 5000 });
            await hit('[data-testid=table-load]');
            await s.page.waitForSelector('[data-testid=load-dialog]');
            const defaults = await s.page.$$eval('[data-testid=load-default]', (e) => e.length);
            if (defaults < 4) throw new Error(`expected the ready-made tables in the dialog, saw ${defaults}`);
            await s.page.waitForSelector('[data-testid=load-mine][data-name="Harness table"]');
            await L.shot(s, `362-load-dialog-${label}`);
            await s.page.$eval('[data-testid=load-mine][data-name="Harness table"]', (b) => b.click());
            await s.page.waitForFunction(() => /5 pieces/.test(document.querySelector('[data-testid=terrain-stats]').innerText), { timeout: 5000 });
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=load-dialog]') && document.body.style.pointerEvents !== 'none', {
                timeout: 5000,
            });
            await hit('[data-testid=reset-table]');
        });
        await L.step(
            `tabletop (${label}): the Tabletop page lists my tables with thumbnails, lists public ones, and a table can be edited and saved back`,
            s,
            async () => {
                await L.visit(s, '/tools/tabletop/tables');
                await s.page.waitForSelector('[data-testid=my-tables]');
                await s.page.waitForSelector('[data-testid=open-sandbox]');
                await s.page.waitForSelector('[data-testid=my-table] [data-testid=table-thumbnail]');
                const named = await s.page.$$eval('[data-testid=my-table-name]', (els) => els.map((e) => e.innerText));
                if (!named.includes('Harness table')) throw new Error(`expected the saved table in the list: ${named}`);
                if (label === 'desktop' && !(await s.page.$('a[href$="/tools/tabletop/tables"]')))
                    throw new Error('the Tools menu should link to the Tabletop page');
                // make it public: it appears in the gallery
                await s.page.select('[data-testid=table-visibility]', 'public');
                await s.page.waitForFunction(() => document.querySelectorAll('[data-testid=gallery-table]').length >= 1, { timeout: 8000 });
                await s.page.waitForFunction(() => /Public/.test(document.querySelector('[data-testid=my-table-visibility]').innerText), {
                    timeout: 8000,
                });
                await L.shot(s, `367-tabletop-tables-${label}`);
                // the gallery search narrows it
                await s.page.type('[data-testid=gallery-search]', 'zzzz-nothing');
                await s.page.waitForFunction(() => document.querySelectorAll('[data-testid=gallery-table]').length === 0, { timeout: 8000 });
                // open it in the sandbox to look: a banner says whose it is, and Back returns to the sandbox
                await hit('[data-testid=table-open]');
                await s.page.waitForSelector('[data-testid=incoming-layout]');
                await L.expectText(s, /Harness table/);
                await L.shot(s, `362-shared-layout-${label}`);
                await L.visit(s, '/tools/tabletop/tables');
                await s.page.waitForSelector('[data-testid=my-table]');
                // edit: the table opens in the sandbox, saving is off until something changes
                await hit('[data-testid=table-edit]');
                await s.page.waitForSelector('[data-testid=editing-note]');
                if (!(await s.page.$('[data-testid=fate-section]'))) throw new Error('the practice Fate deck should be on the edit page too');
                if (!(await s.page.$eval('[data-testid=editing-save]', (b) => b.disabled)))
                    throw new Error('nothing changed yet, so there is nothing to save');
                await hit('[data-testid=mode-terrain]');
                await s.page.select('[data-testid=terrain-preset]', 'crates');
                await hit('[data-testid=terrain-add]');
                await s.page.waitForFunction(() => !document.querySelector('[data-testid=editing-save]').disabled, { timeout: 5000 });
                await hit('[data-testid=editing-save]');
                await s.page.waitForFunction(() => document.querySelector('[data-testid=editing-save]').disabled, { timeout: 8000 });
                // back on the page the table now has one more piece
                await L.visit(s, '/tools/tabletop/tables');
                await s.page.waitForSelector('[data-testid=my-table]');
                await L.expectText(s, /6 pieces/);
                await openSandbox(); // leave the sandbox open for the next step
            },
        );
        await L.step(`tabletop (${label}): a new table starts empty, apart from the sandbox, and is saved with a name`, s, async () => {
            await openSandbox(); // the sandbox has its demo models and terrain on this device
            await L.visit(s, '/tools/tabletop/tables');
            await s.page.waitForSelector('[data-testid=new-table-link]');
            const before = (await s.page.$$('[data-testid=my-table]')).length;
            await hit('[data-testid=new-table-link]');
            await s.page.waitForSelector('[data-testid=new-table]');
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            await hit('[data-testid=mode-terrain]');
            await s.page.waitForFunction(() => /0 pieces/.test(document.querySelector('[data-testid=terrain-stats]').innerText), { timeout: 5000 });
            if ((await s.page.$$('[data-testid^=token-]')).length !== 0) throw new Error('a new table has no models');
            await toBoardTop();
            await L.sleep(300);
            await L.shot(s, `368-new-table-blank-${label}`);
            await s.page.evaluate(() => window.scrollTo(0, 0));
            await L.shot(s, `368-new-table-top-${label}`);
            if (!(await s.page.$eval('[data-testid=new-table-save]', (b) => b.disabled))) throw new Error('an empty table cannot be saved');
            // the page says what it is for
            const sub = await s.page.$eval('[data-testid=page-subtitle]', (e) => e.innerText);
            if (!/name it and save it/.test(sub)) throw new Error(`the heading should describe a new table: ${sub}`);
            // ready-made tables come from Load table (no separate grid above the board)
            if (!(await s.page.$('[data-testid=fate-section]'))) throw new Error('the practice Fate deck should be on the new-table page too');
            if (await s.page.$('[data-testid=starting-points]')) throw new Error('the starting-points grid should be gone');
            await hit('[data-testid=table-load]');
            await s.page.waitForSelector('[data-testid=load-default][data-key=hills]', { timeout: 5000 });
            await s.page.$eval('[data-testid=load-default][data-key=hills]', (b) => b.click());
            await s.page.waitForFunction(() => /7 pieces/.test(document.querySelector('[data-testid=terrain-stats]').innerText), { timeout: 5000 });
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=load-dialog]') && document.body.style.pointerEvents !== 'none', {
                timeout: 5000,
            });
            // leaving with work on the table asks first; with none it just goes
            await hit('[data-testid=new-table-cancel]');
            await s.page.waitForFunction(() => [...document.querySelectorAll('[role=dialog] button')].some((b) => /Leave/.test(b.innerText)), {
                timeout: 5000,
            });
            await s.page.evaluate(() => [...document.querySelectorAll('[role=dialog] button')].find((b) => /Cancel/.test(b.innerText)).click());
            await s.page.waitForFunction(() => !document.querySelector('[role=dialog]') && document.body.style.pointerEvents !== 'none', {
                timeout: 5000,
            });
            await s.page.select('[data-testid=terrain-preset]', 'wall');
            await hit('[data-testid=terrain-add]');
            await s.page.type('[data-testid=new-table-name]', 'Fresh table');
            await s.page.select('[data-testid=new-table-visibility]', 'public');
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=new-table-save]').disabled, { timeout: 5000 });
            await hit('[data-testid=new-table-save]');
            await s.page.waitForSelector('[data-testid=my-tables]', { timeout: 10000 });
            await s.page.waitForFunction((n) => document.querySelectorAll('[data-testid=my-table]').length > n, { timeout: 8000 }, before);
            const names = await s.page.$$eval('[data-testid=my-table-name]', (els) => els.map((e) => e.innerText));
            if (!names.includes('Fresh table')) throw new Error(`the new table should be listed: ${names}`);
            const badge = await s.page.$$eval('[data-testid=my-table]', (cards) => {
                const card = cards.find((c) => /Fresh table/.test(c.innerText));
                return card ? card.querySelector('[data-testid=my-table-visibility]').innerText : '';
            });
            if (!/Public/.test(badge)) throw new Error(`the table was made public, the card says: ${badge}`);
            // the sandbox on this device was not touched
            await openSandbox();
            await s.page.waitForSelector('[data-testid=token-a1]');
        });
        await L.step(
            `tabletop (${label}): table lines mark the centre axes and each quarter's middle, whatever deployment is chosen`,
            s,
            async () => {
                await openSandbox();
                await s.page.waitForSelector('[data-testid=table-lines-toggle]');
                if (await s.page.$('[data-testid=table-lines]')) throw new Error('the table lines start hidden');
                await hit('[data-testid=table-lines-toggle]');
                await s.page.waitForSelector('[data-testid=table-lines]');
                const centres = await s.page.$$eval('[data-testid=quarter-centre]', (els) => els.map((e) => `${e.dataset.x},${e.dataset.y}`).sort());
                if (centres.join('|') !== '27,27|27,9|9,27|9,9') throw new Error(`expected the four quarter centres, saw ${centres}`);
                await s.page.waitForSelector('[data-testid=table-axes]');
                // with a deployment chosen the deployment's own centerline is still there, with no big label on it
                await s.page.select('[data-testid=deployment-select]', 'standard');
                await s.page.waitForSelector('[data-testid=centerline]');
                const backToModels = await L.revealTable(s.page, '[data-testid=table-summary]');
                const summary = await s.page.$eval('[data-testid=table-summary]', (e) => e.innerText);
                await backToModels();
                if (!/Standard/.test(summary) || !/lines on/.test(summary)) throw new Error(`the Table section should say what is set: ${summary}`);
                if ((await s.page.$eval('body', (b) => b.innerText)).includes('CENTERLINE')) throw new Error('the centerline needs no label');
                await toBoardTop();
                await L.shot(s, `369-table-lines-${label}`);
                // kept on this device
                await openSandbox();
                await s.page.waitForSelector('[data-testid=table-lines]');
                await hit('[data-testid=table-lines-toggle]');
                await s.page.waitForFunction(() => !document.querySelector('[data-testid=table-lines]'), { timeout: 5000 });
                await s.page.select('[data-testid=deployment-select]', 'none');
            },
        );
        await L.step(`tabletop (${label}): the side column, photographed in each state for design review`, s, async () => {
            const SHOTS = process.env.SHOTS;
            const column = async (name) => {
                const el = await s.page.$('[data-testid=side-column]');
                await el.screenshot({ path: `${SHOTS}/${name}-${label}.png` });
            };
            await openSandbox();
            await s.page.waitForSelector('[data-testid=side-column]');
            await column('col-1-move-nothing-selected');
            await toBoardTop();
            const m = await pixel(18, 4);
            await s.page.mouse.click(m.x, m.y); // the first master
            await s.page.waitForSelector('[data-testid=model-guide]');
            await column('col-2-move-model-selected');
            await hit('[data-testid=model-guide]');
            await s.page.waitForSelector('[data-testid=side-readout]');
            await column('col-3-move-guidelines-on');
            await hit('[data-testid=model-guide]');
            await hit('[data-testid=mode-terrain]');
            await toBoardTop();
            const roof = await pixel(10, 8);
            await s.page.mouse.click(roof.x, roof.y); // the tower
            await s.page.waitForSelector('[data-testid=terrain-piece]');
            await column('col-4-terrain-piece-selected');
            await hit('[data-testid=terrain-enterable]');
            await s.page.waitForSelector('[data-testid=terrain-floor]');
            await column('col-5-terrain-enterable');
            await hit('[data-testid=mode-move]');
            await hit('[data-testid=reset-table]');
        });
        await L.step(
            `tabletop (${label}): a selected model's panel is one tap away on a phone, and the chip never shows on a desktop`,
            s,
            async () => {
                await openSandbox();
                await s.page.waitForSelector('[data-testid=tabletop-board] svg[role=application]');
                await toBoardTop();
                const m = await pixel(18, 4);
                await s.page.mouse.click(m.x, m.y); // the first master
                await s.page.waitForSelector('[data-testid=selected-panel] [data-testid=model-guide]');
                await L.sleep(500);
                const chip = await s.page.$('[data-testid=selection-chip]');
                if (label === 'desktop') {
                    if (chip) throw new Error('the selection chip is for phones only');
                } else {
                    if (!chip) {
                        // with the side column grouped, the panel can already be on screen: then there is nothing to go to
                        const inViewAlready = await s.page.$eval('[data-testid=selected-panel]', (e) => {
                            const r = e.getBoundingClientRect();
                            return r.top < window.innerHeight && r.bottom > 0;
                        });
                        if (!inViewAlready) throw new Error('the panel is off screen on a phone, so a chip should offer to go to it');
                        return;
                    }
                    const text = await s.page.$eval('[data-testid=selection-chip]', (e) => e.innerText);
                    if (!/Master/.test(text)) throw new Error(`the chip should name the selection: ${text}`);
                    await s.page.click('[data-testid=selection-chip]');
                    await s.page.waitForFunction(() => !document.querySelector('[data-testid=selection-chip]'), { timeout: 5000 });
                    const inView = await s.page.$eval('[data-testid=selected-panel]', (e) => {
                        const r = e.getBoundingClientRect();
                        return r.top < window.innerHeight && r.bottom > 0;
                    });
                    if (!inView) throw new Error('the chip should bring the panel into view');
                    // the tabs stay pinned at the top of the screen however far down the column you are
                    await s.page.evaluate(() => window.scrollBy(0, 4000));
                    await L.sleep(300);
                    const top = await s.page.$eval('[data-testid=mode-tabs]', (e) => e.getBoundingClientRect().top);
                    if (top < 0 || top > 40) throw new Error(`the Models and Terrain tabs should stay at the top of a phone screen, top is ${top}`);
                }
                await hit('[data-testid=reset-table]');
            },
        );
        await L.step(`tabletop (${label}): a Walk across a river costs double, and one through deep water is called out`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg[role=application]');
            await hit('[data-testid=mode-terrain]');
            await s.page.select('[data-testid=terrain-preset]', 'river');
            await hit('[data-testid=terrain-add]');
            await hit('[data-testid=mode-move]');
            const note = () => s.page.$eval('[data-testid=tabletop-note]', (e) => e.innerText);
            // the minion walks straight up the table across the 3" river
            await dragTo([12, 32], [12, 10]);
            let t = await note();
            if (!/in severe terrain, which counts double/.test(t)) throw new Error(`expected the river to cost extra: ${t}`);
            const label1 = await s.page.$eval('[data-testid=move-trail-label]', (e) => e.textContent.trim());
            const total = Number((label1.match(/([\d.]+)"/) || [])[1]);
            if (!(total > 22 + 3)) throw new Error(`22" of walking plus about 4" extra in the river should read well over 25": ${label1}`);
            // a plain walk with no river in the way reads as before
            await dragTo([12, 10], [12, 6]);
            t = await note();
            if (/severe/.test(t)) throw new Error(`nothing severe was crossed: ${t}`);
            // deep water is impassable: a walk through it is called out
            await hit('[data-testid=mode-terrain]');
            await s.page.select('[data-testid=terrain-preset]', 'deepwater');
            await hit('[data-testid=terrain-add]');
            await hit('[data-testid=mode-move]');
            await dragTo([12, 6], [22, 22]);
            t = await note();
            if (!/impassable terrain \(Deep water\)/.test(t)) throw new Error(`expected the deep water to be called out: ${t}`);
            // a Flight walk ignores all of it
            await hit('[data-testid=move-kind-fly]');
            await dragTo([22, 22], [12, 6]);
            t = await note();
            if (/severe|impassable/.test(t)) throw new Error(`a Flight walk ignores terrain: ${t}`);
            await hit('[data-testid=reset-table]');
        });
        await L.step(`tabletop (${label}): a model left over another base can be moved to the nearest legal spot`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg[role=application]');
            const problem = (id) => s.page.$eval(`[data-testid=token-${id}] circle`, (c) => c.getAttribute('data-problem'));
            // the minion is dropped right on the henchman's base
            await dragTo([12, 32], [12.2, 5.2]);
            await s.page.waitForSelector('[data-testid=model-fix-spot]');
            if ((await problem('b3')) !== 'true') throw new Error('the overlap should be flagged');
            await hit('[data-testid=model-fix-spot]');
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=model-fix-spot]'), { timeout: 5000 });
            if (await problem('b3')) throw new Error('the model should no longer be flagged once moved to a legal spot');
            const t = await s.page.$eval('[data-testid=tabletop-note]', (e) => e.innerText);
            if (!/to the nearest legal spot/.test(t)) throw new Error(`the note should say what happened: ${t}`);
            await hit('[data-testid=reset-table]');
        });
        await L.step(`tabletop (${label}): a selected model says which quarter, edge and half of the table it is in`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg[role=application]');
            await toBoardTop();
            const m = await pixel(18, 4); // the first master, straddling the middle line at the top
            await s.page.mouse.click(m.x, m.y);
            await s.page.waitForSelector('[data-testid=whereabouts]');
            const where = () => s.page.$eval('[data-testid=whereabouts]', (e) => e.textContent.trim());
            let t = await where();
            if (!/top-left and top-right quarters/i.test(t) || !/from the top edge/.test(t)) throw new Error(`expected quarters and an edge: ${t}`);
            if (/half|deployment zone/.test(t)) throw new Error(`no deployment is chosen, so no half: ${t}`);
            await s.page.select('[data-testid=deployment-select]', 'standard');
            await s.page.waitForFunction(() => /Blue's half/.test(document.querySelector('[data-testid=whereabouts]').textContent), {
                timeout: 5000,
            });
            t = await where();
            if (!/in Blue's deployment zone/.test(t)) throw new Error(`the master starts in its own zone: ${t}`);
            await s.page.select('[data-testid=deployment-select]', 'none');
            await hit('[data-testid=reset-table]');
        });
        await L.step(`tabletop (${label}): a model that ignores severe terrain is not slowed by a river, and it is kept`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg[role=application]');
            await hit('[data-testid=mode-terrain]');
            await s.page.select('[data-testid=terrain-preset]', 'river');
            await hit('[data-testid=terrain-add]');
            await hit('[data-testid=mode-move]');
            const note = () => s.page.$eval('[data-testid=tabletop-note]', (e) => e.innerText);
            // select the minion, switch on "severe" in its panel
            await dragTo([12, 32], [12, 31.5]);
            await s.page.waitForSelector('[data-testid=ignore-severe]');
            await s.page.$eval('[data-testid=ignore-severe]', (i) => i.click());
            await s.page.waitForFunction(() => document.querySelector('[data-testid=ignores-section] summary').innerText.includes('severe'), {
                timeout: 5000,
            });
            await dragTo([12, 31.5], [12, 10]);
            const t = await note();
            if (/severe terrain/.test(t)) throw new Error(`a model unaffected by severe terrain is not slowed by it: ${t}`);
            // and without the switch it is
            await s.page.$eval('[data-testid=ignore-severe]', (i) => i.click());
            await dragTo([12, 10], [12, 31]);
            const u = await note();
            if (!/severe terrain, which counts double/.test(u)) throw new Error(`without the switch the river costs double: ${u}`);
            await hit('[data-testid=reset-table]');
        });
        await L.step(`tabletop (${label}): a jump from a Ht 3 wall to a Ht 2 wall is legal, and one back up is not`, s, async () => {
            await L.visit(s, '/tools/tabletop');
            const rect = (x, y, w, h) => ({ kind: 'rect', x, y, w, h });
            await s.page.evaluate(
                (rects) => {
                    const piece = (id, name, top, shape) => ({
                        id,
                        name,
                        traits: ['blocking', 'climbable'],
                        surfaces: [{ id: `${id}-1`, top, shape }],
                    });
                    localStorage.setItem(
                        'tabletop-spike-v2',
                        JSON.stringify({
                            tokens: [{ id: 'j1', sp: 6, sz: 2, name: 'Jumper', side: 'a', baseMm: 30, x: 7, y: 12 }],
                            terrain: [piece('wa', 'Ht 3 wall', 3, rects.a), piece('wb', 'Ht 2 wall', 2, rects.b)],
                        }),
                    );
                },
                { a: rect(2, 10, 6, 4), b: rect(11, 10, 6, 4) },
            );
            await L.visit(s, '/tools/tabletop');
            await s.page.waitForSelector('[data-testid=token-j1]');
            await hit('[data-testid=move-kind-jump]');
            const note = () => s.page.$eval('[data-testid=tabletop-note]', (e) => e.innerText);
            await dragTo([7, 12], [12, 12]); // 5 inches across the gap, one inch lower
            let t = await note();
            if (!/A legal jump, landing at elevation 2/.test(t)) throw new Error(`expected a legal jump: ${t}`);
            await dragTo([12, 12], [7, 12]); // and back up: higher than it began
            t = await note();
            if (!/not a legal jump: a jump cannot end higher than it began/.test(t)) throw new Error(`jumping up is not a jump: ${t}`);
            await hit('[data-testid=reset-table]');
        });
        await L.step(`tabletop (${label}): a model may move through a Strategy marker but not end a move on it`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=add-menu]');
            await addItem('marker-open');
            await s.page.waitForSelector('[data-testid=marker-picker]');
            await s.page.type('[data-testid=marker-search]', 'strategy');
            await hit('[data-testid=marker-add]');
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=marker-picker]'), { timeout: 5000 });
            await s.page.waitForFunction(() => document.querySelectorAll('[data-marker=true]').length === 1, { timeout: 5000 });
            const marker = await s.page.$eval('[data-marker=true]', (c) => ({ x: Number(c.getAttribute('cx')), y: Number(c.getAttribute('cy')) }));
            // the marker says what it is
            await toBoardTop();
            const m = await pixel(marker.x, marker.y);
            await s.page.mouse.click(m.x, m.y);
            await s.page.waitForSelector('[data-testid=strategy-note]');
            // a model dropped on it is flagged, and can be moved off
            await dragTo([12, 32], [marker.x + 0.2, marker.y + 0.2]);
            await s.page.waitForSelector('[data-testid=model-fix-spot]');
            const t = await s.page.$eval('[data-testid=placement-warning]', (e) => e.innerText);
            if (!/Strategy marker but not end a move/.test(t)) throw new Error(`expected the Strategy marker rule: ${t}`);
            await hit('[data-testid=model-fix-spot]');
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=model-fix-spot]'), { timeout: 5000 });
            await hit('[data-testid=reset-table]');
        });
        await L.step(`tabletop (${label}): the table check measures a ready-made table against the Gaining Grounds guidelines`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg[role=application]');
            await hit('[data-testid=mode-terrain]');
            await hit('[data-testid=table-load]');
            await s.page.waitForSelector('[data-testid=load-dialog]');
            await s.page.$eval('[data-testid=load-default][data-key=hills]', (b) => b.click());
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=load-dialog]') && document.body.style.pointerEvents !== 'none', {
                timeout: 5000,
            });
            await s.page.$eval('[data-testid=table-check]', (d) => (d.open = true));
            await s.page.waitForFunction(() => document.querySelectorAll('[data-testid=table-check-item]').length >= 4, { timeout: 8000 });
            const labels = await s.page.$$eval('[data-testid=table-check-item]', (els) => els.map((e) => e.innerText.replace(/\s+/g, ' ')));
            if (!labels.some((l) => /Sight-blocking terrain/.test(l))) throw new Error(`expected the sight terrain check: ${labels.join(' | ')}`);
            if (labels.some((l) => /Open sight lines between the zones/.test(l))) throw new Error('the lanes check needs a deployment');
            // with a deployment chosen the lanes and the zones are measured too
            await s.page.select('[data-testid=deployment-select]', 'standard');
            await s.page.waitForFunction(
                () =>
                    [...document.querySelectorAll('[data-testid=table-check-item]')].some((e) =>
                        /Open sight lines between the zones/.test(e.innerText),
                    ),
                { timeout: 8000 },
            );
            const unreachable = await s.page.$$eval('[data-testid=table-check-item]', (els) =>
                els.map((e) => e.innerText).find((t) => /Unreachable areas/.test(t)),
            );
            if (!/zone A/.test(unreachable || '')) throw new Error(`the unreachable check should measure each zone: ${unreachable}`);
            await s.page.select('[data-testid=deployment-select]', 'none');
            await hit('[data-testid=reset-table]');
        });
        await L.step(`tabletop (${label}): an aura can count as hazardous terrain around a model`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg[role=application]');
            await toBoardTop();
            const m = await pixel(18, 4); // the first master
            await s.page.mouse.click(m.x, m.y);
            await s.page.waitForSelector('[data-testid=aura-add]');
            await hit('[data-testid=aura-add]');
            await s.page.waitForSelector('[data-testid=aura-trait-hazardous]');
            await s.page.$eval('[data-testid=aura-trait-hazardous]', (i) => i.click());
            await s.page.waitForFunction(() => document.querySelector('[data-testid=aura-trait-hazardous]').checked, { timeout: 5000 });
            // a model walks into the aura: it is in hazardous terrain
            await dragTo([12, 32], [18, 8]);
            await s.page.waitForSelector('[data-testid=placement-warning]');
            const w = await s.page.$eval('[data-testid=placement-warning]', (e) => e.innerText);
            if (!/hazardous terrain \(Master's aura\)/.test(w)) throw new Error(`the aura should count as hazardous terrain: ${w}`);
            await hit('[data-testid=reset-table]');
        });
        await L.step(`tabletop (${label}): special terrain carries a rules card and the table check counts its limit`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg[role=application]');
            await hit('[data-testid=mode-terrain]');
            await s.page.waitForSelector('[data-testid=terrain-add]');
            await hit('[data-testid=terrain-add]');
            await s.page.waitForSelector('[data-testid=terrain-rules-text]');
            await s.page.$eval('[data-testid=terrain-rules]', (d) => (d.open = true));
            await s.page.type('[data-testid=terrain-rules-text]', 'Models within 2 inches suffer 1 damage.');
            await s.page.$eval('[data-testid=terrain-rules-text]', (t) => t.dispatchEvent(new Event('change', { bubbles: true })));
            await s.page.waitForFunction(
                () => /special terrain/.test(document.querySelector('[data-testid=terrain-rules] summary')?.innerText || ''),
                { timeout: 5000 },
            );
            await s.page.select('[data-testid=terrain-limit]', '1');
            await s.page.waitForFunction(() => /unique/.test(document.querySelector('[data-testid=terrain-rules] summary')?.innerText || ''), {
                timeout: 5000,
            });
            await hit('[data-testid=terrain-add]');
            await s.page.waitForSelector('[data-testid=terrain-piece]');
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
            await s.page.$eval('[data-testid=aura-add]', (b) => {
                const d = b.closest('details');
                if (d && !d.open) d.open = true;
                b.scrollIntoView({ block: 'center' });
            });
            await hit('[data-testid=aura-add]');
            await s.page.$eval('[data-testid=aura-add]', (b) => {
                const d = b.closest('details');
                if (d && !d.open) d.open = true;
                b.scrollIntoView({ block: 'center' });
            });
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
            await s.page.$eval('[data-testid=aura-row-0] [data-testid=aura-lock]', (b) => {
                const d = b.closest('details');
                if (d && !d.open) d.open = true;
                b.scrollIntoView({ block: 'center' });
            });
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
            await s.page.waitForSelector('[data-testid=add-menu]');
            if (await s.page.$('[data-testid=marker-picker]')) throw new Error('the marker list should not sit on the page');
            const openPicker = async () => {
                await addItem('marker-open');
                await s.page.waitForSelector('[data-testid=marker-picker]');
            };
            await openPicker();
            const results = () => s.page.$$eval('[data-testid=marker-result]', (rows) => rows.map((r) => r.innerText.replace(/\s+/g, ' ').trim()));
            if ((await results()).length !== 7) throw new Error(`expected all 7 markers listed: ${await results()}`);
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
            await s.page.waitForSelector('[data-testid=add-menu]');
            await s.page.$eval('[data-testid=add-menu]', (b) => {
                const d = b.closest('details');
                if (d && !d.open) d.open = true;
                b.scrollIntoView({ block: 'center' });
            });
            await addItem('character-open');
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
            // the crew row (the one the play screen uses) carries health and activation; the selected panel rolls down under it
            const row = '[data-testid=crew-list] [data-testid^=crew-]:has([data-testid=selected-panel])';
            await s.page.waitForSelector(row, { timeout: 5000 }).catch(() => {
                throw new Error('the selected model should open its options under its crew row');
            });
            const hp = () =>
                s.page.$eval(row, (r) =>
                    r.innerText
                        .match(/(\d+)\/(\d+)/)
                        ?.slice(1, 3)
                        .join(' / '),
                );
            if ((await hp()) !== '9 / 9') throw new Error(`expected 9 / 9, saw ${await hp()}`);
            const rowPress = (text, attr = 'aria-label') =>
                s.page.$eval(
                    row,
                    (r, t, a) => {
                        const b = [...r.querySelectorAll('button')].find((x) => x.offsetParent && (x.getAttribute(a) ?? '').startsWith(t));
                        if (!b) throw new Error(`no visible ${t} button in the row`);
                        b.scrollIntoView({ block: 'center' });
                        b.click();
                    },
                    text,
                    attr,
                );
            await rowPress('Decrease health');
            await rowPress('Decrease health');
            await s.page.waitForFunction((sel) => document.querySelector(sel).innerText.includes('7/9'), { timeout: 5000 }, row);
            // tokens: the row's Tokens button opens the tracker's token dialog
            await rowPress('Tokens', 'title');
            await s.page.waitForSelector('[data-testid=token-option][data-name=Focus]', { timeout: 5000 });
            await s.page.click('[data-testid=token-option][data-name=Focus]');
            await s.page.waitForSelector('[data-testid^=badges-]', { timeout: 5000 });
            await s.page.keyboard.press('Escape');
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=token-option]'), { timeout: 5000 });
            if (await s.page.$('[data-testid=token-add]')) throw new Error('the model panel should not have a token dropdown any more');
            await rowPress('Mark activated', 'title');
            await s.page.waitForSelector('[data-testid^=activated-]', { timeout: 5000 });
            const ring = await s.page.$eval('[data-testid^=hp-]', (g) => ({
                health: g.getAttribute('data-health'),
                segments: g.querySelectorAll('path').length,
                rect: g.querySelectorAll('rect').length,
            }));
            const look = await s.page.evaluate(() => {
                const g = document.querySelector('[data-testid^=hp-]').closest('[data-testid^=token-]');
                return {
                    ring: !!g.querySelector('[data-testid^=side-ring-]'),
                    fill: g.querySelector('circle:not([data-testid])').getAttribute('fill'),
                };
            });
            if (!look.ring || !/^hsl\(var\(--[a-z]+\)\)$/.test(look.fill))
                throw new Error(`a model's base should show its faction with its side as a ring: ${JSON.stringify(look)}`);
            const max = Number(ring.health.split('/')[1]);
            if (ring.rect || (max <= 12 && ring.segments !== max))
                throw new Error(`the health ring should have one segment per wound and no bar: ${JSON.stringify(ring)}`);
            // status badges: the Focus token is a chip on the base and the activation is a tick
            await s.page.waitForSelector('[data-testid^=badges-]', { timeout: 5000 });
            if (!(await s.page.$('[data-testid^=activated-]'))) throw new Error('an activated model should show a tick on its base');
            // a Fate flip from the model's options: the result is drawn beside it on the table, then discarded
            await s.page.$eval('[data-testid=model-fate-flip]', (b) => {
                b.scrollIntoView({ block: 'center' });
                b.click();
            });
            await s.page.waitForSelector('[data-testid^=fate-badge-]', { timeout: 5000 });
            await s.page.$eval('[data-testid=model-fate-choose]', (b) => b.click());
            await s.page.waitForSelector('[data-testid=model-fate-result]', { timeout: 5000 });
            await s.page.$eval('[data-testid=model-fate-done]', (b) => b.click());
            await s.page.waitForFunction(() => !document.querySelector('[data-testid^=fate-badge-]'), { timeout: 5000 });
            await L.shot(s, `359-model-info-${label}`);
            // drawn at its real base size: the new model has a 50 mm (0.984") radius
            const radii = await s.page.$$eval('[data-testid^=token-] > circle', (cs) => cs.map((c) => Number(c.getAttribute('r'))));
            if (Math.abs(radii[radii.length - 1] - 25 / 25.4) > 0.01) throw new Error(`the new model should have a 50 mm base: ${radii}`);
            // replace from the row: another character, same place; the token, activation and capped health are kept (desktop shows the button)
            const replaceBtn = await s.page.$eval(row, (r) => {
                const b = [...r.querySelectorAll('button')].find((x) => x.offsetParent && /^Replace/.test(x.getAttribute('title') ?? ''));
                b?.click();
                return !!b;
            });
            if (replaceBtn) {
                await s.page.waitForSelector('[data-testid=character-search]', { timeout: 5000 });
                await s.page.type('[data-testid=character-search]', 'seedlady beta');
                await s.page.waitForFunction(() => document.querySelectorAll('[data-testid=character-result]').length === 1, { timeout: 5000 });
                await s.page.click('[data-testid=character-add]');
                await s.page.waitForFunction(
                    () => {
                        const cs = [...document.querySelectorAll('[data-testid^=token-] > circle')];
                        return cs.some((c) => Math.abs(Number(c.getAttribute('r')) - 15 / 25.4) < 0.01);
                    },
                    { timeout: 5000 },
                );
                const [cur, max] = (await hp()).split(' / ').map(Number);
                if (!(cur >= 1 && cur <= max && cur <= 7)) throw new Error(`health after replace should be capped by the new maximum: ${cur}/${max}`);
                if (!(await s.page.$('[data-testid^=activated-]'))) throw new Error('replace should keep the model activated');
                if (!(await s.page.$('[data-testid^=badges-]'))) throw new Error('replace should keep the model tokens');
                await s.page.waitForFunction(() => !document.querySelector('[data-testid=character-picker]'), { timeout: 5000 });
            }
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=character-picker]'), { timeout: 5000 }); // the dialog has finished closing
            await hit('[data-testid=reset-table]');
            // reset clears the models (nothing blank comes back) and the hint says how to start again
            await s.page
                .waitForFunction(() => document.querySelectorAll('[data-testid^=token-]').length === 0, { timeout: 5000 })
                .catch(async () => {
                    throw new Error(`reset left ${(await s.page.$$('[data-testid^=token-]')).length} models`);
                });
            await s.page.waitForSelector('[data-testid=empty-table]');
            // the empty table says where to start, and its button opens the character search
            if (!/Start here/.test(await s.page.$eval('[data-testid=empty-table]', (e) => e.innerText)))
                throw new Error('the empty table should say where to start');
            await s.page.$eval('[data-testid=empty-add]', (b) => b.click());
            await s.page.waitForSelector('[data-testid=character-search]', { timeout: 5000 });
            await s.page.keyboard.press('Escape');
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=character-picker]'), { timeout: 5000 });
        });
        await L.step(`tabletop (${label}): the controller chooses where the Remains marker goes, within 1" of the killed model`, s, async () => {
            await openSandbox([{ id: 'k1', sp: 6, sz: 1, name: 'Fallen', side: 'a', baseMm: 30, x: 18, y: 18 }]);
            await toBoardTop();
            const m = await pixel(18, 18);
            await s.page.mouse.click(m.x, m.y);
            await s.page.waitForSelector('[data-testid=model-kill]', { timeout: 4000 });
            await s.page.$eval('[data-testid=model-kill]', (b) => {
                b.scrollIntoView({ block: 'center' });
                b.click();
            });
            await s.page.waitForSelector('[data-testid=remains-prompt]', { timeout: 4000 });
            if (!/Choose the spot/.test(await s.page.$eval('[data-testid=remains-prompt]', (e) => e.innerText)))
                throw new Error('the prompt should say the controller chooses the spot');
            await s.page.$eval('[data-testid=remains-choose]', (b) => b.click());
            await s.page.waitForSelector('[data-testid=remains-ring]', { timeout: 3000 });
            // a tap far outside the 1" is pulled in to the line (edge to edge, exactly 1")
            await toBoardTop();
            const far = await pixel(18, 26);
            await s.page.mouse.click(far.x, far.y);
            await s.page.waitForFunction(() => document.querySelectorAll('[data-marker=true]').length === 1, { timeout: 4000 });
            const gap = await s.page.evaluate(() => {
                const mk = document.querySelector('[data-marker=true]');
                const base = document.querySelector('[data-testid=token-k1] > circle:not([data-testid])');
                const dx = Number(mk.getAttribute('cx')) - Number(base.getAttribute('cx'));
                const dy = Number(mk.getAttribute('cy')) - Number(base.getAttribute('cy'));
                return { gap: Math.hypot(dx, dy) - Number(mk.getAttribute('r')) - Number(base.getAttribute('r')), below: dy > 0 };
            });
            if (Math.abs(gap.gap - 1) > 0.02 || !gap.below)
                throw new Error(`the marker should be pulled in to 1" below the model: ${JSON.stringify(gap)}`);
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=remains-prompt]'), { timeout: 4000 });
        });
        await L.step(
            `tabletop (${label}): a replacement is placed in base contact with where the original stood, on the side chosen`,
            s,
            async () => {
                await openSandbox([
                    { id: 'r1', sp: 6, sz: 1, name: 'Old', side: 'a', baseMm: 40, x: 18, y: 18, info: { health: 5, maxHealth: 5, tokens: [] } },
                ]);
                const replaceBtn = await s.page.$eval('[data-testid=crew-list]', (r) => {
                    const b = [...r.querySelectorAll('button')].find((x) => x.offsetParent && /^Replace/.test(x.getAttribute('title') ?? ''));
                    b?.click();
                    return !!b;
                });
                if (!replaceBtn) return; // the row's Replace button is for wider screens
                const replaceWith = async () => {
                    await s.page.waitForSelector('[data-testid=character-search]', { timeout: 5000 });
                    await s.page.$eval('[data-testid=character-search]', (i) => (i.value = ''));
                    await s.page.type('[data-testid=character-search]', 'seedlady beta');
                    await s.page.waitForFunction(() => document.querySelectorAll('[data-testid=character-result]').length === 1, { timeout: 5000 });
                    await s.page.click('[data-testid=character-add]');
                    await s.page.waitForFunction(() => !document.querySelector('[data-testid=character-picker]'), { timeout: 5000 });
                };
                await replaceWith();
                await s.page.waitForSelector('[data-testid=unplaced-bar][role=alert]', { timeout: 4000 });
                if (!/base contact/.test(await s.page.$eval('[data-testid=unplaced-bar]', (e) => e.innerText)))
                    throw new Error('the prompt should say the replacement goes in base contact');
                const centre = () =>
                    s.page.evaluate(() => {
                        const c = document.querySelector('[data-testid=token-r1] > circle:not([data-testid])');
                        return { x: Number(c.getAttribute('cx')), y: Number(c.getAttribute('cy')), r: Number(c.getAttribute('r')) };
                    });
                await s.page.$eval('[data-testid=place-unplaced]', (b) => b.click());
                await s.page.waitForSelector('[data-testid=contact-ring]', { timeout: 3000 });
                await toBoardTop();
                const tap = await pixel(26, 18); // the right-hand side
                await s.page.mouse.click(tap.x, tap.y);
                await s.page.waitForFunction(() => !document.querySelector('[data-testid=unplaced-bar]'), { timeout: 4000 });
                const at = await centre();
                const touching = 20 / 25.4 + at.r; // the original's radius (40 mm) plus the new one
                if (Math.abs(at.x - (18 + touching)) > 0.02 || Math.abs(at.y - 18) > 0.02)
                    throw new Error(`the replacement should touch the original's place on the right: ${JSON.stringify(at)}`);
                // replaced again, and placed for the player: it ends up touching where it stood
                await s.page.$eval('[data-testid=crew-list]', (r) => {
                    [...r.querySelectorAll('button')].find((x) => x.offsetParent && /^Replace/.test(x.getAttribute('title') ?? ''))?.click();
                });
                await replaceWith();
                await s.page.waitForSelector('[data-testid=place-contact-auto]', { timeout: 4000 });
                await s.page.$eval('[data-testid=place-contact-auto]', (b) => b.click());
                await s.page.waitForFunction(() => !document.querySelector('[data-testid=unplaced-bar]'), { timeout: 4000 });
                const moved = await centre();
                const apart = Math.hypot(moved.x - at.x, moved.y - at.y);
                if (Math.abs(apart - (at.r + moved.r)) > 0.02)
                    throw new Error(`placed for the player, it should touch where the original stood: ${apart} apart`);
            },
        );
        await L.step(`tabletop (${label}): models within 1" of an enemy are engaged (not peons), and an engaged Walk is half the Sp`, s, async () => {
            const gap = 30 / 25.4 + 1; // two 30 mm bases with 1" between their edges
            await openSandbox([
                { id: 'e1', sp: 6, sz: 1, name: 'Left', side: 'a', baseMm: 30, x: 18, y: 18 },
                { id: 'e2', sp: 6, sz: 1, name: 'Right', side: 'b', baseMm: 30, x: 18 + gap, y: 18 },
                { id: 'e3', sp: 6, sz: 1, name: 'Peon', side: 'b', baseMm: 30, x: 18, y: 19.8, peon: true },
                { id: 'e4', sp: 6, sz: 1, name: 'Far', side: 'b', baseMm: 30, x: 30, y: 30 },
            ]);
            await s.page.waitForSelector('[data-testid=engaged-e1]', { timeout: 5000 });
            await s.page.waitForSelector('[data-testid=engaged-e2]');
            const engaged = await s.page.$$eval('[data-testid^=engaged-]', (els) => els.map((e) => e.getAttribute('data-testid')).sort());
            if (engaged.join() !== 'engaged-e1,engaged-e2') throw new Error(`only the two facing each other are engaged: ${engaged}`);
            await toBoardTop();
            const at = await pixel(18, 18);
            await s.page.mouse.click(at.x, at.y);
            await s.page.waitForSelector('[data-testid=engaged-note]', { timeout: 4000 });
            const text = await s.page.$eval('[data-testid=engaged-note]', (e) => e.innerText);
            if (!/Right/.test(text) || !/\(3"\)/.test(text) || !/missile/.test(text)) throw new Error(`unexpected engagement note: ${text}`);
            // a walk of 4" is over the halved Sp 3
            await dragTo([18, 18], [18, 14]);
            await s.page.waitForFunction(() => /halved: engaged/.test(document.querySelector('[data-testid=tabletop-note]').innerText), {
                timeout: 4000,
            });
            // moved away, it is no longer engaged
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=engaged-e1]'), { timeout: 4000 });
        });
        await L.step(
            `tabletop (${label}): stairs let a model change height with no climb, and a Charge is a straight line that an engaged model cannot declare`,
            s,
            async () => {
                const rect = (x, y, w, h) => ({ kind: 'rect', x, y, w, h });
                const terrain = [
                    {
                        id: 'st',
                        name: 'Stairs',
                        traits: ['ramp'],
                        surfaces: [
                            { id: 'st1', shape: rect(15.75, 16.5, 4.5, 3), top: 1 },
                            { id: 'st2', shape: rect(17.25, 16.5, 3, 3), top: 2 },
                            { id: 'st3', shape: rect(18.75, 16.5, 1.5, 3), top: 3 },
                        ],
                    },
                    { id: 'pf', name: 'Platform', traits: ['climbable'], surfaces: [{ id: 'pf1', shape: rect(20.25, 16.5, 3.75, 3), top: 3 }] },
                ];
                await openSandbox(
                    [
                        { id: 'w1', sp: 12, sz: 1, name: 'Walker', side: 'a', baseMm: 30, x: 13, y: 18 },
                        { id: 'w2', sp: 12, sz: 1, name: 'Climber', side: 'a', baseMm: 30, x: 22.5, y: 24 },
                        { id: 'c1', sp: 6, sz: 1, name: 'Charger', side: 'a', baseMm: 30, x: 8, y: 8 },
                        { id: 'c2', sp: 6, sz: 1, name: 'Foe', side: 'b', baseMm: 30, x: 8 + 30 / 25.4 + 0.6, y: 8 },
                    ],
                    terrain,
                );
                await s.page.waitForSelector('[data-testid=tabletop-board] svg');
                const note = () => s.page.$eval('[data-testid=tabletop-note]', (e) => e.innerText);
                // over the stairs onto the platform: no climbing cost
                await dragTo([13, 18], [22.5, 18]);
                await s.page.waitForFunction(() => /stairs or a ramp/.test(document.querySelector('[data-testid=tabletop-note]').innerText), {
                    timeout: 4000,
                });
                const free = await note();
                if (/climbed/.test(free)) throw new Error(`stairs should cost no climb: ${free}`);
                // straight up the side of the platform: the climb counts as movement
                await dragTo([22.5, 24], [22.5, 18.4]);
                await s.page.waitForFunction(() => /climbed 3/.test(document.querySelector('[data-testid=tabletop-note]').innerText), {
                    timeout: 4000,
                });
                // a Charge: a straight line up to the Sp; an engaged model cannot declare it
                await s.page.$eval('[data-testid=move-kind-charge]', (b) => b.click());
                await dragTo([8, 8], [8, 14]);
                await s.page.waitForFunction(
                    () => /cannot declare Charge while engaged/.test(document.querySelector('[data-testid=tabletop-note]').innerText),
                    { timeout: 4000 },
                );
                await dragTo([8, 14], [8, 21]);
                await s.page.waitForFunction(
                    () => /more than a Charge \(Sp 6\)/.test(document.querySelector('[data-testid=tabletop-note]').innerText),
                    { timeout: 4000 },
                );
            },
        );
        await L.step(
            `tabletop (${label}): help opens from its button, the terrain editor and the ? key, and the page lists the shortcuts`,
            s,
            async () => {
                await openSandbox();
                await s.page.waitForSelector('[data-testid=tabletop-board] svg');
                const open = (what) =>
                    s.page.waitForSelector('[data-testid=help-dialog]', { timeout: 4000 }).catch(() => {
                        throw new Error(`help did not open from ${what}`);
                    });
                const gone = () => s.page.waitForFunction(() => !document.querySelector('[data-testid=help-dialog]'), { timeout: 4000 });
                const text = () => s.page.$eval('[data-testid=help-scroll]', (e) => e.innerText);
                // the button opens it at the start, and its sections switch
                await hit('[data-testid=tabletop-help-open]');
                await open('its button');
                if (!/Two tabs/.test(await text())) throw new Error('help should open at Getting started');
                await s.page.$eval('[data-testid=help-tab-terrain]', (b) => b.click());
                await s.page.waitForFunction(() => /Draw your own outline/.test(document.querySelector('[data-testid=help-scroll]').innerText), {
                    timeout: 3000,
                });
                if (!/Stairs \(steps/.test(await text())) throw new Error('the terrain help should list the presets');
                await s.page.keyboard.press('Escape');
                await gone();
                // the terrain editor's own Help opens at terrain
                await s.page.click('[data-testid=mode-terrain]');
                await hit('[data-testid=tabletop-help-open]');
                await open('the button on the terrain tab');
                if (!/Draw your own outline/.test(await text())) throw new Error('the terrain editor help should open at Building terrain');
                await s.page.$eval('[data-testid=help-close]', (b) => b.click());
                await gone();
                // ? on the focused table opens the shortcuts
                await s.page.click('[data-testid=mode-move]');
                await s.page.$eval('[data-testid=tabletop-board] svg', (el) => el.focus());
                await s.page.keyboard.press('?');
                await open('the ? key');
                if (!/Choose the previous or next model/.test(await text())) throw new Error('? should open the keyboard shortcuts');
                await s.page.keyboard.press('Escape');
                await gone();
            },
        );
        await L.step(
            `tabletop (${label}): the zoom buttons sit in the bar above the board, Add comes before the models, and the side column can be skipped`,
            s,
            async () => {
                await openSandbox();
                await s.page.waitForSelector('[data-testid=crew-list]', { timeout: 6000 });
                const layout = await s.page.evaluate(() => {
                    const board = document.querySelector('[data-testid=tabletop-board]');
                    const zoom = document.querySelector('[data-testid=zoom-controls]');
                    const add = document.querySelector('[data-testid=add-panel]');
                    const list = document.querySelector('[data-testid=crew-list]');
                    return {
                        zoomOnBoard:
                            !!board &&
                            !!zoom &&
                            !board.contains(zoom) &&
                            !!zoom.closest('[data-testid=board-bar]') &&
                            !!(board.compareDocumentPosition(zoom) & Node.DOCUMENT_POSITION_PRECEDING),
                        addFirst: !!add && !!list && !!(add.compareDocumentPosition(list) & Node.DOCUMENT_POSITION_FOLLOWING),
                    };
                });
                if (!layout.zoomOnBoard) throw new Error('the zoom buttons should be in the bar above the board (not covering it)');
                if (!layout.addFirst) throw new Error('the Add panel should come before the list of models');
                // the first Tab stop in the side column skips to the models
                await s.page.$eval('[data-testid=skip-to-models]', (a) => a.click());
                const focused = await s.page.evaluate(() => document.activeElement?.getAttribute('data-testid'));
                if (focused !== 'crew-list') throw new Error(`the skip link should move focus to the models: ${focused}`);
                // the height labels can be put away
                if (!(await s.page.$('[data-testid=height-label]'))) throw new Error('the terrain should show its heights by default');
                await s.page.$eval('[data-testid=heights-toggle]', (i) => i.click());
                await s.page.waitForFunction(() => !document.querySelector('[data-testid=height-label]'), { timeout: 3000 });
                await s.page.$eval('[data-testid=heights-toggle]', (i) => i.click());
                await s.page.waitForSelector('[data-testid=height-label]', { timeout: 3000 });
                // the Fate deck button does not look pressed on a tab that keeps its panel shut
                await s.page.$eval('[data-testid=fate-open]', (b) => b.click());
                await s.page.waitForSelector('[data-testid=fate-docked], [data-testid=fate-sheet]', { timeout: 5000 });
                await s.page.click('[data-testid=mode-terrain]');
                await L.sleep(400);
                const pressed = await s.page.$eval('[data-testid=fate-open]', (b) => b.getAttribute('aria-expanded'));
                if (pressed !== 'false') throw new Error(`the Fate deck button should not look open on the Terrain tab: ${pressed}`);
            },
        );
        await L.step(
            `tabletop (${label}): the side column is grouped: tabs for Models, Terrain and Table, the tools above the board, Help beside the tabs`,
            s,
            async () => {
                await openSandbox();
                await s.page.waitForSelector('[data-testid=mode-tabs]');
                const tabsNow = await s.page.$$eval('[data-testid=mode-tabs] [role=tab]', (t) => t.map((x) => x.innerText.trim()));
                if (tabsNow.join(',') !== 'Models,Terrain,Table') throw new Error(`the tabs should be Models, Terrain, Table: ${tabsNow}`);
                // the tools sit with the board, not in the side column, and Help is an icon next to the tabs
                const where = await s.page.evaluate(() => {
                    const tools = document.querySelector('[data-testid=move-tools]');
                    return {
                        toolsUnderBoard: !!tools?.closest('[data-testid=board-column]'),
                        toolsInSide: !!tools?.closest('[data-testid=side-column]'),
                        helpInTabRow: !!document.querySelector('[data-testid=tab-row] [data-testid=tabletop-help-open]'),
                        tableSettingsShown: !!document.querySelector('[data-testid=table-panel]')?.checkVisibility?.(),
                    };
                });
                if (!where.toolsUnderBoard || where.toolsInSide) throw new Error(`the tools should be with the board: ${JSON.stringify(where)}`);
                if (!where.helpInTabRow) throw new Error('Help should sit beside the tabs');
                if (where.tableSettingsShown) throw new Error('the table settings belong to the Table tab');
                // the Table tab holds the saved tables and the surface and zones; the models tab then has no table settings
                await s.page.click('[data-testid=mode-table]');
                await s.page.waitForSelector('[data-testid=table-panel]', { visible: true, timeout: 3000 });
                for (const id of ['table-select', 'deployment-select', 'table-lines-toggle', 'badges-toggle', 'heights-toggle', 'table-save']) {
                    const shown = await s.page.$eval(`[data-testid=${id}]`, (e) => !!e.checkVisibility?.());
                    if (!shown) throw new Error(`${id} should be on the Table tab`);
                }
                if (await s.page.$eval('[data-testid=crew-list]', (e) => !!e.checkVisibility?.()).catch(() => false))
                    throw new Error('the model list belongs to the Models tab');
                // the arrow keys move through the three tabs and wrap
                await s.page.$eval('[data-testid=mode-table]', (b) => b.focus());
                await s.page.keyboard.press('ArrowRight');
                await s.page.waitForFunction(() => document.querySelector('[data-testid=mode-move]')?.getAttribute('aria-selected') === 'true', {
                    timeout: 3000,
                });
                await s.page.keyboard.press('ArrowLeft');
                await s.page.waitForFunction(() => document.querySelector('[data-testid=mode-table]')?.getAttribute('aria-selected') === 'true', {
                    timeout: 3000,
                });
            },
        );
        await L.step(`tabletop (${label}): terrain is edited in a sheet on a phone, with the board still in view above it`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            await s.page.click('[data-testid=mode-terrain]');
            await s.page.waitForSelector('[data-testid=terrain-editor]', { timeout: 4000 });
            const sheet = await s.page.$('[data-testid=terrain-sheet]');
            if (label === 'desktop') {
                if (sheet) throw new Error('on a wide screen the terrain editor stays in the side column');
                return;
            }
            if (!sheet) throw new Error('on a phone the terrain editor should be a sheet');
            // the Fate button has nothing to open on this tab, so it does not float over the sheet
            if (await s.page.$eval('[data-testid=fate-open]', (b) => !!b.checkVisibility?.()))
                throw new Error('the floating Fate button should be hidden while the terrain sheet is up');
            await L.sleep(900); // the board scrolls into view
            const fit = await s.page.evaluate(() => {
                const board = document.querySelector('[data-testid=tabletop-board]').getBoundingClientRect();
                const sheetEl = document.querySelector('[data-testid=terrain-sheet]').getBoundingClientRect();
                return { boardTop: Math.round(board.top), boardBottom: Math.round(board.bottom), sheetTop: Math.round(sheetEl.top) };
            });
            if (fit.boardBottom > fit.sheetTop + 2 || fit.boardTop < -2)
                throw new Error(`the whole board should show above the sheet: ${JSON.stringify(fit)}`);
            // Done puts the sheet away and returns to the models
            await s.page.$eval('[data-testid=terrain-sheet-done]', (b) => b.click());
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=terrain-sheet]'), { timeout: 3000 });
            if ((await s.page.$eval('[data-testid=mode-move]', (b) => b.getAttribute('aria-selected'))) !== 'true')
                throw new Error('Done should return to the Models tab');
        });
        await L.step(`tabletop (${label}): the model list can be one line per model, and keeps its Tokens and health`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=crew-list]', { timeout: 6000 });
            const rowHeight = () =>
                s.page.evaluate(() => {
                    const row = [...document.querySelectorAll('[data-testid=crew-list] [data-testid^=crew-]')].find(
                        (r) => r.getAttribute('data-testid') !== 'crew-list',
                    );
                    return Math.round(row.getBoundingClientRect().height);
                });
            const full = await rowHeight();
            await s.page.$eval('[data-testid=compact-toggle]', (i) => i.click());
            await s.page.waitForFunction(
                (h) => {
                    const row = [...document.querySelectorAll('[data-testid=crew-list] [data-testid^=crew-]')].find(
                        (r) => r.getAttribute('data-testid') !== 'crew-list',
                    );
                    return Math.round(row.getBoundingClientRect().height) < h * 0.75;
                },
                { timeout: 4000 },
                full,
            );
            const compact = await rowHeight();
            // the health stepper is still on the row; the rarer buttons are in its menu
            const state = await s.page.evaluate(() => {
                const row = [...document.querySelectorAll('[data-testid=crew-list] [data-testid^=crew-]')].find(
                    (r) => r.getAttribute('data-testid') !== 'crew-list',
                );
                const vis = (e) => !!e && !!e.checkVisibility?.();
                return {
                    stepper: vis(row.querySelector('[aria-label^="Decrease health"]')) && vis(row.querySelector('[aria-label^="Increase health"]')),
                    tokensButton: [...row.querySelectorAll('button')].some((b) => vis(b) && b.getAttribute('title') === 'Tokens'),
                    more: vis(row.querySelector('[data-testid=row-more]')),
                };
            });
            // the column keeps to the screen: a long name must be cut short, not widen the whole column
            const fits = await s.page.evaluate(
                () => document.querySelector('[data-testid=side-column]').getBoundingClientRect().right <= window.innerWidth - 4,
            );
            if (!fits) throw new Error('the side column is wider than the screen');
            if (!state.stepper) throw new Error('a compact row keeps the health stepper');
            if (state.tokensButton) throw new Error('a compact row has no separate Tokens button');
            if (!state.more) throw new Error('a compact row has a menu for its other actions');
            if (label === 'desktop' && compact > 56)
                throw new Error(`a compact row should be one line on a wide screen: ${compact}px (full ${full}px)`);
            // Tokens are the first thing in the menu, and it opens the token dialog
            await s.page.$eval('[data-testid=row-more]', (b) => b.scrollIntoView({ block: 'center' }));
            await s.page.click('[data-testid=row-more]');
            await s.page.waitForSelector('[role=menuitem]', { timeout: 3000 });
            const items = await s.page.$$eval('[role=menuitem]', (e) => e.map((x) => x.innerText.trim()));
            if (!items.includes('Tokens') || !items.some((t) => /Replace/.test(t)))
                throw new Error(`the menu should offer Tokens and Replace: ${items}`);
            await s.page.evaluate(() => [...document.querySelectorAll('[role=menuitem]')].find((x) => /^Tokens/.test(x.innerText.trim())).click());
            await s.page.waitForFunction(() => /Manage Tokens/.test(document.body.innerText), { timeout: 4000 });
            await s.page.keyboard.press('Escape');
            await s.page.waitForFunction(() => !/Manage Tokens/.test(document.body.innerText), { timeout: 4000 });
            // the choice is kept on this device
            await L.visit(s, '/tools/tabletop');
            await s.page.waitForSelector('[data-testid=compact-toggle]');
            if (!(await s.page.$eval('[data-testid=compact-toggle]', (i) => i.checked))) throw new Error('the compact list should be remembered');
            await s.page.$eval('[data-testid=compact-toggle]', (i) => i.click());
        });
        await L.step(`tabletop (${label}): a model's options are grouped, with Mark killed and Remove together at the end`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=crew-list]', { timeout: 6000 });
            // the Add card keeps the side picker beside the Add button, and shows no marker count while there are no markers
            const add = await s.page.evaluate(() => {
                const panel = document.querySelector('[data-testid=add-panel]');
                return {
                    side: !!panel.querySelector('[data-testid=new-side]'),
                    sideLabel: /(^|\n)Side(\n|$)/.test(panel.innerText),
                    markerCount: !!panel.querySelector('[data-testid=marker-count]'),
                    h: Math.round(panel.getBoundingClientRect().height),
                };
            });
            if (!add.side || add.sideLabel)
                throw new Error(`the side picker should sit beside Add, with no label of its own: ${JSON.stringify(add)}`);
            if (add.markerCount) throw new Error('there is no marker count while no markers are out');
            await toBoardTop();
            const m = await pixel(18, 4);
            await s.page.mouse.click(m.x, m.y);
            await s.page.waitForSelector('[data-testid=selected-panel] [data-testid=model-guide]', { timeout: 4000 });
            const grouped = await s.page.evaluate(() => {
                const panel = document.querySelector('[data-testid=selected-panel]');
                const text = panel.innerText;
                const danger = panel.querySelector('[data-testid=model-danger]');
                const actions = panel.querySelector('[data-testid=model-actions]');
                return {
                    headings: ['Check', 'Movement', 'Notes and auras'].filter((h) => new RegExp(`(^|\\n)${h}(\\n|$)`, 'i').test(text)),
                    killInDanger: !!danger?.querySelector('[data-testid=model-kill]'),
                    removeInDanger: !!danger?.querySelector('[data-testid=remove-token]'),
                    killInActions: !!actions?.querySelector('[data-testid=model-kill]'),
                    dangerLast:
                        !!danger &&
                        !panel.querySelector('[data-testid=aura-controls]')?.compareDocumentPosition(danger).toString().includes('0') &&
                        !!(panel.querySelector('[data-testid=aura-controls]').compareDocumentPosition(danger) & Node.DOCUMENT_POSITION_FOLLOWING),
                };
            });
            if (grouped.headings.length !== 3)
                throw new Error(`the options should have the headings Check, Movement and Notes and auras: ${JSON.stringify(grouped)}`);
            if (!grouped.killInDanger || !grouped.removeInDanger || grouped.killInActions)
                throw new Error(`Mark killed and Remove belong together at the end: ${JSON.stringify(grouped)}`);
            if (!grouped.dangerLast) throw new Error('the destructive actions come after the auras');
        });
        await L.step(`tabletop (${label}): choosing a tool never moves the board (the toolbar keeps its height)`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            const boardY = () =>
                s.page.evaluate(() => Math.round(document.querySelector('[data-testid=tabletop-board]').getBoundingClientRect().top + scrollY));
            const before = await boardY();
            // Place has its own controls, and sight guidelines an Aim button: both appear in the side column, not in the toolbar above the board
            await s.page.$eval('[data-testid=move-kind-place]', (b) => b.click());
            await s.page.waitForSelector('[data-testid=place-range]');
            if ((await boardY()) !== before) throw new Error(`choosing Place moved the board from ${before} to ${await boardY()}`);
            if (await s.page.$eval('[data-testid=place-range]', (e) => !!e.closest('[data-testid=board-bar]')))
                throw new Error('the Place controls belong in the side column');
            await s.page.$eval('[data-testid=move-kind-walk]', (b) => b.click());
            // the Ruler keeps the move buttons and Step in place (so the toolbar does not change height), and pressing one goes back to moving
            await s.page.$eval('[data-testid=mode-ruler]', (b) => b.click());
            await s.page.waitForFunction(() => document.querySelector('[data-testid=mode-ruler]')?.getAttribute('aria-pressed') === 'true', {
                timeout: 3000,
            });
            if ((await boardY()) !== before) throw new Error('turning on the Ruler moved the board');
            if (!(await s.page.$('[data-testid=move-kind-jump]')) || !(await s.page.$('[data-testid=step-toggle]')))
                throw new Error('the move buttons and Step stay on the toolbar in Ruler mode');
            await s.page.$eval('[data-testid=move-kind-jump]', (b) => b.click());
            await s.page.waitForFunction(() => document.querySelector('[data-testid=mode-ruler]')?.getAttribute('aria-pressed') === 'false', {
                timeout: 3000,
            });
            await s.page.$eval('[data-testid=move-kind-walk]', (b) => b.click());
            await toBoardTop();
            const m = await pixel(18, 4);
            await s.page.mouse.click(m.x, m.y);
            await s.page.waitForSelector('[data-testid=model-guide]');
            await toBoardTop();
            const guided = await boardY();
            await s.page.$eval('[data-testid=model-guide]', (b) => b.click());
            await s.page.waitForSelector('[data-testid=guide-panel]');
            await s.page.waitForSelector('[data-testid=mode-sight]');
            if ((await boardY()) !== guided) throw new Error('switching on sight guidelines moved the board');
            if (await s.page.$eval('[data-testid=mode-sight]', (e) => !!e.closest('[data-testid=board-bar]')))
                throw new Error('the Aim button belongs in the guide panel');
        });
        await L.step(`tabletop (${label}): a model can be chosen and moved from the keyboard alone, with the same rules and notes`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            await toBoardTop();
            await s.page.$eval('[data-testid=tabletop-board] svg', (el) => el.focus());
            await s.page.waitForFunction(() => getComputedStyle(document.querySelector('[data-testid=board-keys]')).display !== 'none', {
                timeout: 3000,
            });
            const cx = () => s.page.$eval('[data-testid=token-a1] > circle', (c) => Number(c.getAttribute('cx')));
            const before = await cx();
            // ] chooses the first model; the panel opens for it and the note says which
            await s.page.keyboard.press(']');
            await s.page.waitForFunction(() => /1 of /.test(document.querySelector('[data-testid=tabletop-note]').innerText), { timeout: 3000 });
            await s.page.waitForSelector('[data-testid=selected-panel] [data-testid=model-guide]', { timeout: 3000 });
            // two presses of the right arrow: 0.5" each; Shift is a finer 0.1"
            await s.page.keyboard.press('ArrowRight');
            await s.page.keyboard.press('ArrowRight');
            await s.page.keyboard.down('Shift');
            await s.page.keyboard.press('ArrowRight');
            await s.page.keyboard.up('Shift');
            await s.page.waitForFunction(() => /moved/.test(document.querySelector('[data-testid=tabletop-note]').innerText), { timeout: 4000 });
            const after = await cx();
            if (Math.abs(after - before - 1.1) > 0.02) throw new Error(`the model should have moved 1.1" to the right: ${before} to ${after}`);
            // Escape lets go of the model
            await s.page.keyboard.press('Escape');
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=selected-panel] [data-testid=model-guide]'), { timeout: 3000 });
        });
        await L.step(`tabletop (${label}): terrain can be chosen, moved, turned and removed from the keyboard alone`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            await s.page.click('[data-testid=mode-terrain]');
            await s.page.$eval('[data-testid=tabletop-board] svg', (el) => el.focus());
            const note = () => s.page.$eval('[data-testid=tabletop-note]', (e) => e.innerText);
            await s.page.keyboard.press(']');
            await s.page.waitForFunction(() => /1 of \d+\. Arrows move it/.test(document.querySelector('[data-testid=tabletop-note]').innerText), {
                timeout: 3000,
            });
            await s.page.keyboard.press('ArrowRight');
            await s.page.waitForFunction(() => /moved 0\.5" right/.test(document.querySelector('[data-testid=tabletop-note]').innerText), {
                timeout: 3000,
            });
            await s.page.keyboard.press('.');
            await s.page.waitForFunction(() => /turned 15 degrees clockwise/.test(document.querySelector('[data-testid=tabletop-note]').innerText), {
                timeout: 3000,
            });
            await s.page.keyboard.press('Delete');
            await s.page.waitForFunction(
                () => /removed \(Undo brings it back\)/.test(document.querySelector('[data-testid=tabletop-note]').innerText),
                { timeout: 3000 },
            );
            if (/Arrows move it/.test(await note())) throw new Error('the removed piece should be let go');
        });
        await L.step(`tabletop (${label}): a marker lists the models near it, peons left out`, s, async () => {
            await openSandbox([
                { id: 'mk', name: 'Scrap Marker', side: 'n', baseMm: 30, x: 18, y: 18, marker: true, markerId: 1 },
                { id: 'n1', sp: 6, sz: 1, name: 'Near Blue', side: 'a', baseMm: 30, x: 20, y: 18 },
                { id: 'n2', sp: 6, sz: 1, name: 'Near Red', side: 'b', baseMm: 30, x: 18, y: 20.5 },
                { id: 'n3', sp: 6, sz: 1, name: 'Peon Red', side: 'b', baseMm: 30, x: 16.5, y: 18, peon: true },
                { id: 'n4', sp: 6, sz: 1, name: 'Far Blue', side: 'a', baseMm: 30, x: 32, y: 32 },
            ]);
            await toBoardTop();
            const at = await pixel(18, 18);
            await s.page.mouse.click(at.x, at.y);
            await s.page.waitForSelector('[data-testid=marker-neighbours]', { timeout: 4000 });
            const blue = await s.page.$eval('[data-testid=marker-neighbours-a]', (e) => e.innerText);
            const red = await s.page.$eval('[data-testid=marker-neighbours-b]', (e) => e.innerText);
            if (!/Near Blue/.test(blue) || /Far Blue/.test(blue)) throw new Error(`blue within 3": ${blue}`);
            if (!/Near Red/.test(red) || /Peon/.test(red)) throw new Error(`red within 3" should leave the peon out: ${red}`);
            await s.page.$eval('[data-testid=marker-range]', (i) => {
                i.value = '1';
                i.dispatchEvent(new Event('input'));
            });
            await s.page.waitForFunction(() => /none/.test(document.querySelector('[data-testid=marker-neighbours-b]').innerText), { timeout: 4000 });
        });
        await L.step(`tabletop (${label}): a killed model offers a Remains marker within 1", and a peon makes none`, s, async () => {
            await openSandbox([
                { id: 'k1', sp: 6, sz: 1, name: 'Fallen', side: 'a', baseMm: 30, x: 18, y: 18 },
                { id: 'k2', sp: 6, sz: 1, name: 'Peon', side: 'a', baseMm: 30, x: 26, y: 18, peon: true },
            ]);
            await toBoardTop();
            const markers = () => s.page.$$eval('[data-marker=true]', (els) => els.length);
            const start = await markers();
            const pick = async (x, y) => {
                await toBoardTop();
                const m = await pixel(x, y);
                await s.page.mouse.click(m.x, m.y);
            };
            const kill = async () => {
                await s.page.waitForSelector('[data-testid=model-kill]', { timeout: 4000 });
                await s.page.$eval('[data-testid=model-kill]', (b) => {
                    b.scrollIntoView({ block: 'center' });
                    b.click();
                });
            };
            await pick(18, 18);
            await kill();
            await s.page.waitForSelector('[data-testid=remains-prompt]', { timeout: 4000 });
            await s.page.$eval('[data-testid=remains-place]', (b) => b.click());
            await s.page.waitForFunction((n) => document.querySelectorAll('[data-marker=true]').length === n, { timeout: 4000 }, start + 1);
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=remains-prompt]'), { timeout: 4000 });
            // the Remains marker is within 1" of the fallen model's base
            const near = await s.page.evaluate(() => {
                const m = [...document.querySelectorAll('[data-marker=true]')].pop();
                const base = document.querySelector('[data-testid=token-k1] > circle');
                const dx = Number(m.getAttribute('cx')) - Number(base.getAttribute('cx'));
                const dy = Number(m.getAttribute('cy')) - Number(base.getAttribute('cy'));
                return Math.hypot(dx, dy) - Number(m.getAttribute('r')) - Number(base.getAttribute('r'));
            });
            if (near > 1.0001) throw new Error(`the Remains marker is ${near}" from the fallen model, not within 1"`);
            // a peon is killed: no Remains marker
            await pick(26, 18);
            await kill();
            await L.sleep(500);
            if (await s.page.$('[data-testid=remains-prompt]')) throw new Error('a peon makes no Remains marker');
        });
        await L.step(`tabletop (${label}): the practice Fate deck draws, discards, flips, looks at the top and searches`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=fate-section]');
            await s.page.$eval('[data-testid=fate-open]', (el) => {
                el.scrollIntoView({ block: 'center' });
                el.click();
            });
            await s.page.waitForSelector('[data-testid=fate-side-1]', { timeout: 5000 });
            // wide: docked in the column under the button; a phone: a bottom sheet, with the table still on screen above it
            const phone = await s.page.evaluate(() => window.innerWidth < 1024);
            await s.page.waitForSelector(phone ? '[data-testid=fate-sheet]' : '[data-testid=fate-docked]', { timeout: 3000 });
            if (phone) {
                const covered = await s.page.evaluate(() => {
                    const sheet = document.querySelector('[data-testid=fate-sheet]').getBoundingClientRect();
                    return sheet.top > window.innerHeight * 0.3; // the sheet leaves at least the top third free
                });
                if (!covered) throw new Error('the Fate sheet should leave the table visible above it');
            }
            const press = (id) =>
                s.page.$eval(`[data-testid=${id}]`, (b) => {
                    b.scrollIntoView({ block: 'center' });
                    b.click();
                });
            const settle = () =>
                s.page.waitForFunction(() => !document.querySelector('[role=dialog]') && document.body.style.pointerEvents !== 'none', {
                    timeout: 5000,
                });
            const handIs = (n) =>
                s.page.waitForFunction(
                    (want) => document.querySelector('[data-testid=fate-side-1] [data-testid=fate-hand-count]').innerText.trim() === want,
                    { timeout: 5000 },
                    `${n}/7`,
                );
            await handIs(0);
            await press('fate-draw');
            await handIs(1);
            await press('fate-refill');
            await handIs(7);
            // the start phase: discard some, then draw up to the maximum
            await press('fate-start-phase');
            await s.page.waitForSelector('[data-testid=fate-start-dialog]', { timeout: 4000 });
            await s.page.$eval('[data-testid=fate-start-card]', (c) => c.click());
            await s.page.waitForFunction(() => /Discard 1, draw 1/.test(document.querySelector('[data-testid=fate-start-summary]').innerText), {
                timeout: 4000,
            });
            await press('fate-start-apply');
            await s.page.waitForFunction(
                () => !document.querySelector('[data-testid=fate-start-dialog]') && document.body.style.pointerEvents !== 'none',
                { timeout: 5000 },
            );
            await handIs(7);
            await s.page.waitForSelector('[data-testid=fate-discard-1] [data-testid=fate-discard-card]');
            // a hand card opens a menu; discarding it moves it to the face-up pile
            await s.page.$eval('[data-testid=fate-side-1] [data-testid=fate-hand-card]', (b) => b.click());
            await s.page.waitForSelector('[data-testid=fate-card-menu]');
            await press('fate-menu-discard');
            await handIs(6);
            await s.page.waitForSelector('[data-testid=fate-discard-1] [data-testid=fate-discard-card]');
            // a flip with a plus reveals two cards; take one and resolve
            await press('fate-plus');
            await press('fate-flip');
            await s.page.waitForFunction(() => document.querySelectorAll('[data-testid=fate-conflict] > *').length === 2, { timeout: 5000 });
            await press('fate-choose');
            await s.page.waitForSelector('[data-testid=fate-result]');
            await press('fate-resolve');
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=fate-conflict]'), { timeout: 5000 });
            // look at the top card and leave it on the bottom
            await press('fate-more');
            await press('fate-peek');
            await s.page.waitForSelector('[data-testid=fate-peek-dialog]');
            await s.page.select('[data-testid=fate-peek-row] select', 'bottom');
            await press('fate-peek-apply');
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=fate-peek-dialog]'), { timeout: 5000 });
            await settle();
            // search for a card: taking one puts it in the hand
            await press('fate-search');
            await s.page.waitForSelector('[data-testid=fate-search-card]');
            await s.page.$eval('[data-testid=fate-search-card]', (b) => b.click());
            await handIs(7);
            await settle();
            // cheating Fate: flip, take the card, then pick a hand card; the hand menu offers to replace the result with it
            await press('fate-flip');
            await s.page.waitForSelector('[data-testid=fate-choose]', { timeout: 5000 });
            await press('fate-choose');
            await s.page.waitForSelector('[data-testid=fate-result]', { timeout: 5000 });
            await s.page.waitForSelector('[data-testid=fate-cheat-hint]', { timeout: 4000 });
            const before = await s.page.$eval('[data-testid=fate-result]', (e) => e.innerText);
            await s.page.$eval('[data-testid=fate-side-1] [data-testid=fate-hand-card]', (c) => c.click());
            await s.page.waitForSelector('[data-testid=fate-menu-cheat]', { timeout: 4000 });
            await press('fate-menu-cheat');
            await s.page.waitForFunction((was) => document.querySelector('[data-testid=fate-result]').innerText !== was, { timeout: 4000 }, before);
            // one chance per flip: the menu now says so
            await s.page.$eval('[data-testid=fate-side-1] [data-testid=fate-hand-card]', (c) => c.click());
            await s.page.waitForSelector('[data-testid=fate-cheat-none]', { timeout: 4000 });
            await s.page.$eval('[data-testid=fate-side-1] [data-testid=fate-hand-card]', (c) => c.click()); // put the menu away
            await press('fate-resolve');
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=fate-conflict]'), { timeout: 5000 });
            // an opposed duel: Blue attacks, Red resists, both enter totals, and the lowest total (Red's 9) cheats first
            const flipsOpen = (n) =>
                s.page.waitForFunction((want) => document.querySelectorAll('[data-testid=fate-conflict]').length === want, { timeout: 5000 }, n);
            const turnText = () => s.page.$eval('[data-testid=fate-duel-turn]', (e) => e.innerText).catch(() => '');
            await s.page.select('[data-testid=fate-duel-role]', 'attacker');
            await press('fate-flip');
            await s.page.waitForSelector('[data-testid=fate-choose]', { timeout: 5000 });
            await press('fate-choose');
            await s.page.waitForSelector('[data-testid=fate-result]', { timeout: 5000 });
            await press('fate-act-2');
            await press('fate-draw');
            await press('fate-draw');
            await s.page.select('[data-testid=fate-duel-role]', 'resister');
            await press('fate-flip');
            await flipsOpen(2);
            await s.page.waitForSelector('[data-testid=fate-choose]', { timeout: 5000 });
            await press('fate-choose'); // Red's own flip is the only one Red may take a card from
            await s.page.waitForFunction(() => document.querySelectorAll('[data-testid=fate-result]').length === 2, { timeout: 5000 });
            const setTotal = async (slot, total) => {
                await press(`fate-act-${slot}`);
                await s.page.waitForSelector('[data-testid=fate-duel-total]', { timeout: 4000 });
                await s.page.$eval(
                    '[data-testid=fate-duel-total]',
                    (i, v) => {
                        i.value = String(v);
                        i.dispatchEvent(new Event('input'));
                    },
                    total,
                );
                await press('fate-duel-total-save');
            };
            await setTotal(2, 9);
            await setTotal(1, 12);
            await s.page.waitForFunction(() => /chance to cheat/.test(document.querySelector('[data-testid=fate-duel-turn]')?.innerText ?? ''), {
                timeout: 5000,
            });
            if (!/Red/.test(await turnText())) throw new Error(`the lower total (Red's 9) should cheat first: ${await turnText()}`);
            // Blue may not cheat or pass out of turn: the controls are not offered
            if (await s.page.$('[data-testid=fate-pass]')) throw new Error('Blue should not be offered a pass before Red has had a turn');
            await press('fate-act-2');
            await s.page.$eval('[data-testid=fate-side-2] [data-testid=fate-hand-card]', (c) => c.click());
            await s.page.waitForSelector('[data-testid=fate-menu-cheat]', { timeout: 4000 });
            await press('fate-menu-cheat');
            await s.page.waitForFunction(() => /Blue/.test(document.querySelector('[data-testid=fate-duel-turn]')?.innerText ?? ''), {
                timeout: 5000,
            });
            await press('fate-act-1');
            await s.page.waitForSelector('[data-testid=fate-pass]', { timeout: 4000 });
            await press('fate-pass');
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=fate-duel-turn]'), { timeout: 5000 });
            // done: each side discards its own flip
            await press('fate-resolve');
            await press('fate-act-2');
            await press('fate-resolve');
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=fate-conflict]'), { timeout: 5000 });
            await press('fate-act-1');
            // effects on the other hand: Red draws, Blue asks to see it, Red shows, and Blue puts it away; then a random discard
            await press('fate-act-2');
            await press('fate-draw');
            await press('fate-act-1');
            await s.page.waitForSelector('[data-testid=fate-ask-reveal]');
            await press('fate-ask-reveal');
            await s.page.waitForSelector('[data-testid=fate-prompt]', { timeout: 5000 });
            await press('fate-prompt-show');
            await s.page.waitForSelector('[data-testid=fate-answer] [data-testid=fate-answer-dismiss]', { timeout: 5000 });
            await press('fate-answer-dismiss');
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=fate-answer]'), { timeout: 5000 });
            await press('fate-random-discard');
            await s.page.waitForSelector('[data-testid=fate-strip-pile-2]', { timeout: 5000 });
            // the history says what happened, in words
            await s.page.$eval('[data-testid=fate-history] summary', (el) => el.click());
            await s.page.waitForFunction(() => /showed their hand to Blue/.test(document.querySelector('[data-testid=fate-history]').innerText), {
                timeout: 5000,
            });
            await L.shot(s, `370-practice-fate-${label}`);
        });
        await L.step(`tabletop (${label}): the sandbox offers no blank models and starts empty`, s, async () => {
            await s.page.evaluate(() => localStorage.removeItem('tabletop-spike-v2'));
            await L.visit(s, '/tools/tabletop');
            await s.page.waitForSelector('[data-testid=empty-table]');
            if ((await s.page.$$('[data-testid^=token-]')).length !== 0) throw new Error('the sandbox should start with no models');
            if (await s.page.$('[data-testid=add-token]')) throw new Error('there should be no blank "Add model" button');
            await s.page.waitForSelector('[data-testid=add-menu]');
            await L.expectText(s, /Add a character/);
        });
        await L.step(`tabletop (${label}): the terrain editor selects, edits, moves, adds, turns, undoes and clears pieces`, s, async () => {
            await openSandbox();
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            const toBoard = () => s.page.evaluate(() => document.querySelector('[data-testid=tabletop-board]').scrollIntoView());
            const centreOn = (testid) =>
                s.page.$eval(`[data-testid=${testid}]`, (b) => {
                    const d = b.closest('details');
                    if (d && !d.open) d.open = true;
                    b.scrollIntoView({ block: 'center' });
                });
            const click = async (testid) => {
                await centreOn(testid);
                await s.page.click(`[data-testid=${testid}]`).catch((e) => {
                    throw new Error(`could not click ${testid}: ${e.message}`);
                });
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
                await toBoardTop(); // the controls sit further down the page now, so bring the board back before each tap
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
            await hit('[data-testid=table-load]');
            await s.page.waitForSelector('[data-testid=load-dialog]');
            await s.page.$eval('[data-testid=load-default][data-key=hills]', (b) => b.click());
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=load-dialog]') && document.body.style.pointerEvents !== 'none', {
                timeout: 5000,
            });
            await s.page.waitForFunction(() => /7 pieces/.test(document.querySelector('[data-testid=terrain-stats]').innerText), { timeout: 5000 });
            const covered = Number(((await stats()).match(/Covered: (\d+)%/) || [])[1]);
            if (covered < 45 || covered > 65) throw new Error(`a ready-made table should cover 45-65%: ${await stats()}`);
            await L.shot(s, `358-terrain-layout-${label}`);
            await click('terrain-undo');
            await s.page.waitForFunction(() => /0 pieces/.test(document.querySelector('[data-testid=terrain-stats]').innerText), { timeout: 5000 });
            await hit('[data-testid=table-load]');
            await s.page.waitForSelector('[data-testid=load-dialog]');
            await s.page.$eval('[data-testid=load-default][data-key=demo]', (b) => b.click());
            await s.page.waitForFunction(() => !document.querySelector('[data-testid=load-dialog]') && document.body.style.pointerEvents !== 'none', {
                timeout: 5000,
            });
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
