#!/usr/bin/env node
/**
 * Type-checks the Vue/TS sources with vue-tsc and compares per-file error counts with scripts/typecheck-baseline.json,
 * so the ~900 findings that predate the check cannot grow while they are burned down.
 *
 *   npm run typecheck           fail if any file has more errors than its baseline (new files start at 0)
 *   npm run typecheck:update    rewrite the baseline to the current counts (run it after fixing errors, never to hide new ones)
 *
 * vue-tsc is installed under node_modules/.cache/typecheck and not into package.json on purpose: when it is resolvable from
 * the project, prettier-plugin-organize-imports starts reordering imports in every .vue file.
 */
import { spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const tool = join(root, 'node_modules/.cache/typecheck');
const baselinePath = join(root, 'scripts/typecheck-baseline.json');
const update = process.argv.includes('--update');

const VUE_TSC = '2.2.12';
if (!existsSync(join(tool, 'node_modules/vue-tsc/bin/vue-tsc.js'))) {
    mkdirSync(tool, { recursive: true });
    writeFileSync(join(tool, 'package.json'), '{"private":true}\n');
    const ts = JSON.parse(readFileSync(join(root, 'node_modules/typescript/package.json'), 'utf8')).version;
    console.log(`Installing vue-tsc@${VUE_TSC} and typescript@${ts} into node_modules/.cache/typecheck …`);
    const i = spawnSync('npm', ['i', '--no-audit', '--no-fund', '--silent', `vue-tsc@${VUE_TSC}`, `typescript@${ts}`], { cwd: tool, stdio: 'inherit' });
    if (i.status !== 0) process.exit(i.status ?? 1);
}

const run = spawnSync('node', [join(tool, 'node_modules/vue-tsc/bin/vue-tsc.js'), '--noEmit', '-p', 'tsconfig.json'], {
    cwd: root,
    encoding: 'utf8',
    maxBuffer: 256 * 1024 * 1024,
});
const output = `${run.stdout ?? ''}${run.stderr ?? ''}`;

const counts = {};
for (const m of output.matchAll(/^(resources\/[^()\n]+)\(\d+,\d+\): error TS\d+/gm)) counts[m[1]] = (counts[m[1]] ?? 0) + 1;
const total = Object.values(counts).reduce((a, b) => a + b, 0);

if (total === 0 && run.status !== 0) {
    console.error(output.slice(0, 2000));
    console.error('vue-tsc failed without reporting type errors.');
    process.exit(1);
}

const sorted = Object.fromEntries(Object.entries(counts).sort(([a], [b]) => a.localeCompare(b)));
if (update) {
    writeFileSync(baselinePath, `${JSON.stringify(sorted, null, 4)}\n`);
    console.log(`Baseline written: ${total} errors in ${Object.keys(sorted).length} files.`);
    process.exit(0);
}

const baseline = existsSync(baselinePath) ? JSON.parse(readFileSync(baselinePath, 'utf8')) : {};
const worse = Object.entries(counts).filter(([file, n]) => n > (baseline[file] ?? 0));
const better = Object.entries(baseline).filter(([file, n]) => (counts[file] ?? 0) < n);
const baseTotal = Object.values(baseline).reduce((a, b) => a + b, 0);

console.log(`vue-tsc: ${total} errors in ${Object.keys(counts).length} files (baseline ${baseTotal}).`);
if (better.length) console.log(`${better.length} file(s) improved — run \`npm run typecheck:update\` to lock the gain in.`);
if (worse.length) {
    console.error('\nNew type errors (file: now / baseline):');
    for (const [file, n] of worse) {
        console.error(`  ${file}: ${n} / ${baseline[file] ?? 0}`);
        for (const line of output.split('\n').filter((l) => l.startsWith(`${file}(`)).slice(0, 8)) console.error(`    ${line}`);
    }
    process.exit(1);
}
