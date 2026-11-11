/* eslint-disable @typescript-eslint/no-require-imports -- standalone CommonJS script, not part of the app bundle */
// Shared helpers for the tournament browser flows. See docs/ui-audit.md ("Tournament flows").
const fs = require('fs');
const path = require('path');
const puppeteer = require(path.resolve('node_modules/puppeteer-core'));
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const SHOTS = process.env.SHOTS || path.resolve('storage/app/ui-audit/tournaments');
fs.mkdirSync(SHOTS, { recursive: true });
const ids = JSON.parse(fs.readFileSync(process.env.IDS, 'utf8'));
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const results = [];

async function launch() {
    return puppeteer.launch({ executablePath: process.env.CHROME, headless: 'shell', args: ['--no-sandbox', '--disable-gpu'] });
}

// One signed-in (or guest) browser context that records console / network trouble per step.
async function session(browser, email, vp = { width: 1280, height: 900 }) {
    const ctx = await browser.createBrowserContext();
    const page = await ctx.newPage();
    await page.setViewport(vp);
    await page.setCookie({ name: 'cookie_consent', value: 'accepted', url: BASE });
    const problems = [];
    page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`));
    page.on('console', (m) => {
        const t = m.text();
        if (m.type() === 'error' && !/favicon|Failed to load resource.*(404|419)|WebSocket|ws:|reverb|Echo/i.test(t))
            problems.push(`console.error: ${t.slice(0, 220)}`);
    });
    page.on('response', (r) => {
        if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.request().method()} ${r.url().replace(BASE, '')}`);
    });
    const s = { page, problems, email };
    if (email) {
        await page.goto(`${BASE}/login`, { waitUntil: 'networkidle2' });
        await page.type('input[type=email], input[name=email]', email);
        await page.type('input[type=password]', 'password');
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}), page.keyboard.press('Enter')]);
        if (page.url().includes('/login')) throw new Error(`login failed for ${email}`);
    }
    return s;
}

async function step(name, s, fn) {
    s.problems.length = 0;
    let status = 'PASS',
        detail = '';
    try {
        await fn();
    } catch (e) {
        status = 'FAIL';
        detail = e.message.split('\n')[0];
        try {
            await s.page.screenshot({ path: path.join(SHOTS, 'FAIL-' + name.replace(/[^a-z0-9]+/gi, '_') + '.png') });
        } catch {}
    }
    if (s.problems.length) {
        detail += (detail ? ' | ' : '') + s.problems.join(' | ');
        if (status === 'PASS') status = 'WARN';
    }
    results.push({ name, status, detail });
    console.log(`${status.padEnd(4)} ${name}${detail ? '\n       ' + detail : ''}`);
}

const shot = async (s, name) => s.page.screenshot({ path: path.join(SHOTS, `${name}.png`) });
const text = (s) => s.page.evaluate(() => document.body.innerText);
const visit = async (s, p) => {
    await s.page.goto(BASE + p, { waitUntil: 'networkidle2' });
    await sleep(300);
};
// poll: the dev server is single-threaded and Inertia visits finish asynchronously
const expectText = async (s, re, msg, timeout = 9000) => {
    const end = Date.now() + timeout;
    let t = '';
    while (Date.now() < end) {
        t = await text(s);
        if (re.test(t)) return;
        await sleep(250);
    }
    throw new Error(msg || `expected page to match ${re}`);
};

// click the first visible element (button/a/label) whose text matches — with a real mouse press, because Reka tabs/menus react to pointer events
async function clickText(s, re, sel = 'button, a, [role=tab], [role=menuitem], label') {
    const ok = await s.page.evaluate(
        (src, flags, sel) => {
            document.querySelectorAll('[data-h-click]').forEach((e) => e.removeAttribute('data-h-click'));
            const rx = new RegExp(src, flags);
            const el = [...document.querySelectorAll(sel)].find((e) => e.offsetParent !== null && rx.test(e.innerText.trim()) && !e.disabled);
            if (!el) return false;
            el.scrollIntoView({ block: 'center' });
            el.setAttribute('data-h-click', '1');
            return true;
        },
        re.source,
        re.flags,
        sel,
    );
    if (!ok) throw new Error(`no clickable element matching ${re}`);
    await s.page.click('[data-h-click]');
    await sleep(400);
}

// Reka select: open the nth combobox inside an optional scope, then choose an option by text
async function pickSelect(s, nth, optionRe, scopeSel = 'body') {
    const idx = await s.page.evaluate(
        (scopeSel, nth) => {
            const scope = document.querySelector(scopeSel);
            const boxes = [...scope.querySelectorAll('button[role=combobox]')].filter((e) => e.offsetParent !== null);
            const el = boxes[nth];
            if (!el) return -1;
            document.querySelectorAll('[data-h-click]').forEach((e) => e.removeAttribute('data-h-click'));
            el.scrollIntoView({ block: 'center' });
            el.setAttribute('data-h-click', '1');
            return nth;
        },
        scopeSel,
        nth,
    );
    if (idx < 0) throw new Error(`no combobox #${nth} in ${scopeSel}`);
    await s.page.click('[data-h-click]');
    await sleep(300);
    const ok = await s.page.evaluate(
        (src, flags) => {
            document.querySelectorAll('[data-h-click]').forEach((e) => e.removeAttribute('data-h-click'));
            const rx = new RegExp(src, flags);
            const el = [...document.querySelectorAll('[role=option]')].find((e) => rx.test(e.innerText.trim()));
            if (!el) return false;
            el.setAttribute('data-h-click', '1');
            return true;
        },
        optionRe.source,
        optionRe.flags,
    );
    if (!ok) throw new Error(`no option matching ${optionRe}`);
    await s.page.click('[data-h-click]');
    await sleep(300);
}

// Reka select identified by what it currently shows (e.g. /Malifaux/ for the ruleset picker)
async function pickByTrigger(s, triggerRe, optionRe) {
    const ok = await s.page.evaluate(
        (src, flags) => {
            document.querySelectorAll('[data-h-click]').forEach((e) => e.removeAttribute('data-h-click'));
            const rx = new RegExp(src, flags);
            const el = [...document.querySelectorAll('button[role=combobox]')].find((e) => e.offsetParent !== null && rx.test(e.innerText.trim()));
            if (!el) return false;
            el.scrollIntoView({ block: 'center' });
            el.setAttribute('data-h-click', '1');
            return true;
        },
        triggerRe.source,
        triggerRe.flags,
    );
    if (!ok) throw new Error(`no select currently showing ${triggerRe}`);
    await s.page.click('[data-h-click]');
    await sleep(300);
    const found = await s.page.evaluate(
        (src, flags) => {
            document.querySelectorAll('[data-h-click]').forEach((e) => e.removeAttribute('data-h-click'));
            const rx = new RegExp(src, flags);
            const el = [...document.querySelectorAll('[role=option]')].find((e) => rx.test(e.innerText.trim()));
            if (!el) return false;
            el.setAttribute('data-h-click', '1');
            return true;
        },
        optionRe.source,
        optionRe.flags,
    );
    if (!found) throw new Error(`no option matching ${optionRe}`);
    await s.page.click('[data-h-click]');
    await sleep(300);
}

module.exports = { pickByTrigger, BASE, ids, sleep, results, launch, session, step, shot, text, visit, expectText, clickText, pickSelect };
