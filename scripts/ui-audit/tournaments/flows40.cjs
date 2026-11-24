/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const fs = require('fs');
const path = require('path');
const L = require('./lib.cjs');
const axePath = process.env.AXE || path.resolve('node_modules/axe-core/axe.min.js');
// Deployment of a two-player tabletop game, in the rulebook's order: the defender (Dave) divides the crew, the attacker (Carol) chooses
// which group goes first, then defender, attacker and defender place their models completely inside their zones. The websocket is off in
// the harness, so each player reloads to see the other's move.
(async () => {
    const browser = await L.launch();
    const carol = await L.session(browser, 'carol@example.test');
    const dave = await L.session(browser, 'dave@example.test');
    for (const s of [carol, dave]) s.page.on('dialog', (d) => d.accept().catch(() => {}));
    const game = L.ids.deploy_game;

    const props = (s) => s.page.evaluate(() => JSON.parse(document.querySelector('[data-page]').getAttribute('data-page')).props);
    const phase = (s) => s.page.$eval('[data-testid=deploy-panel]', (e) => e.dataset.phase);
    const waitPhase = (s, p) =>
        s.page
            .waitForFunction((want) => document.querySelector('[data-testid=deploy-panel]')?.dataset.phase === want, { timeout: 9000 }, p)
            .catch(async () => {
                throw new Error(`expected the ${p} phase, the panel says ${await phase(s).catch(() => 'nothing')}`);
            });
    const board = async (s) => {
        await L.visit(s, `/games/${game}/board`);
        await s.page.waitForSelector('[data-testid=deploy-panel]');
    };
    const modelId = async (s, name) => (await props(s)).models.find((m) => m.name === name).id;
    /** Drag a model on the table to (x, y) inches. The target is worked out from the live coordinates on every step (the page can scroll as the pointer goes down). */
    const drag = async (s, id, x, y) => {
        const where = () =>
            s.page.evaluate(
                (i, tx, ty) => {
                    const g = document.querySelector(`[data-testid=token-${i}]`);
                    const m = g.getScreenCTM();
                    const r = g.getBoundingClientRect();
                    return { fx: r.left + r.width / 2, fy: r.top + r.height / 2, tx: m.a * tx + m.e, ty: m.d * ty + m.f };
                },
                id,
                x,
                y,
            );
        await s.page.evaluate((i) => document.querySelector(`[data-testid=token-${i}]`).scrollIntoView({ block: 'center' }), id);
        await L.sleep(200);
        const from = await where();
        await s.page.mouse.move(from.fx, from.fy);
        await s.page.mouse.down();
        for (let k = 1; k <= 8; k++) {
            const now = await where();
            await s.page.mouse.move(from.fx + ((now.tx - from.fx) * k) / 8, from.fy + ((now.ty - from.fy) * k) / 8);
        }
        const end = await where();
        await s.page.mouse.move(end.tx, end.ty);
        await s.page.mouse.up();
        await L.sleep(1000);
    };
    /** Serious or critical axe findings fail the step; the lesser ones are only listed. */
    const axeScan = async (s, label) => {
        if (!fs.existsSync(axePath)) return;
        await s.page.evaluate(fs.readFileSync(axePath, 'utf8'));
        const res = await s.page.evaluate(async () => {
            const r = await window.axe.run(document, { resultTypes: ['violations'] });
            return r.violations.map((v) => ({ id: v.id, impact: v.impact, count: v.nodes.length, first: v.nodes[0].target.join(' ') }));
        });
        for (const v of res) console.log(`   [axe ${label}] ${v.impact} ${v.id} x${v.count}: ${v.first}`);
        const serious = res.filter((v) => v.impact === 'serious' || v.impact === 'critical');
        if (serious.length) throw new Error(`${serious.length} serious axe rule(s): ${serious.map((v) => `${v.id}x${v.count}`).join(', ')}`);
    };
    const zoneNote = (s) => s.page.$eval('[data-testid=deploy-zone-note]', (e) => e.innerText);

    await L.step('play screen: a tabletop game at deployment shows the Deploy step and a way to the table', carol, async () => {
        await L.visit(carol, `/games/${game}`);
        await carol.page.waitForSelector('[data-testid=deploy-card]');
        await L.expectText(carol, /Dave divides the defender's crew/);
        await L.expectText(carol, /Deploy/);
        await axeScan(carol, 'deploy card');
        await L.shot(carol, '400-deploy-card');
        await carol.page.click('[data-testid=deploy-open-table]');
        await carol.page.waitForSelector('[data-testid=deploy-panel]');
    });

    await L.step('board (attacker): while the defender divides the crew, there is nothing to do but wait or turn the zones', carol, async () => {
        await board(carol);
        if ((await phase(carol)) !== 'split') throw new Error(`phase was ${await phase(carol)}`);
        if (await carol.page.$('[data-testid=deploy-split]')) throw new Error("the attacker was offered the defender's split");
        if (!(await carol.page.$('[data-testid=deploy-rotate]'))) throw new Error('the attacker cannot turn the zones');
        // touching a model while the crew is being divided must not ask the server (which would answer 403): the models are locked
        const refused = [];
        carol.page.on('response', (r) => r.status() === 403 && refused.push(r.url()));
        await drag(carol, await modelId(carol, 'Pandora'), 10, 4);
        if (refused.length) throw new Error(`the server refused a move (403): ${refused.join(', ')}`);
    });

    await L.step('board (phone, defender): the split fits the screen and its controls are tap-sized', dave, async () => {
        const phone = await L.session(browser, 'dave@example.test', { width: 390, height: 844 });
        await L.visit(phone, `/games/${game}/board`);
        await phone.page.waitForSelector('[data-testid=deploy-split]');
        const over = await phone.page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        if (over > 1) throw new Error(`page scrolls sideways by ${over}px`);
        const small = await phone.page.evaluate(() =>
            [...document.querySelectorAll('[data-testid=deploy-panel] button, [data-testid=deploy-panel] label, [data-testid=deploy-panel] a')]
                .map((e) => ({ t: e.innerText.trim().slice(0, 30), h: Math.round(e.getBoundingClientRect().height) }))
                .filter((e) => e.h < 44),
        );
        if (small.length) throw new Error(`under 44px tall: ${small.map((e) => `${e.t} (${e.h}px)`).join(', ')}`);
        await L.shot(phone, '400-deploy-split-phone');
    });

    await L.step('board (defender): divides the crew, holding one model back', dave, async () => {
        await board(dave);
        await dave.page.waitForSelector('[data-testid=deploy-split]');
        const hound = await modelId(dave, 'Guild Hound');
        await axeScan(dave, 'split');
        await L.shot(dave, '400-deploy-split');
        await dave.page.click(`[data-testid=deploy-later-${hound}]`);
        await dave.page.click('[data-testid=deploy-split-confirm]');
        await waitPhase(dave, 'choose');
    });

    await L.step('board (attacker): chooses which group the defender deploys first', carol, async () => {
        await board(carol);
        await waitPhase(carol, 'choose');
        await carol.page.waitForSelector('[data-testid=deploy-choose-later]');
        await axeScan(carol, 'choose');
        await L.shot(carol, '400-deploy-choose');
        await carol.page.click('[data-testid=deploy-choose-later]');
        await waitPhase(carol, 'first');
    });

    await L.step(
        'board (defender): a model outside the zone cannot be finished, inside it can, and only the group being placed moves',
        dave,
        async () => {
            await board(dave);
            await waitPhase(dave, 'first');
            const hound = await modelId(dave, 'Guild Hound');
            const guard = await modelId(dave, 'Guild Guard');
            // the first group is the held-back hound, wherever it was left; the rest of the crew is locked meanwhile
            await drag(dave, guard, 3, 3);
            const guardAfter = await dave.page.$eval(`[data-testid=token-${guard}] circle`, (c) => [
                Number(c.getAttribute('cx')),
                Number(c.getAttribute('cy')),
            ]);
            if (Math.abs(guardAfter[0] - 3) < 0.5 && Math.abs(guardAfter[1] - 3) < 0.5)
                throw new Error('a model of the group not being placed could be moved');
            await drag(dave, hound, 18, 20);
            if (!/Not completely inside/.test(await zoneNote(dave))) throw new Error(`mid-table, the note said: ${await zoneNote(dave)}`);
            if (!(await dave.page.$eval('[data-testid=deploy-done]', (b) => b.disabled)))
                throw new Error('Finished placing was enabled outside the zone');
            await drag(dave, hound, 18, 32);
            if (!/completely inside/.test(await zoneNote(dave))) throw new Error(`in the zone, the note said: ${await zoneNote(dave)}`);
            await axeScan(dave, 'placing');
            await L.shot(dave, '400-deploy-placing');
            await dave.page.click('[data-testid=deploy-done]');
            await waitPhase(dave, 'attacker');
        },
    );

    await L.step('board (attacker): places the whole crew in the opposite zone', carol, async () => {
        await board(carol);
        await waitPhase(carol, 'attacker');
        await drag(carol, await modelId(carol, 'Pandora'), 10, 4);
        await drag(carol, await modelId(carol, 'Silurid'), 22, 4);
        if (await carol.page.$eval('[data-testid=deploy-done]', (b) => b.disabled)) {
            await L.shot(carol, '400-deploy-attacker-stuck');
            throw new Error(`Finished placing is disabled: ${await zoneNote(carol)}`);
        }
        await carol.page.click('[data-testid=deploy-done]');
        await waitPhase(carol, 'second');
    });

    await L.step('board (defender): places the rest of the crew, and the game moves on to schemes', dave, async () => {
        await board(dave);
        await waitPhase(dave, 'second');
        await drag(dave, await modelId(dave, 'Lady Justice'), 10, 32);
        await drag(dave, await modelId(dave, 'Guild Guard'), 24, 32);
        await dave.page.click('[data-testid=deploy-done]');
        await waitPhase(dave, 'done');
        await L.expectText(dave, /scheme/i);
        await L.shot(dave, '400-deploy-done');
    });

    await L.step('play screen: after deployment the game is at the scheme step', carol, async () => {
        await L.visit(carol, `/games/${game}`);
        await L.expectText(carol, /scheme/i);
        if (await carol.page.$('[data-testid=deploy-card]')) throw new Error('the deployment card is still showing');
        if ((await props(carol)).game.status !== 'scheme_select') throw new Error(`status was ${(await props(carol)).game.status}`);
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
