#!/usr/bin/env node
/*
 * Logged-in (or guest) UI audit: signs in through the real login form, visits pages at phone and
 * desktop widths, runs axe-core and a few layout checks, and writes screenshots. Local use only —
 * see docs/ui-audit.md.
 *
 *   CHROME=/path/to/chrome BASE=http://127.0.0.1:8765 node scripts/ui-audit/audit.cjs \
 *     --role member --pages /games,/collection --out /tmp/shots
 *
 *   --role     member | organizer | admin | guest   (default guest)
 *   --pages    comma-separated paths; or --discover to also follow links found on the sidebar
 *              (admin) / listing pages (games, tournaments, crews)
 *   --viewports mobile,desktop   --theme light|dark
 *   --detail   also print the first elements behind each serious/critical axe violation
 */
const fs = require('fs');
const path = require('path');
const puppeteer = require(path.resolve('node_modules/puppeteer-core'));

const arg = (name, fallback) => {
    const i = process.argv.indexOf(`--${name}`);
    return i === -1 ? fallback : process.argv[i + 1] && !process.argv[i + 1].startsWith('--') ? process.argv[i + 1] : true;
};
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const ROLE = arg('role', 'guest');
const OUT = arg('out', path.resolve('storage/app/ui-audit'));
const THEME = arg('theme', 'light');
const VIEWPORTS = String(arg('viewports', 'mobile,desktop')).split(',');
const DISCOVER = arg('discover', false) === true;
const DETAIL = arg('detail', false) === true;
const CREDS = {
    member: 'audit.member@example.test',
    organizer: 'audit.organizer@example.test',
    admin: 'audit.admin@example.test',
};
const axePath = process.env.AXE || path.resolve('node_modules/axe-core/axe.min.js');
const axeSrc = fs.existsSync(axePath) ? fs.readFileSync(axePath, 'utf8') : null;
if (!axeSrc) console.warn('axe-core not found (npm i --no-save axe-core or set AXE); skipping accessibility checks');
const VP = {
    mobile: { width: 390, height: 844, isMobile: true, hasTouch: true, deviceScaleFactor: 2 },
    desktop: { width: 1440, height: 900 },
};
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const slug = (p) => p.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'home';

async function newPage(browser, vp) {
    const page = await browser.newPage();
    await page.setViewport(VP[vp]);
    await page.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: THEME }]);
    await page.setCookie({ name: 'cookie_consent', value: 'accepted', url: BASE }, { name: 'appearance', value: THEME, url: BASE });
    return page;
}

async function login(page) {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle2' });
    await page.type('input[type=email], input[name=email]', CREDS[ROLE]);
    await page.type('input[type=password]', 'password');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}), page.keyboard.press('Enter')]);
    if (page.url().includes('/login')) throw new Error(`Login failed for ${ROLE} (is the database seeded? see docs/ui-audit.md)`);
}

async function discover(page, pages) {
    const found = new Set(pages);
    const grab = async (url, pattern, limit) => {
        await page.goto(BASE + url, { waitUntil: 'networkidle2' }).catch(() => {});
        const hrefs = await page.evaluate((src) => [...document.querySelectorAll('a[href]')].map((a) => new URL(a.href).pathname).filter((p) => new RegExp(src).test(p)), pattern);
        [...new Set(hrefs)].slice(0, limit).forEach((h) => found.add(h));
    };
    if (ROLE === 'admin') await grab('/admin', '^/admin(/|$)', 80);
    else if (ROLE !== 'guest') {
        await grab('/games', '^/games/[0-9a-f-]{36}$', 3);
        await grab('/tournaments', '^/tournaments/[0-9a-f-]{36}$', 3);
        await grab('/tools/crew-builder', '^/tools/crew-builder/(share|[0-9]+)', 3);
    }
    return [...found];
}

(async () => {
    fs.mkdirSync(OUT, { recursive: true });
    const browser = await puppeteer.launch({ executablePath: process.env.CHROME, headless: true, args: ['--no-sandbox'] });
    // Log in once on a throwaway page, then share the session cookies with every page we open.
    const setup = await newPage(browser, 'desktop');
    if (ROLE !== 'guest') await login(setup);
    const cookies = await setup.cookies();
    let pages = String(arg('pages', '/')).split(',').filter(Boolean);
    if (DISCOVER) pages = await discover(setup, pages);
    await setup.close();

    const rows = [];
    for (const vp of VIEWPORTS) {
        for (const p of pages) {
            const page = await newPage(browser, vp);
            await page.setCookie(...cookies.map(({ name, value, domain, path: cp }) => ({ name, value, domain, path: cp })));
            const errors = [];
            const bad = [];
            page.on('pageerror', (e) => errors.push(e.message.slice(0, 120)));
            page.on('response', (r) => { if (r.status() >= 500 || (r.status() >= 400 && !/storage|favicon|analytics/.test(r.url()))) bad.push(`${r.status()} ${new URL(r.url()).pathname}`); });
            const res = await page.goto(BASE + p, { waitUntil: 'networkidle2', timeout: 60000 }).catch((e) => ({ status: () => `ERR ${e.message.slice(0, 40)}` }));
            await sleep(1200);
            let m = {};
            if (axeSrc) await page.evaluate(axeSrc);
            m = await page.evaluate(async (hasAxe, detail) => {
                const ax = hasAxe ? await window.axe.run(document, { resultTypes: ['violations'] }) : { violations: [] };
                const tiny = [...document.querySelectorAll('body *')].filter((e) => [...e.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim().length > 2) && parseFloat(getComputedStyle(e).fontSize) < 11 && e.getBoundingClientRect().width > 0).length;
                return {
                    h1: document.querySelectorAll('h1').length,
                    overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
                    tiny,
                    axe: ax.violations.filter((v) => v.impact !== 'minor').map((v) => `${v.impact[0]}:${v.id}×${v.nodes.length}`),
                    nodes: detail
                        ? ax.violations.filter((v) => ['critical', 'serious'].includes(v.impact)).flatMap((v) => v.nodes.slice(0, 3).map((n) => `${v.id}: ${n.html.replace(/class="[^"]*"/g, '').replace(/\s+/g, ' ').slice(0, 200)} @ ${n.target.join(' ').slice(0, 80)}`))
                        : [],
                };
            }, !!axeSrc, DETAIL);
            if (DETAIL) m.nodes.forEach((n) => console.log('      ' + n));
            const file = `${ROLE}__${vp}__${slug(p)}.png`;
            await page.screenshot({ path: path.join(OUT, file), fullPage: false });
            const status = res && typeof res.status === 'function' ? res.status() : '?';
            rows.push({ vp, p, status, ...m, errors, bad: [...new Set(bad)].slice(0, 3), file });
            console.log(`${vp.padEnd(7)} ${String(status).padEnd(4)} ${p.padEnd(44)} h1=${m.h1} tiny=${m.tiny}${m.overflow ? ' H-OVERFLOW' : ''} ${m.axe.join(' ') || 'axe-clean'}${errors.length ? ' | JS ' + errors[0] : ''}${bad.length ? ' | ' + bad[0] : ''}`);
            await page.close();
        }
    }
    fs.writeFileSync(path.join(OUT, `${ROLE}-results.json`), JSON.stringify(rows, null, 1));
    await browser.close();
})().catch((e) => { console.error(e.message); process.exit(1); });
