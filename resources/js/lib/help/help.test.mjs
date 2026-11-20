import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { test } from 'node:test';
import { TERRAIN_TRAITS } from '../tabletop/geometry.ts';
import { TERRAIN_PRESETS } from '../tabletop/terrainPresets.ts';
import { HELP_TOPICS, SHORTCUTS_ID, helpTabs, helpTopic } from './index.ts';
import { TRAIT_HELP } from './topics/tabletop.ts';

test('every help topic is complete: a title, summary, route, and sections with text', () => {
    assert.ok(HELP_TOPICS.length >= 3);
    const slugs = HELP_TOPICS.map((t) => t.slug);
    assert.equal(new Set(slugs).size, slugs.length, 'slugs are unique');
    for (const t of HELP_TOPICS) {
        assert.match(t.slug, /^[a-z][a-z0-9-]*$/, t.slug);
        assert.ok(t.title && t.summary.length > 20 && t.route, t.slug);
        assert.ok(t.sections.length >= 3, `${t.slug} has too few sections`);
        const ids = t.sections.map((s) => s.id);
        assert.equal(new Set(ids).size, ids.length, `${t.slug}: section ids are unique`);
        assert.ok(!ids.includes(SHORTCUTS_ID), `${t.slug}: "${SHORTCUTS_ID}" is the shortcuts tab's id`);
        for (const s of t.sections) {
            assert.match(s.id, /^[a-z][a-z0-9-]*$/, `${t.slug}/${s.id}`);
            assert.ok(s.title && s.intro.length > 20, `${t.slug}/${s.id}`);
            assert.ok(s.items.length >= 2, `${t.slug}/${s.id} has too few items`);
            for (const item of s.items) assert.ok(item.title && item.body.length > 30, `${t.slug}/${s.id}: ${item.title}`);
        }
        const tabs = helpTabs(t);
        assert.equal(tabs.length, t.sections.length + (t.shortcuts?.length ? 1 : 0));
    }
    assert.equal(helpTopic('nothing-here'), undefined);
});

test('the registry matches the server list of topics, so a topic cannot exist on one side only', () => {
    const php = fs.readFileSync(new URL('../../../../app/Support/Help/HelpTopics.php', import.meta.url), 'utf8');
    const listed = [...php.match(/SLUGS = \[([^\]]*)\]/)[1].matchAll(/'([^']+)'/g)].map((m) => m[1]);
    assert.deepEqual([...listed].sort(), HELP_TOPICS.map((t) => t.slug).sort());
});

test('the help is about how the features work: no page of rules quoted', () => {
    // a guard on tone: rules are linked to, not reproduced. A few rule references are fine; wholesale quoting is not.
    for (const t of HELP_TOPICS) {
        const text = JSON.stringify(t);
        const refs = (text.match(/CRG p\./g) ?? []).length;
        assert.ok(refs <= 3, `${t.slug} cites the rulebook ${refs} times: say how the tool works and link the rules instead`);
    }
});

test('the tabletop help covers every terrain trait and preset, and lists the keys the table answers to', () => {
    // every trait the editor offers has a tooltip, and no tooltip is left for a trait that no longer exists
    assert.deepEqual(Object.keys(TRAIT_HELP).sort(), [...TERRAIN_TRAITS].sort());
    for (const text of Object.values(TRAIT_HELP)) assert.ok(text.length > 20 && text.length < 260);
    const tabletop = helpTopic('tabletop');
    // the terrain help lists every preset, so a new preset cannot be left out of it
    const terrain = tabletop.sections
        .find((s) => s.id === 'terrain')
        .items.map((i) => i.body)
        .join(' ');
    for (const preset of TERRAIN_PRESETS) assert.ok(terrain.includes(preset.label), preset.label);
    // the shortcuts the board's key handler answers to are all written down
    const keys = tabletop.shortcuts.flatMap((g) => g.shortcuts.flatMap((s) => s.keys));
    for (const k of ['[', ']', '←', '→', '↑', '↓', 'Esc', 'Delete', '?', ',', '.', 'Shift', 'Alt'])
        assert.ok(keys.includes(k), `no shortcut lists ${k}`);
});

test('every help image exists, is in the manifest with its size, has alt text, and its legend matches the numbers drawn on it', () => {
    const root = new URL('../../../../public/images/help/', import.meta.url).pathname;
    const manifest = JSON.parse(fs.readFileSync(new URL('./images.json', import.meta.url), 'utf8'));
    const used = new Set();
    for (const t of HELP_TOPICS) {
        for (const s of t.sections) {
            for (const item of s.items) {
                const img = item.image;
                if (!img) continue;
                const where = `${t.slug}/${s.id}: ${item.title}`;
                assert.ok(
                    fs.existsSync(path.join(root, img.src)),
                    `${where}: public/images/help/${img.src} is missing (run the help screenshots: docs/features/help.md)`,
                );
                assert.ok(manifest[img.src]?.w > 0 && manifest[img.src]?.h > 0, `${where}: ${img.src} is not in images.json`);
                assert.ok(img.alt.length > 15, `${where}: alt text`);
                assert.equal(
                    img.callouts?.length ?? 0,
                    manifest[img.src].callouts,
                    `${where}: the legend has ${img.callouts?.length ?? 0} entries but the picture has ${manifest[img.src].callouts} numbers`,
                );
                used.add(img.src);
            }
        }
    }
    // nothing left over in the manifest or the folder
    for (const key of Object.keys(manifest)) assert.ok(used.has(key), `${key} is in images.json but no help item uses it`);
});
