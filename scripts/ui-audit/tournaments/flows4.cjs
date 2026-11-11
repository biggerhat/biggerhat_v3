/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
const MOBILE = { width: 390, height: 844, isMobile: true, hasTouch: true, deviceScaleFactor: 2 };
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const overflow = (s) =>
        s.page.evaluate(() => ({
            sw: document.documentElement.scrollWidth,
            iw: window.innerWidth,
            wide: [...document.querySelectorAll('body *')]
                .filter((e) => e.getBoundingClientRect().right > window.innerWidth + 1 && e.offsetParent !== null)
                .slice(0, 4)
                .map((e) => `${e.tagName.toLowerCase()}.${(e.className + '').toString().slice(0, 40)}`),
        }));
    const check = async (label, s, path, after) =>
        L.step(`mobile: ${label}`, s, async () => {
            await L.visit(s, path);
            if (after) await after();
            const o = await overflow(s);
            await L.shot(s, `40-${label.replace(/[^a-z0-9]+/gi, '_')}`);
            if (o.sw > o.iw + 1) throw new Error(`horizontal overflow ${o.sw}px > ${o.iw}px (${o.wide.join(', ')})`);
        });

    const alice = await L.session(browser, 'alice@example.test', MOBILE);
    const org = await L.session(browser, 'org@example.test', MOBILE);
    const guest = await L.session(browser, null, MOBILE);

    await check('view with match card (alice)', alice, `/tournaments/${ids.t1}/view`, async () => {
        await L.clickText(alice, /^Report result$/i);
    });
    await check('display mode', guest, `/tournaments/${ids.t1}/display`);
    await check('view fog event (guest)', guest, `/tournaments/${ids.t3}/view`);
    await check('create form', org, '/tournaments/create');
    await check('manage players (team event)', org, `/tournaments/${ids.t2}`, async () => {
        await L.clickText(org, /^Players/i, '[role=tab]');
    });
    await check('manage rounds (running event)', org, `/tournaments/${ids.t1}`);
    await check('manage settings edit', org, `/tournaments/${ids.t2}`, async () => {
        await L.clickText(org, /^Settings$/i, '[role=tab]');
        await L.clickText(org, /^Edit$/i);
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
