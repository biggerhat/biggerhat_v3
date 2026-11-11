/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Fixing pairings during round setup: click a player, then another player / an unpaired player / a table to swap.
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const org = await L.session(browser, 'org@example.test');
    const pairing = async () =>
        org.page.$$eval('div.rounded-md.border.px-2', (rows) =>
            rows.map((r) => r.innerText.replace(/\s+/g, ' ').trim()).filter((t) => /Table \d/.test(t)),
        );

    await L.step('swap: two pairings show clickable players and the unpaired player', org, async () => {
        await L.visit(org, `/tournaments/${ids.t5}`);
        await L.clickText(org, /^Rounds/i, '[role=tab]');
        await L.expectText(org, /To fix a pairing, click a player/i);
        await L.expectText(org, /Unpaired:\s*Eve/i);
        await L.shot(org, '90-swap-before');
    });
    await L.step('swap: Ben <-> Cat moves players across tables', org, async () => {
        await L.clickText(org, /^Ben$/, 'button');
        await L.expectText(org, /Now pick the player to swap with/i);
        await L.clickText(org, /^Cat$/, 'button');
        await L.expectText(org, /Ann\s*vs\s*Cat|Ann vs Cat/i);
        const rows = await pairing();
        if (!rows.some((r) => /Table 1.*Ann.*Cat/.test(r)) || !rows.some((r) => /Table 2.*Ben.*Dan/.test(r)))
            throw new Error(`unexpected pairings: ${rows.join(' | ')}`);
        await L.shot(org, '91-swap-after');
    });
    await L.step('swap: Eve (unpaired) replaces Ann, Ann becomes unpaired', org, async () => {
        await L.clickText(org, /^Eve$/, 'button');
        await L.clickText(org, /^Ann$/, 'button');
        await L.expectText(org, /Unpaired:\s*Ann/i);
        const rows = await pairing();
        if (!rows.some((r) => /Table 1.*Eve.*Cat/.test(r))) throw new Error(`unexpected pairings: ${rows.join(' | ')}`);
    });
    await L.step('swap: table numbers swap', org, async () => {
        await L.clickText(org, /^Table 1$/, 'button');
        await L.clickText(org, /^Table 2$/, 'button');
        await org.page.waitForFunction(() => /Table 2.*Eve.*Cat/.test(document.body.innerText.replace(/\s+/g, ' ')), { timeout: 9000 });
        await L.shot(org, '92-swap-tables');
    });
    await L.step('swap: selection can be cancelled', org, async () => {
        await L.clickText(org, /^Dan$/, 'button');
        await L.clickText(org, /^Cancel$/, 'button');
        await L.expectText(org, /To fix a pairing, click a player/i);
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
