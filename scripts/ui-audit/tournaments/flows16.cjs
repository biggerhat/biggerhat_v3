/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Finding events (search, format, upcoming / past) and adding one to a calendar.
(async () => {
    const browser = await L.launch();
    const { ids } = L;
    const guest = await L.session(browser, null);

    await L.step('find: the public list has a search box, a format filter and When buttons', guest, async () => {
        await L.visit(guest, '/tournaments');
        await guest.page.waitForSelector('input[aria-label="Search tournaments"]', { timeout: 9000 });
        await L.expectText(guest, /Spring Showdown/);
        await L.expectText(guest, /Capped Open/);
        await L.shot(guest, '150-find-events');
    });
    await L.step('find: typing narrows the list by name', guest, async () => {
        await guest.page.type('input[aria-label="Search tournaments"]', 'Capped');
        await guest.page.waitForFunction(() => /\?q=Capped/.test(location.search) && !/Spring Showdown/.test(document.body.innerText), {
            timeout: 9000,
        });
        await L.expectText(guest, /Capped Open/);
    });
    await L.step('find: Past shows only finished events, and an unmatched search says so', guest, async () => {
        await guest.page.$eval('input[aria-label="Search tournaments"]', (el) => {
            el.value = '';
            el.dispatchEvent(new Event('input', { bubbles: true }));
        });
        await L.clickText(guest, /^Past$/i, 'button');
        await guest.page.waitForFunction(
            () =>
                /when=past/.test(location.search) && /Spring Showdown/.test(document.body.innerText) && !/Capped Open/.test(document.body.innerText),
            { timeout: 9000 },
        );
        await guest.page.type('input[aria-label="Search tournaments"]', 'zzzz-nothing');
        await L.expectText(guest, /No tournaments match those filters/i);
        await L.shot(guest, '151-find-empty');
    });
    await L.step('find: the format filter and Upcoming combine', guest, async () => {
        await L.visit(guest, '/tournaments?when=upcoming');
        await L.pickByTrigger(guest, /All formats/i, /Fields of Glory/i);
        await guest.page.waitForFunction(
            () =>
                /ruleset=tos_fields_of_glory/.test(location.search) &&
                /FoG Open/.test(document.body.innerText) &&
                !/Capped Open/.test(document.body.innerText),
            { timeout: 9000 },
        );
    });

    await L.step('calendar: an upcoming event offers Add to calendar and serves a valid .ics', guest, async () => {
        await L.visit(guest, `/tournaments/${ids.t6}/view`);
        await L.expectText(guest, /Add to calendar/i);
        const res = await fetch(`${L.BASE}/tournaments/${ids.t6}/event.ics`);
        const body = await res.text();
        if (
            res.status !== 200 ||
            !/text\/calendar/.test(res.headers.get('content-type')) ||
            !body.startsWith('BEGIN:VCALENDAR') ||
            !/SUMMARY:Capped Open/.test(body)
        ) {
            throw new Error(`bad ics: ${res.status} ${body.slice(0, 80)}`);
        }
    });
    await L.step('calendar: a finished event does not offer it', guest, async () => {
        await L.visit(guest, `/tournaments/${ids.t7}/view`);
        const t = await L.text(guest);
        if (/Add to calendar/i.test(t)) throw new Error('a completed event should not offer Add to calendar');
    });

    await browser.close();
    const bad = L.results.filter((r) => r.status !== 'PASS');
    console.log(`\n${L.results.length - bad.length}/${L.results.length} clean`);
})().catch((e) => {
    console.error('RUNNER ERROR', e);
    process.exit(1);
});
