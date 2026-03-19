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
</script>

<template>
    <div v-if="content && content.content" class="prose prose-lg dark:prose-invert max-w-none">
        <template v-for="(node, idx) in content.content as Record<string, unknown>[]" :key="idx">
            <!-- Paragraph -->
            <p v-if="node.type === 'paragraph' && node.content">
                <InlineContent :nodes="node.content as Record<string, unknown>[]" />
            </p>
            <p v-else-if="node.type === 'paragraph' && !node.content" />

            <!-- Headings -->
            <component v-else-if="node.type === 'heading'" :is="getHeadingTag((node.attrs as Record<string, number>).level)">
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
            <pre
                v-else-if="node.type === 'codeBlock'"
            ><code><template v-for="(child, cidx) in ((node.content as Record<string, unknown>[]) ?? [])" :key="cidx">{{ child.text }}</template></code></pre>

            <!-- Image -->
            <figure v-else-if="node.type === 'image'">
                <img :src="(node.attrs as Record<string, string>).src" :alt="(node.attrs as Record<string, string>).alt ?? ''" class="rounded-lg" loading="lazy" decoding="async" />
            </figure>

            <!-- Entity Embed -->
            <EntityEmbedRenderer v-else-if="isEntityEmbed(node)" :attrs="node.attrs as Record<string, unknown>" />

            <!-- Horizontal Rule -->
            <hr v-else-if="node.type === 'horizontalRule'" />
        </template>
    </div>
</template>
