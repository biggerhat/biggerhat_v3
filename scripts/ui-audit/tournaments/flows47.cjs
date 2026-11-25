/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Site news admin: a published news post has a "Post to Discord" button that opens a dialog with an optional line of your own. The flow only
// opens and cancels it (it never sends, so nothing reaches Discord). Desktop and phone.
(async () => {
    const browser = await L.launch();
    const hscroll = (s) => s.page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    for (const [label, vp] of [
        ['desktop', { width: 1280, height: 900 }],
        ['phone', { width: 390, height: 844 }],
    ]) {
        const me = await L.session(browser, 'news@example.test', vp);
        await L.step(`news discord (${label}): the button opens a dialog with the post and a message box, and cancel closes it`, me, async () => {
            await L.visit(me, '/admin/news/posts');
            await L.expectText(me, /Collection tracking is here/);
            await me.page.click('[data-testid=news-discord]');
            await L.expectText(me, /Sends "Collection tracking is here" to the site's Discord channel/);
            await L.expectText(me, /A line of your own \(optional\)/);
            if (!(await me.page.$('[data-testid=news-discord-send]'))) throw new Error('no Post button');
            if ((await hscroll(me)) > 1) throw new Error('page scrolls sideways');
            await L.shot(me, `news-discord-${label}`);
            await L.clickText(me, /^Cancel$/, '[role=dialog] button');
            await L.sleep(400);
            if (await me.page.$('[data-testid=news-discord-dialog]')) throw new Error('the dialog should close on Cancel');
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
