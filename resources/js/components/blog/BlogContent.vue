<script setup lang="ts">
import EntityEmbedRenderer from '@/components/blog/EntityEmbedRenderer.vue';
import InlineContent from '@/components/blog/InlineContent.vue';

defineProps<{
    content: Record<string, unknown> | null;
}>();

const isEntityEmbed = (node: Record<string, unknown>) => node.type === 'entityEmbed';

const getHeadingTag = (level: number) => {
    const tags: Record<number, string> = { 1: 'h1', 2: 'h2', 3: 'h3', 4: 'h4', 5: 'h5', 6: 'h6' };
    return tags[level] ?? 'h3';
};

const alignStyle = (node: Record<string, unknown>) => {
    const align = (node.attrs as Record<string, string> | null)?.textAlign;
    return align && align !== 'left' ? { textAlign: align } : undefined;
};
</script>

<template>
    <div v-if="content && content.content" class="prose prose-lg dark:prose-invert max-w-none">
        <template v-for="(node, idx) in content.content as Record<string, unknown>[]" :key="idx">
            <!-- Paragraph -->
            <p v-if="node.type === 'paragraph' && node.content" :style="alignStyle(node)">
                <InlineContent :nodes="node.content as Record<string, unknown>[]" />
            </p>
            <p v-else-if="node.type === 'paragraph' && !node.content" />

            <!-- Headings -->
            <component v-else-if="node.type === 'heading'" :is="getHeadingTag((node.attrs as Record<string, number>).level)" :style="alignStyle(node)">
                <InlineContent :nodes="(node.content as Record<string, unknown>[]) ?? []" />
            </component>

            <!-- Bullet List -->
            <ul v-else-if="node.type === 'bulletList'">
                <li v-for="(item, iidx) in (node.content as Record<string, unknown>[]) ?? []" :key="iidx">
                    <template v-for="(para, pidx) in (item.content as Record<string, unknown>[]) ?? []" :key="pidx">
                        <InlineContent :nodes="(para.content as Record<string, unknown>[]) ?? []" />
                    </template>
                </li>
            </ul>

            <!-- Ordered List -->
            <ol v-else-if="node.type === 'orderedList'">
                <li v-for="(item, iidx) in (node.content as Record<string, unknown>[]) ?? []" :key="iidx">
                    <template v-for="(para, pidx) in (item.content as Record<string, unknown>[]) ?? []" :key="pidx">
                        <InlineContent :nodes="(para.content as Record<string, unknown>[]) ?? []" />
                    </template>
                </li>
            </ol>

            <!-- Blockquote -->
            <blockquote v-else-if="node.type === 'blockquote'">
                <template v-for="(para, pidx) in (node.content as Record<string, unknown>[]) ?? []" :key="pidx">
                    <p v-if="para.type === 'paragraph'">
                        <InlineContent :nodes="(para.content as Record<string, unknown>[]) ?? []" />
                    </p>
                </template>
            </blockquote>

            <!-- Code Block -->
            <div v-else-if="node.type === 'codeBlock'" class="not-prose my-4 overflow-x-auto rounded-lg bg-muted">
                <pre class="p-4 text-sm leading-relaxed"><code class="font-mono"><template v-for="(child, cidx) in ((node.content as Record<string, unknown>[]) ?? [])" :key="cidx">{{ child.text }}</template></code></pre>
            </div>

            <!-- Image -->
            <figure v-else-if="node.type === 'image'" class="my-6">
                <img :src="(node.attrs as Record<string, string>).src" :alt="(node.attrs as Record<string, string>).alt ?? ''" class="w-full rounded-lg" loading="lazy" decoding="async" />
            </figure>

            <!-- Entity Embed -->
            <EntityEmbedRenderer v-else-if="isEntityEmbed(node)" :attrs="node.attrs as Record<string, unknown>" />

            <!-- Table -->
            <div v-else-if="node.type === 'table'" class="not-prose my-6 overflow-x-auto">
                <table class="w-full border-collapse text-xs sm:text-sm">
                    <template v-for="(row, ridx) in (node.content as Record<string, unknown>[]) ?? []" :key="ridx">
                        <tr class="border-b border-border last:border-b-0">
                            <template v-for="(cell, cidx) in (row.content as Record<string, unknown>[]) ?? []" :key="cidx">
                                <component
                                    :is="cell.type === 'tableHeader' ? 'th' : 'td'"
                                    class="px-2.5 py-2 text-left align-top sm:px-4 sm:py-3"
                                    :class="cell.type === 'tableHeader' ? 'bg-muted/50 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground sm:text-xs' : ''"
                                >
                                    <template v-for="(child, pidx) in (cell.content as Record<string, unknown>[]) ?? []" :key="pidx">
                                        <p v-if="child.type === 'paragraph'" :style="alignStyle(child)">
                                            <InlineContent :nodes="(child.content as Record<string, unknown>[]) ?? []" />
                                        </p>
                                        <EntityEmbedRenderer v-else-if="isEntityEmbed(child)" :attrs="child.attrs as Record<string, unknown>" />
                                    </template>
                                </component>
                            </template>
                        </tr>
                    </template>
                </table>
            </div>

            <!-- Horizontal Rule -->
            <hr v-else-if="node.type === 'horizontalRule'" class="my-4" />
        </template>
    </div>
</template>
