/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const fs = require('fs');
const os = require('os');
const path = require('path');
const L = require('./lib.cjs');
// Importing a finished event from a CSV: check the file first, then create the completed tournament.
(async () => {
    const browser = await L.launch();
    const org = await L.session(browser, 'org@example.test');

    const good = path.join(os.tmpdir(), 'import-good.csv');
    fs.writeFileSync(
        good,
        [
            'round,table,player_one,player_one_faction,player_one_vp,player_two,player_two_faction,player_two_vp,result,is_bye,is_forfeit',
            '1,1,Ivy,Arcanists,9,Ian,Guild,4,completed,false,false',
            '1,2,Iris,Bayou,6,Ira,Neverborn,6,agreed,false,false',
            '2,1,Ivy,Arcanists,7,Iris,Bayou,5,completed,false,false',
            '2,2,Ian,Guild,3,Ira,Neverborn,8,completed,false,false',
        ].join('\n'),
    );
    const bad = path.join(os.tmpdir(), 'import-bad.csv');
    fs.writeFileSync(bad, ['round,player_one,player_two,player_one_vp,player_two_vp', '1,Ivy,Ian,50,4', 'x,Iris,Ira,6,6'].join('\n'));

    const fill = async () => {
        await org.page.type('#i-name', 'Imported Showdown');
        await org.page.$eval('#i-date', (el) => {
            el.value = '2026-09-12';
            el.dispatchEvent(new Event('input', { bubbles: true }));
        });
    };

    await L.step('import: the Create page links to the importer, which offers an example file', org, async () => {
        await L.visit(org, '/tournaments/create');
        await L.clickText(org, /Import the results from a CSV/i, 'a');
        await org.page.waitForSelector('[data-testid="import-template"]', { timeout: 9000 });
        await L.expectText(org, /Results file/i);
        await L.shot(org, '230-import-form');
    });
    await L.step('import: a file with problems is explained line by line and cannot be imported', org, async () => {
        await fill();
        await (await org.page.$('input[type=file]')).uploadFile(bad);
        await L.clickText(org, /^Check file$/i, 'button');
        await org.page.waitForSelector('[data-testid="import-preview"]', { timeout: 9000 });
        await L.expectText(org, /can't be imported yet/i);
        await L.expectText(org, /Line 2/i);
        const disabled = await org.page.evaluate(
            () => [...document.querySelectorAll('button')].find((b) => /^Import results$/.test(b.innerText.trim()))?.disabled,
        );
        if (!disabled) throw new Error('Import results should stay disabled for a file with errors');
        await L.shot(org, '231-import-errors');
    });
    await L.step('import: a clean file shows what it contains, then imports as a finished event', org, async () => {
        await (await org.page.$('input[type=file]')).uploadFile(good);
        await L.sleep(300);
        await L.clickText(org, /^Check file$/i, 'button');
        await L.expectText(org, /2 rounds · 4 games · 4 players/i);
        await L.shot(org, '232-import-preview');
        await L.clickText(org, /^Import results$/i, 'button');
        await L.expectText(org, /Imported Showdown/i);
        await L.expectText(org, /Ivy/);
        await L.shot(org, '233-imported-event');
    });
    await L.step('import: the imported event is completed, with standings and a results card', org, async () => {
        await L.expectText(org, /Completed/i);
        await L.expectText(org, /Current Standings|Final|Standings/i);
        await L.expectText(org, /Download PDF|Results/i);
    });

    await browser.close();
    const bad2 = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad2.length}/${L.results.length} clean`);
    if (bad2.length) process.exit(1);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
