/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Discord: connect a channel webhook from Settings, see it marked connected, try the test message, disconnect.
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const org = await L.session(browser, 'org@example.test');
    const fake = 'https://discord.com/api/webhooks/123456789012345678/abcdefghijklmnopqrstuvwxyz-ABCDEF_0123456789';

    await L.step('discord: Settings has a Discord card that is not connected yet', org, async () => {
        await L.visit(org, `/tournaments/${ids.t1}`);
        await L.clickText(org, /^Settings$/i, '[role=tab]');
        await org.page.waitForSelector('[data-testid="discord-card"]', { timeout: 9000 });
        await L.expectText(org, /Channel settings → Integrations → Webhooks/i);
        const connected = await org.page.evaluate(() => /Connected/.test(document.querySelector('[data-testid="discord-card"]').innerText));
        if (connected) throw new Error('should start disconnected');
        await L.shot(org, '240-discord-card');
    });
    await L.step('discord: a URL that is not a Discord webhook is refused with a reason', org, async () => {
        await org.page.type('input[aria-label="Discord webhook URL"]', 'https://example.com/hook');
        await L.clickText(org, /^Connect$/i, 'button');
        await L.expectText(org, /does not look like a Discord webhook URL/i);
        org.problems.length = 0; // the 422 is exactly what this step expects
    });
    await L.step('discord: a real-looking webhook connects and is never shown again', org, async () => {
        await org.page.focus('input[aria-label="Discord webhook URL"]');
        await org.page.keyboard.down('Control');
        await org.page.keyboard.press('KeyA');
        await org.page.keyboard.up('Control');
        await org.page.keyboard.press('Backspace');
        await org.page.type('input[aria-label="Discord webhook URL"]', fake);
        await L.clickText(org, /^Connect$/i, 'button');
        await L.expectText(org, /Discord connected/i);
        await org.page.waitForFunction(() => /Connected/.test(document.querySelector('[data-testid="discord-card"]').innerText), { timeout: 9000 });
        const html = await org.page.content();
        if (html.includes('abcdefghijklmnopqrstuvwxyz')) throw new Error('the webhook secret must not be in the page');
        await L.shot(org, '241-discord-connected');
    });
    await L.step('discord: the test message reports back (the fake webhook is rejected by Discord)', org, async () => {
        await L.clickText(org, /^Send test message$/i, 'button');
        await L.expectText(org, /Discord did not accept the message/i, undefined, 20000);
        org.problems.length = 0; // the 422 is the expected answer for a webhook that does not exist
    });
    await L.step('discord: disconnecting asks first and clears the connection', org, async () => {
        await L.clickText(org, /^Disconnect$/i, 'button');
        await L.expectText(org, /Disconnect Discord\?/i);
        await L.clickText(org, /^Disconnect$/i, '[role=alertdialog] button, [role=dialog] button');
        await L.expectText(org, /Discord disconnected/i);
        await org.page.waitForFunction(() => !/Connected/.test(document.querySelector('[data-testid="discord-card"]').innerText), { timeout: 9000 });
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
    if (bad.length) process.exit(1);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
