/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
const L = require('./lib.cjs');
// Remember me: ticking the box sets the long-lived remember cookie, keeps you in across pages, and survives losing the session cookie.
(async () => {
    const browser = await L.launch();
    const s = await L.session(browser, null, { width: 390, height: 844 });
    const cookies = async () => (await s.page.cookies()).map((c) => ({ n: c.name.replace(/remember_web_.*/, 'remember_web'), session: c.session, exp: c.expires }));
    const loggedIn = async (path) => {
        await L.visit(s, path);
        return !s.page.url().includes('/login');
    };

    await L.step('remember: the checkbox state reaches the login request', s, async () => {
        await s.page.goto(`${L.BASE}/login`, { waitUntil: 'networkidle2' });
        await s.page.type('input[type=email]', 'org@example.test');
        await s.page.type('input[type=password]', 'password');
        await s.page.click('#remember');
        const checked = await s.page.$eval('#remember', (e) => e.getAttribute('data-state'));
        if (checked !== 'checked') throw new Error(`checkbox state ${checked}`);
        const req = s.page.waitForRequest((r) => r.url().endsWith('/login') && r.method() === 'POST');
        await Promise.all([s.page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}), s.page.click('form button[type=submit]')]);
        const body = (await req).postData() ?? '';
        if (!/"remember":true|remember=(1|true|on)/.test(body)) throw new Error(`login body: ${body}`);
    });
    await L.step('remember: a long-lived remember cookie is set', s, async () => {
        const c = await cookies();
        const r = c.find((x) => x.n === 'remember_web');
        if (!r || r.session) throw new Error(`cookies: ${JSON.stringify(c)}`);
    });
    await L.step('remember: staying signed in across page changes and reloads', s, async () => {
        for (const p of ['/tournaments', '/tools/crew-builder', '/', '/settings/profile']) if (!(await loggedIn(p))) throw new Error(`lost session on ${p}`);
        await s.page.reload({ waitUntil: 'networkidle2' });
        if (s.page.url().includes('/login')) throw new Error('lost session on reload');
    });
    await L.step('remember: losing the session cookie keeps you signed in', s, async () => {
        const all = await s.page.cookies();
        const sess = all.find((c) => /_session$/.test(c.name));
        await s.page.deleteCookie(sess);
        if (!(await loggedIn('/settings/profile'))) throw new Error('remember cookie did not restore the session');
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
