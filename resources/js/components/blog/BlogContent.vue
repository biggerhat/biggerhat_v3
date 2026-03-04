<script setup lang="ts">
import EntityEmbedRenderer from '@/components/blog/EntityEmbedRenderer.vue';
import EntityReferenceRenderer from '@/components/blog/EntityReferenceRenderer.vue';
import GameIcon from '@/components/GameIcon.vue';
import GameText from '@/components/GameText.vue';

defineProps<{
    content: Record<string, unknown> | null;
}>();

const isEntityEmbed = (node: Record<string, unknown>) => node.type === 'entityEmbed';
const isEntityReference = (node: Record<string, unknown>) => node.type === 'entityReference';
const isGameIcon = (node: Record<string, unknown>) => node.type === 'gameIcon';
const isText = (node: Record<string, unknown>) => node.type === 'text';

const getHeadingTag = (level: number) => {
    const tags: Record<number, string> = { 1: 'h1', 2: 'h2', 3: 'h3', 4: 'h4', 5: 'h5', 6: 'h6' };
    return tags[level] ?? 'h3';
};

const hasMarks = (node: Record<string, unknown>, mark: string) => {
    const marks = (node.marks as Array<{ type: string }>) ?? [];
    return marks.some((m) => m.type === mark);
};

const getLinkHref = (node: Record<string, unknown>) => {
    const marks = (node.marks as Array<{ type: string; attrs?: { href?: string } }>) ?? [];
    const linkMark = marks.find((m) => m.type === 'link');
    return linkMark?.attrs?.href ?? '#';
};
</script>

<template>
    <div v-if="content && content.content" class="prose prose-lg dark:prose-invert max-w-none">
        <template v-for="(node, idx) in content.content as Record<string, unknown>[]" :key="idx">
            <!-- Paragraph -->
            <p v-if="node.type === 'paragraph' && node.content">
                <template v-for="(child, cidx) in node.content as Record<string, unknown>[]" :key="cidx">
                    <EntityReferenceRenderer v-if="isEntityReference(child)" :attrs="child.attrs as Record<string, unknown>" />
                    <GameIcon
                        v-else-if="isGameIcon(child)"
                        :type="(child.attrs as Record<string, string>).iconType"
                        class-name="h-5 inline-block align-text-bottom"
                    />
                    <template v-else-if="isText(child)">
                        <a v-if="hasMarks(child, 'link')" :href="getLinkHref(child)" target="_blank" rel="noopener">
                            <GameText :text="child.text as string" />
                        </a>
                        <strong v-else-if="hasMarks(child, 'bold') && hasMarks(child, 'italic')"
                            ><em><GameText :text="child.text as string" /></em
                        ></strong>
                        <strong v-else-if="hasMarks(child, 'bold')"><GameText :text="child.text as string" /></strong>
                        <em v-else-if="hasMarks(child, 'italic')"><GameText :text="child.text as string" /></em>
                        <code v-else-if="hasMarks(child, 'code')">{{ child.text }}</code>
                        <GameText v-else :text="child.text as string" />
                    </template>
                </template>
            </p>
            <p v-else-if="node.type === 'paragraph' && !node.content" />

            <!-- Headings -->
            <component v-else-if="node.type === 'heading'" :is="getHeadingTag((node.attrs as Record<string, number>).level)">
                <template v-for="(child, cidx) in (node.content as Record<string, unknown>[]) ?? []" :key="cidx">
                    <GameText v-if="isText(child)" :text="child.text as string" />
                    <EntityReferenceRenderer v-else-if="isEntityReference(child)" :attrs="child.attrs as Record<string, unknown>" />
                </template>
            </component>

            <!-- Bullet List -->
            <ul v-else-if="node.type === 'bulletList'">
                <li v-for="(item, iidx) in (node.content as Record<string, unknown>[]) ?? []" :key="iidx">
                    <template v-for="(para, pidx) in (item.content as Record<string, unknown>[]) ?? []" :key="pidx">
                        <template v-for="(child, cidx) in (para.content as Record<string, unknown>[]) ?? []" :key="cidx">
                            <EntityReferenceRenderer v-if="isEntityReference(child)" :attrs="child.attrs as Record<string, unknown>" />
                            <GameIcon
                                v-else-if="isGameIcon(child)"
                                :type="(child.attrs as Record<string, string>).iconType"
                                class-name="h-5 inline-block align-text-bottom"
                            />
                            <GameText v-else-if="isText(child)" :text="child.text as string" />
                        </template>
                    </template>
                </li>
            </ul>

            <!-- Ordered List -->
            <ol v-else-if="node.type === 'orderedList'">
                <li v-for="(item, iidx) in (node.content as Record<string, unknown>[]) ?? []" :key="iidx">
                    <template v-for="(para, pidx) in (item.content as Record<string, unknown>[]) ?? []" :key="pidx">
                        <template v-for="(child, cidx) in (para.content as Record<string, unknown>[]) ?? []" :key="cidx">
                            <EntityReferenceRenderer v-if="isEntityReference(child)" :attrs="child.attrs as Record<string, unknown>" />
                            <GameIcon
                                v-else-if="isGameIcon(child)"
                                :type="(child.attrs as Record<string, string>).iconType"
                                class-name="h-5 inline-block align-text-bottom"
                            />
                            <GameText v-else-if="isText(child)" :text="child.text as string" />
                        </template>
                    </template>
                </li>
            </ol>

            <!-- Blockquote -->
            <blockquote v-else-if="node.type === 'blockquote'">
                <template v-for="(para, pidx) in (node.content as Record<string, unknown>[]) ?? []" :key="pidx">
                    <p v-if="para.type === 'paragraph'">
                        <template v-for="(child, cidx) in (para.content as Record<string, unknown>[]) ?? []" :key="cidx">
                            <GameText v-if="isText(child)" :text="child.text as string" />
                        </template>
                    </p>
                </template>
            </blockquote>

            <!-- Code Block -->
            <pre
                v-else-if="node.type === 'codeBlock'"
            ><code><template v-for="(child, cidx) in ((node.content as Record<string, unknown>[]) ?? [])" :key="cidx">{{ child.text }}</template></code></pre>

            <!-- Image -->
            <figure v-else-if="node.type === 'image'">
                <img :src="(node.attrs as Record<string, string>).src" :alt="(node.attrs as Record<string, string>).alt ?? ''" class="rounded-lg" />
            </figure>

            <!-- Entity Embed -->
            <EntityEmbedRenderer v-else-if="isEntityEmbed(node)" :attrs="node.attrs as Record<string, unknown>" />

            <!-- Horizontal Rule -->
            <hr v-else-if="node.type === 'horizontalRule'" />
        </template>
    </div>
</template>
