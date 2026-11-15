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
            await L.visit(s, '/tools/tabletop');
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
            if (!/^\d+(\.\d)?"$/.test(dist)) throw new Error(`unexpected trail label: ${dist}`);
            await dragTo([10, 8], [5.5, 5.5]); // then up onto the Ht 5 upper level
            await L.expectText(s, /climbed 2"/i);
            await dragTo([5.5, 5.5], [20, 12]); // and off to the table: a drop of 5
            await L.expectText(s, /1 irreducible damage/i);
        });
        await L.step(`tabletop (${label}): a model dropped on another base, or on impassable ground, is flagged`, s, async () => {
            await L.visit(s, '/tools/tabletop');
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            await s.page.click('[data-testid=mode-move]');
            await dragTo([12, 32], [24, 31.3]); // the minion onto the red henchman
            await L.expectText(s, /overlaps Henchman's base/i);
            await s.page.waitForSelector('[data-problem=true]');
            await s.page.waitForSelector('[data-testid=placement-warning]');
            await dragTo([24, 31.3], [12, 32]); // put it back
            await s.page.waitForFunction(() => !document.querySelector('[data-problem=true]'), { timeout: 5000 });
        });
        await L.step(`tabletop (${label}): the ruler measures edge to edge between two models`, s, async () => {
            await L.visit(s, '/tools/tabletop');
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            await s.page.click('[data-testid=mode-ruler]');
            await dragTo([12, 5], [18, 4]); // henchman (40mm) to master (50mm), centres 6.08" apart
            await s.page.waitForSelector('[data-testid=ruler-readout]');
            const t = await s.page.$eval('[data-testid=ruler-readout]', (e) => e.innerText);
            // 6.08 - 0.787 - 0.984 = 4.3
            if (!/4\.3"/.test(t)) throw new Error(`ruler readout is not 4.3": ${t}`);
        });
        await L.step(`tabletop (${label}): line of sight is clear in the open and blocked by a wall for a shorter model`, s, async () => {
            await L.visit(s, '/tools/tabletop');
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            await s.page.click('[data-testid=mode-move]');
            await dragTo([12, 32], [18, 20]); // the Sz 1 minion steps up behind the Ht 2 wall
            await s.page.click('[data-testid=mode-sight]');
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
        await L.step(`tabletop (${label}): level filter and deployment overlay`, s, async () => {
            await s.page.select('[data-testid=level-select]', '0');
            await s.page.select('[data-testid=deployment-select]', 'standard');
            await L.shot(s, `351-tabletop-filter-${label}`);
            if ((await hscroll(s)) > 1) throw new Error(`page scrolls sideways by ${await hscroll(s)}px`);
        });
        await L.step(`tabletop (${label}): all four deployments draw their real shapes and a centerline`, s, async () => {
            await L.visit(s, '/tools/tabletop');
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
            await L.visit(s, '/tools/tabletop');
            await s.page.waitForSelector('[data-testid=tabletop-board] svg');
            await L.clickText(s, /^Reset table$/i, 'button'); // earlier steps moved models; the table is saved on this device
            await s.page.evaluate(() => document.querySelector('[data-testid=tabletop-board]').scrollIntoView()); // the Reset button sits below the board on a phone
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
            await L.visit(s, '/tools/tabletop');
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
        await L.step(`tabletop (${label}): adding a model works`, s, async () => {
            // centre the button first: on a phone the fixed tab bar can sit over a button scrolled to the very bottom
            await s.page.$eval('[data-testid=add-token]', (b) => b.scrollIntoView({ block: 'center' }));
            await s.page.click('[data-testid=add-token]');
            await s.page
                .waitForFunction(() => document.querySelectorAll('[data-testid^=token-]').length === 7, { timeout: 5000 })
                .catch(async () => {
                    throw new Error(`model was not added: ${(await s.page.$$('[data-testid^=token-]')).length} on the table`);
                });
        });
        await L.step(`tabletop (${label}): the terrain editor selects, edits, moves, adds, turns, undoes and clears pieces`, s, async () => {
            await L.visit(s, '/tools/tabletop');
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
            await L.visit(s, '/tools/tabletop');
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
