/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Game board: a live game's crews are on the 36" table, a dragged model keeps its place after a reload,
// and a person who is not in the game cannot open it.
(async () => {
    const browser = await L.launch();
    const hscroll = (s) => s.page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    const circle = (s, nth) =>
        s.page.evaluate((i) => {
            const c = document.querySelectorAll('[data-testid^=token-] > circle')[i];
            const r = c.getBoundingClientRect();
            return { cx: Number(c.getAttribute('cx')), cy: Number(c.getAttribute('cy')), px: r.left + r.width / 2, py: r.top + r.height / 2 };
        }, nth);

    const nav = await L.session(browser, 'carol@example.test');
    await L.step('board: the play screen links to it', nav, async () => {
        await L.visit(nav, `/games/${L.ids.play_game}`);
        await nav.page.waitForSelector('[data-testid=open-board]');
        await nav.page.click('[data-testid=open-board]');
        await nav.page.waitForSelector('[data-testid=tabletop-board]', { timeout: 9000 }).catch(async () => {
            throw new Error(`board did not appear at ${nav.page.url()}: ${(await L.text(nav)).replace(/\\s+/g, ' ').slice(0, 300)}`);
        });
        if (!nav.page.url().endsWith('/board')) throw new Error(`landed on ${nav.page.url()}`);
        await L.clickText(nav, /Back to the game/i, 'a');
        await nav.page.waitForSelector('[data-testid=open-board]');
    });

    for (const [label, vp] of [
        ['desktop', { width: 1280, height: 900 }],
        ['phone', { width: 390, height: 844 }],
    ]) {
        const carol = await L.session(browser, 'carol@example.test', vp);
        await L.step(`board (${label}): the game's crews are on the table with a Back link`, carol, async () => {
            await L.visit(carol, `/games/${L.ids.play_game}/board`);
            await carol.page.waitForSelector('[data-testid^=token-]');
            const n = (await carol.page.$$('[data-testid^=token-]')).length;
            if (n < 2) throw new Error(`expected both crews on the table, found ${n} models`);
            await L.expectText(carol, /Back to the game/);
            if ((await hscroll(carol)) > 1) throw new Error(`page scrolls sideways by ${await hscroll(carol)}px`);
            await L.shot(carol, `360-board-${label}`);
        });
        await L.step(`board (${label}): a dragged model keeps its place after a reload`, carol, async () => {
            const before = await circle(carol, 0);
            await carol.page.mouse.move(before.px, before.py);
            await carol.page.mouse.down();
            await carol.page.mouse.move(before.px + 40, before.py + 80, { steps: 6 });
            await carol.page.mouse.up();
            await L.sleep(1200); // the save is a background request
            const dropped = await circle(carol, 0);
            await L.visit(carol, `/games/${L.ids.play_game}/board`);
            await carol.page.waitForSelector('[data-testid^=token-]');
            const after = await circle(carol, 0);
            if (Math.abs(after.cx - before.cx) < 0.5 && Math.abs(after.cy - before.cy) < 0.5)
                throw new Error(
                    `the model went back to where it started (before ${before.cx},${before.cy}; dropped ${dropped.cx},${dropped.cy}; after ${after.cx},${after.cy})`,
                );
        });
        await L.step(`board (${label}): terrain laid out on the game's table is saved with the game`, carol, async () => {
            const centre = (testid) =>
                carol.page.$eval(`[data-testid=${testid}]`, (b) => {
                    const d = b.closest('details');
                    if (d && !d.open) d.open = true;
                    b.scrollIntoView({ block: 'center' });
                });
            const click = async (testid) => {
                await centre(testid);
                await carol.page.click(`[data-testid=${testid}]`);
            };
            const pieces = () => carol.page.$eval('[data-testid=terrain-stats]', (e) => Number((e.innerText.match(/(\d+) pieces?/) || [])[1]));
            await L.visit(carol, `/games/${L.ids.play_game}/board`);
            await carol.page.waitForSelector('[data-testid=mode-terrain]');
            await click('mode-terrain');
            await carol.page.waitForSelector('[data-testid=terrain-stats]');
            const start = await pieces();
            await carol.page.select('[data-testid=terrain-preset]', 'tower');
            await click('terrain-add');
            await carol.page.waitForFunction(
                (n) => Number((document.querySelector('[data-testid=terrain-stats]').innerText.match(/(\d+) pieces?/) || [])[1]) === n,
                { timeout: 5000 },
                start + 1,
            );
            await L.sleep(1200); // the save is a background request
            await L.visit(carol, `/games/${L.ids.play_game}/board`);
            await carol.page.waitForSelector('[data-testid=mode-terrain]');
            await click('mode-terrain');
            const kept = await pieces();
            if (kept !== start + 1) throw new Error(`expected ${start + 1} pieces after a reload, found ${kept}`);
            await L.shot(carol, `361-board-terrain-${label}`);
            // Leave the table as it was found.
            await click('terrain-clear');
            await L.sleep(1200);
        });
        await L.step(`board (${label}): a marker from the list is placed at its base size and saved with the game`, carol, async () => {
            const hit = async (selector) => {
                await carol.page.$eval(selector, (b) => {
                    const d = b.closest('details');
                    if (d && !d.open) d.open = true;
                    b.scrollIntoView({ block: 'center' });
                });
                await carol.page.click(selector);
            };
            const addItem = async (id) => {
                await carol.page.waitForFunction(() => document.body.style.pointerEvents !== 'none', { timeout: 5000 });
                await hit('[data-testid=add-menu]');
                await carol.page.waitForSelector(`[data-testid=${id}]`);
                await carol.page.$eval(`[data-testid=${id}]`, (e) => e.click());
            };
            const markers = () =>
                carol.page.$$eval('[data-marker=true]', (cs) =>
                    cs.map((c) => ({ cx: Number(c.getAttribute('cx')), cy: Number(c.getAttribute('cy')), r: Number(c.getAttribute('r')) })),
                );
            const open = async () => {
                await L.visit(carol, `/games/${L.ids.play_game}/board`);
                await carol.page.waitForSelector('[data-testid=add-menu]');
            };
            await open();
            const start = (await markers()).length;
            await addItem('marker-open');
            await carol.page.waitForSelector('[data-testid=marker-picker]');
            await carol.page.type('[data-testid=marker-search]', 'corpse');
            const row = await carol.page.$eval('[data-testid=marker-result]', (r) => r.innerText.replace(/\s+/g, ' '));
            if (!/Corpse Marker/.test(row) || !/40 mm/.test(row)) throw new Error(`unexpected result row: ${row}`);
            await hit('[data-testid=marker-add]');
            await carol.page.waitForFunction((n) => document.querySelectorAll('[data-marker=true]').length === n, { timeout: 5000 }, start + 1);
            await L.sleep(1200);
            await open();
            const kept = await markers();
            if (kept.length !== start + 1) throw new Error(`expected ${start + 1} markers after a reload, found ${kept.length}`);
            const mine = kept[kept.length - 1];
            if (Math.abs(mine.r - 20 / 25.4) > 0.01) throw new Error(`a 40 mm marker has radius ${mine.r}, expected ${20 / 25.4}`);
            // Drag it somewhere else and check the move is saved too.
            await carol.page.evaluate(() => document.querySelector('[data-testid=tabletop-board]').scrollIntoView());
            const px = await carol.page.evaluate(
                ([x, y]) => {
                    const svg = document.querySelector('[data-testid=tabletop-board] svg');
                    const r = svg.getBoundingClientRect();
                    const vb = svg.viewBox.baseVal;
                    return { x: r.left + ((x - vb.x) / vb.width) * r.width, y: r.top + ((y - vb.y) / vb.height) * r.height };
                },
                [mine.cx, mine.cy],
            );
            await carol.page.mouse.move(px.x, px.y);
            await carol.page.mouse.down();
            await carol.page.mouse.move(px.x + 60, px.y + 40, { steps: 6 });
            await carol.page.mouse.up();
            await L.sleep(1200);
            await open();
            const moved = (await markers()).find((m) => Math.abs(m.cx - mine.cx) > 0.5 || Math.abs(m.cy - mine.cy) > 0.5);
            if (!moved) throw new Error('the marker went back to where it was placed');
            await L.shot(carol, `364-board-marker-${label}`);
            // Pick it up again, and the table is as it was found.
            await carol.page.evaluate(() => document.querySelector('[data-testid=tabletop-board]').scrollIntoView());
            const at = await carol.page.evaluate(
                ([x, y]) => {
                    const svg = document.querySelector('[data-testid=tabletop-board] svg');
                    const r = svg.getBoundingClientRect();
                    const vb = svg.viewBox.baseVal;
                    return { x: r.left + ((x - vb.x) / vb.width) * r.width, y: r.top + ((y - vb.y) / vb.height) * r.height };
                },
                [moved.cx, moved.cy],
            );
            await carol.page.mouse.click(at.x, at.y);
            await hit('[data-testid=remove-token]');
            await L.sleep(1200);
            await open();
            if ((await markers()).length !== start) throw new Error('picking the marker up was not saved');
        });
        await L.step(
            `board (${label}): the crew list shows the play screen's rows; health and activation change from them and are saved with the game`,
            carol,
            async () => {
                const open = async () => {
                    await L.visit(carol, `/games/${L.ids.play_game}/board`);
                    await carol.page.waitForSelector('[data-testid=crew-list] [data-testid^=crew-]');
                };
                // the visible control inside a row (a row draws a phone and a desktop copy of some)
                const press = (rowId, label) =>
                    carol.page.evaluate(
                        (id, text) => {
                            const row = document.querySelector(`[data-testid=crew-${id}]`);
                            const b = [...row.querySelectorAll('button')].find(
                                (x) => x.offsetParent && (x.getAttribute('aria-label') ?? '').startsWith(text),
                            );
                            if (!b) return false;
                            b.scrollIntoView({ block: 'center' });
                            b.click();
                            return true;
                        },
                        rowId,
                        label,
                    );
                const hp = (rowId) =>
                    carol.page.evaluate(
                        (id) =>
                            document
                                .querySelector(`[data-testid=crew-${id}]`)
                                .innerText.match(/(\d+)\/(\d+)/)
                                ?.slice(1, 3)
                                .join('/'),
                        rowId,
                    );
                await open();
                const ids = await carol.page.$$eval('[data-testid=crew-list] [data-testid^=crew-]', (rs) =>
                    rs.map((r) => r.getAttribute('data-testid').slice(5)),
                );
                let mine = null;
                for (const id of ids) {
                    if (
                        await carol.page.evaluate(
                            (i) =>
                                [...document.querySelector(`[data-testid=crew-${i}]`).querySelectorAll('button')].some((b) =>
                                    (b.getAttribute('aria-label') ?? '').startsWith('Decrease health'),
                                ),
                            id,
                        )
                    ) {
                        mine = id;
                        break;
                    }
                }
                if (!mine) throw new Error('no crew row of mine could change health');
                const before = await hp(mine);
                const [cur, max] = before.split('/').map(Number);
                if (!(await press(mine, 'Decrease health'))) throw new Error('the row has no visible health control');
                await carol.page.waitForFunction(
                    (id, t) => document.querySelector(`[data-testid=crew-${id}]`).innerText.includes(t),
                    { timeout: 5000 },
                    mine,
                    `${cur - 1}/${max}`,
                );
                await L.sleep(1200);
                await open();
                const kept = await hp(mine);
                if (kept !== `${cur - 1}/${max}`) throw new Error(`health came back as ${kept}, expected ${cur - 1}/${max}`);
                await press(mine, 'Increase health'); // leave it as found
                // activation: the tick shows on the base
                await carol.page.evaluate((id) => {
                    const row = document.querySelector(`[data-testid=crew-${id}]`);
                    const b = [...row.querySelectorAll('button')].find((x) => x.offsetParent && /Mark activated/.test(x.getAttribute('title') ?? ''));
                    b?.click();
                }, mine);
                await carol.page.waitForSelector(`[data-testid=activated-${mine}]`, { timeout: 5000 });
                await L.shot(carol, `367-board-crew-list-${label}`);
                await carol.page.evaluate((id) => {
                    const row = document.querySelector(`[data-testid=crew-${id}]`);
                    [...row.querySelectorAll('button')]
                        .find((x) => x.offsetParent && /Mark unactivated/.test(x.getAttribute('title') ?? ''))
                        ?.click();
                }, mine);
                await L.sleep(1200);
            },
        );
        await L.step(`board (${label}): choosing a row opens the tabletop options under it, and the row can replace the model`, carol, async () => {
            await L.visit(carol, `/games/${L.ids.play_game}/board`);
            await carol.page.waitForSelector('[data-testid=crew-list] [data-testid^=crew-]');
            const id = await carol.page.evaluate(() => {
                const row = [...document.querySelectorAll('[data-testid=crew-list] [data-testid^=crew-]')].find((r) =>
                    [...r.querySelectorAll('button')].some((b) => (b.getAttribute('aria-label') ?? '').startsWith('Decrease health')),
                );
                const name = row.querySelector('button.font-semibold');
                name.scrollIntoView({ block: 'center' });
                name.click();
                return row.getAttribute('data-testid').slice(5);
            });
            await carol.page.waitForSelector(`[data-testid=crew-${id}] [data-testid=selected-panel]`, { timeout: 5000 }).catch(() => {
                throw new Error('the selected model options did not open under its row');
            });
            if (!(await carol.page.$(`[data-testid=crew-${id}] [data-testid=model-guide]`)))
                throw new Error('the row rolldown has no sight guidelines switch');
            if (await carol.page.$('[data-testid=model-activated]')) throw new Error('activation should come from the row, not a second control');
            await L.shot(carol, `368-board-row-rolldown-${label}`);
            // replace: the dialog opens from the row
            const opened = await carol.page.evaluate((rid) => {
                const row = document.querySelector(`[data-testid=crew-${rid}]`);
                const b = [...row.querySelectorAll('button')].find(
                    (x) => x.offsetParent && /Replace/.test(x.getAttribute('title') ?? x.getAttribute('aria-label') ?? ''),
                );
                if (b) b.click();
                return !!b;
            }, id);
            if (opened) {
                await carol.page.waitForFunction(() => /Replace Crew Member/.test(document.body.innerText), { timeout: 5000 });
                await carol.page.keyboard.press('Escape');
            }
        });
        await L.step(`board (${label}): which side the deployment zones sit is kept with the game`, carol, async () => {
            await L.visit(carol, `/games/${L.ids.play_game}/board`);
            await carol.page.waitForSelector('[data-testid=deployment-variant]');
            await carol.page.select('[data-testid=deployment-variant]', '2');
            await L.sleep(1200);
            await L.visit(carol, `/games/${L.ids.play_game}/board`);
            await carol.page.waitForSelector('[data-testid=deployment-variant]');
            const kept = await carol.page.$eval('[data-testid=deployment-variant]', (e) => e.value);
            if (kept !== '2') throw new Error(`the orientation came back as ${kept}`);
            await carol.page.select('[data-testid=deployment-variant]', '0'); // leave it as found
            await L.sleep(1200);
        });
        await L.step(`board (${label}): the game's table can be saved to my tables`, carol, async () => {
            await L.visit(carol, `/games/${L.ids.play_game}/board`);
            await carol.page.waitForSelector('[data-testid=table-save]');
            await carol.page.$eval('[data-testid=mode-terrain]', (b) => b.click());
            await carol.page.waitForSelector('[data-testid=terrain-add]');
            await carol.page.$eval('[data-testid=terrain-add]', (b) => b.click());
            await L.sleep(1200);
            await carol.page.$eval('[data-testid=table-save]', (b) => b.click());
            await carol.page.waitForSelector('[data-testid=save-dialog]');
            await carol.page.type('[data-testid=save-table-name]', 'Game table');
            await carol.page.$eval('[data-testid=save-table-confirm]', (b) => b.click());
            await carol.page.waitForFunction(
                () => !document.querySelector('[data-testid=save-dialog]') && document.body.style.pointerEvents !== 'none',
                { timeout: 8000 },
            );
            // it is now one of the viewer's tables, in the Load table dialog
            await carol.page.$eval('[data-testid=table-load]', (b) => b.click());
            await carol.page.waitForSelector('[data-testid=load-mine][data-name="Game table"]', { timeout: 8000 });
            await carol.page.keyboard.press('Escape');
            await carol.page.waitForFunction(
                () => !document.querySelector('[data-testid=load-dialog]') && document.body.style.pointerEvents !== 'none',
                { timeout: 5000 },
            );
            await carol.page.$eval('[data-testid=terrain-clear]', (b) => b.click()); // leave the table as found
            await L.sleep(1200);
        });
        await L.step(`board (${label}): the table surface chosen is kept with the game`, carol, async () => {
            await L.visit(carol, `/games/${L.ids.play_game}/board`);
            await carol.page.waitForSelector('[data-testid=table-select]');
            const surface = () => carol.page.$eval('[data-testid=table-surface]', (e) => e.getAttribute('data-surface'));
            if ((await surface()) !== 'grass') throw new Error(`the table should start as grass, saw ${await surface()}`);
            await carol.page.$eval('[data-testid=table-select]', (b) => {
                const d = b.closest('details');
                if (d && !d.open) d.open = true;
                b.scrollIntoView({ block: 'center' });
            });
            await carol.page.select('[data-testid=table-select]', 'cobble');
            await carol.page.waitForFunction(() => document.querySelector('[data-testid=table-surface]')?.getAttribute('data-surface') === 'cobble', {
                timeout: 5000,
            });
            await L.sleep(1200);
            await L.visit(carol, `/games/${L.ids.play_game}/board`);
            await carol.page.waitForSelector('[data-testid=table-surface]');
            const kept = await carol.page.$eval('[data-testid=table-select]', (e) => e.value);
            if (kept !== 'cobble') throw new Error(`the surface came back as ${kept}`);
            await L.shot(carol, `368-board-table-surface-${label}`);
            await carol.page.$eval('[data-testid=table-select]', (b) => {
                const d = b.closest('details');
                if (d && !d.open) d.open = true;
                b.scrollIntoView({ block: 'center' });
            });
            await carol.page.select('[data-testid=table-select]', 'grass'); // leave it as found
            await L.sleep(1200);
        });
        await L.step(`board (${label}): an aura shown to the other player is saved with the game`, carol, async () => {
            const hit = async (selector) => {
                await carol.page.$eval(selector, (b) => {
                    const d = b.closest('details');
                    if (d && !d.open) d.open = true;
                    b.scrollIntoView({ block: 'center' });
                });
                await carol.page.click(selector);
            };
            const open = async () => {
                await L.visit(carol, `/games/${L.ids.play_game}/board`);
                await carol.page.waitForSelector('[data-testid^=token-]');
            };
            await open();
            // Click models until one is mine: the other crew is locked, so its aura has no share button.
            const count = (await carol.page.$$('[data-testid^=token-]')).length;
            let found = false;
            for (let i = 0; i < count && !found; i++) {
                await carol.page.evaluate(() => document.querySelector('[data-testid=tabletop-board]').scrollIntoView());
                const c = await circle(carol, i);
                await carol.page.mouse.click(c.px, c.py);
                await carol.page.waitForSelector('[data-testid=aura-controls]');
                await hit('[data-testid=aura-add]');
                if (await carol.page.$('[data-testid=aura-share]')) found = true;
                else await hit('[data-testid=aura-remove]');
            }
            if (!found) throw new Error('no model of mine could show an aura');
            await hit('[data-testid=aura-share]');
            await carol.page.waitForSelector('[data-shared=true]');
            await L.sleep(1200);
            await open();
            if (!(await carol.page.$('[data-shared=true]'))) throw new Error('the shared aura was not kept after a reload');
            await L.shot(carol, `365-board-shared-aura-${label}`);
            // withdraw it: select its model again and remove the aura
            const auraOwner = await carol.page.$eval('[data-shared=true]', (g) => g.parentElement.getAttribute('data-testid'));
            const owner = await carol.page.$eval(`[data-testid=${auraOwner}]`, (g) => g.getAttribute('data-testid'));
            if (!owner) throw new Error('aura has no owner');
            const idx = await carol.page.evaluate(
                (o) =>
                    [...document.querySelectorAll('[data-testid^=token-]')].findIndex(
                        (g) => g.getAttribute('data-testid') === o.replace('aura-', 'token-'),
                    ),
                auraOwner,
            );
            await carol.page.evaluate(() => document.querySelector('[data-testid=tabletop-board]').scrollIntoView());
            const c = await circle(carol, idx);
            await carol.page.mouse.click(c.px, c.py);
            await hit('[data-testid=aura-remove]');
            await L.sleep(1200);
            await open();
            if (await carol.page.$('[data-shared=true]')) throw new Error('withdrawing the aura was not saved');
        });
    }

    const live = await L.session(browser, 'carol@example.test');
    await L.step('board: a move broadcast by the other player appears without a reload', live, async () => {
        // No Reverb server in the harness: stand in for Echo and fire the event the server would send.
        await live.page.evaluateOnNewDocument(() => {
            window.Echo = {
                join() {
                    const ch = {
                        listen(event, cb) {
                            if (event === '.GameBoardMoved') window.__boardMoved = cb;
                            if (event === '.GameBoardTerrainChanged') window.__boardTerrain = cb;
                            if (event === '.GameBoardMarkersChanged') window.__boardMarkers = cb;
                            if (event === '.GameBoardAurasChanged') window.__boardAuras = cb;
                            if (event === '.GameBoardSetupChanged') window.__boardSetup = cb;
                            return ch;
                        },
                    };
                    return ch;
                },
                // the notification bell listens on a private channel
                private() {
                    const quiet = { listen: () => quiet, notification: () => quiet };
                    return quiet;
                },
                leave() {},
            };
        });
        await L.visit(live, `/games/${L.ids.play_game}/board`);
        await live.page.waitForSelector('[data-testid^=token-]');
        await live.page.waitForFunction(() => typeof window.__boardMoved === 'function', { timeout: 9000 });
        const id = await live.page.evaluate(() =>
            document.querySelectorAll('[data-testid^=token-]')[1].getAttribute('data-testid').replace('token-', ''),
        );
        await live.page.evaluate((i) => window.__boardMoved({ positions: { [i]: { x: 20, y: 18 } } }), id);
        await L.sleep(300);
        const at = await live.page.evaluate(() => {
            const c = document.querySelectorAll('[data-testid^=token-] > circle')[1];
            return { cx: Number(c.getAttribute('cx')), cy: Number(c.getAttribute('cy')) };
        });
        if (Math.abs(at.cx - 20) > 0.01 || Math.abs(at.cy - 18) > 0.01) throw new Error(`model did not move: ${JSON.stringify(at)}`);
    });
    await L.step('board: terrain the other player lays out appears without a reload', live, async () => {
        await live.page.waitForFunction(() => typeof window.__boardTerrain === 'function', { timeout: 9000 });
        const wall = (id, x) => ({
            id,
            name: 'Wall',
            traits: ['blocking', 'climbable'],
            surfaces: [{ id: `${id}-1`, top: 2, shape: { kind: 'rect', x, y: 10, w: 8, h: 1.5 } }],
        });
        await live.page.evaluate((layout) => window.__boardTerrain({ terrain: layout }), [wall('w1', 4), wall('w2', 20)]);
        await live.page.waitForFunction(
            () =>
                document.querySelectorAll('[data-testid=tabletop-board] svg text').length > 0 &&
                [...document.querySelectorAll('[data-testid=tabletop-board] svg text')].filter((t) => t.textContent.trim() === 'Ht 2').length === 2,
            { timeout: 5000 },
        );
        await live.page.evaluate(() => window.__boardTerrain({ terrain: [] }));
        await live.page.waitForFunction(
            () => ![...document.querySelectorAll('[data-testid=tabletop-board] svg text')].some((t) => /^Ht /.test(t.textContent.trim())),
            { timeout: 5000 },
        );
    });

    await L.step('board: a marker the other player places appears without a reload', live, async () => {
        await live.page.waitForFunction(() => typeof window.__boardMarkers === 'function', { timeout: 9000 });
        await live.page.evaluate(() =>
            window.__boardMarkers({
                markers: [
                    { id: 'mx1', marker_id: 1, owner: 1, x: 10, y: 10 },
                    { id: 'mx2', marker_id: 1, owner: 2, x: 20, y: 20 },
                ],
            }),
        );
        await live.page.waitForFunction(() => document.querySelectorAll('[data-marker=true]').length === 2, { timeout: 5000 });
        await live.page.evaluate(() => window.__boardMarkers({ markers: [{ id: 'mx2', marker_id: 1, owner: 2, x: 22, y: 22 }] }));
        await live.page.waitForFunction(() => document.querySelectorAll('[data-marker=true]').length === 1, { timeout: 5000 });
        await live.page.evaluate(() => window.__boardMarkers({ markers: [] }));
        await live.page.waitForFunction(() => document.querySelectorAll('[data-marker=true]').length === 0, { timeout: 5000 });
    });

    await L.step('board: an aura the other player shows appears without a reload', live, async () => {
        await live.page.waitForFunction(() => typeof window.__boardAuras === 'function', { timeout: 9000 });
        const id = await live.page.evaluate(() =>
            document.querySelectorAll('[data-testid^=token-]')[1].getAttribute('data-testid').replace('token-', ''),
        );
        await live.page.evaluate((i) => window.__boardAuras({ auras: [{ id: 'sa1', owner_id: i, range: 4, locked: false }] }), id);
        await live.page.waitForFunction(() => document.querySelectorAll('[data-shared=true]').length === 1, { timeout: 5000 });
        await live.page.evaluate(() => window.__boardAuras({ auras: [] }));
        await live.page.waitForFunction(() => document.querySelectorAll('[data-shared=true]').length === 0, { timeout: 5000 });
    });

    await L.step('board: the other player turning the deployment round shows without a reload', live, async () => {
        await live.page.waitForFunction(() => typeof window.__boardSetup === 'function', { timeout: 9000 });
        await live.page.evaluate(() => window.__boardSetup({ deployment_variant: 3 }));
        await live.page.waitForFunction(() => document.querySelector('[data-testid=deployment-variant]')?.value === '3', { timeout: 5000 });
    });

    const watcher = await L.session(browser, 'alice@example.test');
    await L.step('board: a watcher of an observable game sees the board read-only and gets a link from the observe page', watcher, async () => {
        await L.visit(watcher, `/games/${L.ids.play_game}/observe`);
        await watcher.page.waitForSelector('[data-testid=open-board]');
        const label = await watcher.page.$eval('[data-testid=open-board]', (a) => a.innerText.trim());
        if (!/Watch the board/i.test(label)) throw new Error(`observer link says "${label}"`);
        await watcher.page.click('[data-testid=open-board]');
        await watcher.page.waitForSelector('[data-testid=watch-note]', { timeout: 9000 });
        await watcher.page.waitForSelector('[data-testid^=token-]');
        if (await watcher.page.$('[data-testid=mode-terrain]')) throw new Error('a watcher should not get the terrain editor');
        if (await watcher.page.$('[data-testid=marker-open]')) throw new Error('a watcher should not be able to add markers');
        // dragging a model does nothing: it stays where it was
        const before = await circle(watcher, 0);
        await watcher.page.mouse.move(before.px, before.py);
        await watcher.page.mouse.down();
        await watcher.page.mouse.move(before.px + 50, before.py + 50, { steps: 5 });
        await watcher.page.mouse.up();
        const after = await circle(watcher, 0);
        if (Math.abs(after.cx - before.cx) > 0.01 || Math.abs(after.cy - before.cy) > 0.01) throw new Error('a watcher moved a model');
        await L.shot(watcher, '366-board-watch');
    });

    const outsider = await L.session(browser, 'alice@example.test');
    await L.step('board: someone who is not in the game gets a 403', outsider, async () => {
        const res = await outsider.page.goto(`${L.BASE}/games/${L.ids.play_game}/board`, { waitUntil: 'networkidle2' });
        if (res.status() !== 403) throw new Error(`expected 403, got ${res.status()}`);
        outsider.problems.length = 0; // the browser logs the 403 itself; that is the point of the step
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
