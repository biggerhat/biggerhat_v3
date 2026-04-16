<script setup lang="ts">
import EntityReferenceRenderer from '@/components/blog/EntityReferenceRenderer.vue';
import GameIcon from '@/components/GameIcon.vue';
import GameText from '@/components/GameText.vue';
defineProps<{
    nodes: Record<string, unknown>[];
}>();

const isEntityReference = (node: Record<string, unknown>) => node.type === 'entityReference';
const isGameIcon = (node: Record<string, unknown>) => node.type === 'gameIcon';
const isText = (node: Record<string, unknown>) => node.type === 'text';

const getMarks = (node: Record<string, unknown>): string[] => {
    return ((node.marks as Array<{ type: string }>) ?? []).map((m) => m.type);
};

const getLinkHref = (node: Record<string, unknown>) => {
    const marks = (node.marks as Array<{ type: string; attrs?: { href?: string } }>) ?? [];
    const linkMark = marks.find((m) => m.type === 'link');
    return linkMark?.attrs?.href ?? '#';
};

/**
 * Build a CSS class string from all applicable marks.
 * Bold/italic/link/code are handled via HTML elements;
 * underline and strikethrough are handled via CSS classes.
 */
const textClass = (node: Record<string, unknown>) => {
    const marks = getMarks(node);
    const classes: string[] = [];
    if (marks.includes('bold')) classes.push('font-bold');
    if (marks.includes('italic')) classes.push('italic');
    if (marks.includes('underline')) classes.push('underline');
    if (marks.includes('strike')) classes.push('line-through');
    if (marks.includes('highlight')) classes.push('bg-yellow-200 dark:bg-yellow-800/60 rounded px-0.5');
    return classes.join(' ');
};

const isCode = (node: Record<string, unknown>) => getMarks(node).includes('code');
const isLink = (node: Record<string, unknown>) => getMarks(node).includes('link');
</script>

<template>
    <template v-for="(child, cidx) in nodes" :key="cidx">
        <EntityReferenceRenderer v-if="isEntityReference(child)" :attrs="child.attrs as Record<string, unknown>" />
        <GameIcon
            v-else-if="isGameIcon(child)"
            :type="(child.attrs as Record<string, string>).iconType"
            class-name="h-5 mx-0.5 inline-block align-text-bottom"
        />
        <template v-else-if="isText(child)">
            <a v-if="isLink(child)" :href="getLinkHref(child)" target="_blank" rel="noopener" :class="textClass(child)">
                <GameText :text="child.text as string" />
            </a>
            <code v-else-if="isCode(child)" :class="['rounded bg-muted px-1.5 py-0.5 font-mono text-[0.875em]', textClass(child)]">{{
                child.text
            }}</code>
            <span v-else-if="textClass(child)" :class="textClass(child)"><GameText :text="child.text as string" /></span>
            <GameText v-else :text="child.text as string" />
        </template>
    </template>
</template>
