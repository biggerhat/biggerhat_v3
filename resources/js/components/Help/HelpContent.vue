<script setup lang="ts">
import { SHORTCUTS_ID, type HelpImage, type HelpTopic } from '@/lib/help';
import imageSizes from '@/lib/help/images.json';
import { computed } from 'vue';

/**
 * One help topic's text: every section, or just `section`. Used by the help dialog and the help page, so there is one rendering of it.
 * `headingLevel` is the level of the section titles; the shortcut group titles go one below, so heading levels never skip.
 */
const props = defineProps<{ topic: HelpTopic; section?: string; headingLevel?: 2 | 3 }>();

const sections = computed(() => (props.section ? props.topic.sections.filter((s) => s.id === props.section) : props.topic.sections));
const showShortcuts = computed(() => !!props.topic.shortcuts?.length && (!props.section || props.section === SHORTCUTS_ID));
/** Sizes of the screenshots (made with them), so a page does not jump as they load. */
const sizeOf = (image: HelpImage) => (imageSizes as Record<string, { w: number; h: number }>)[image.src];
const Heading = computed(() => `h${props.headingLevel ?? 3}`);
const SubHeading = computed(() => `h${(props.headingLevel ?? 3) + 1}`);
</script>

<template>
    <div class="space-y-6 text-sm" data-testid="help-content">
        <section v-for="s in sections" :id="`help-${s.id}`" :key="s.id" class="space-y-3" :data-testid="`help-${s.id}`">
            <component :is="Heading" class="text-base font-semibold">{{ s.title }}</component>
            <p class="text-muted-foreground">{{ s.intro }}</p>
            <dl class="space-y-3">
                <div v-for="item in s.items" :key="item.title">
                    <dt class="font-medium">{{ item.title }}</dt>
                    <dd class="text-muted-foreground">
                        {{ item.body }}
                        <figure v-if="item.image" class="mt-2 space-y-1.5" :data-testid="`help-image-${item.image.src.replace(/[^a-z0-9]+/gi, '-')}`">
                            <img
                                :src="`/images/help/${item.image.src}`"
                                :alt="item.image.alt"
                                :width="sizeOf(item.image)?.w"
                                :height="sizeOf(item.image)?.h"
                                loading="lazy"
                                decoding="async"
                                class="h-auto max-w-full rounded-md border"
                            />
                            <figcaption v-if="item.image.callouts?.length">
                                <ol class="list-none space-y-0.5 text-xs">
                                    <li v-for="(c, i) in item.image.callouts" :key="i" class="flex gap-2">
                                        <span
                                            class="mt-px inline-flex size-4 shrink-0 items-center justify-center rounded-full bg-amber-500 text-2xs font-bold text-black"
                                            aria-hidden="true"
                                            >{{ i + 1 }}</span
                                        >
                                        <span
                                            ><span class="sr-only">Number {{ i + 1 }}: </span>{{ c }}</span
                                        >
                                    </li>
                                </ol>
                            </figcaption>
                        </figure>
                    </dd>
                </div>
            </dl>
        </section>

        <section v-if="showShortcuts" :id="`help-${SHORTCUTS_ID}`" class="space-y-4" :data-testid="`help-${SHORTCUTS_ID}`">
            <component :is="Heading" class="text-base font-semibold">Keyboard and pointer shortcuts</component>
            <div v-for="g in topic.shortcuts" :key="g.title" class="space-y-1.5">
                <component :is="SubHeading" class="font-medium">{{ g.title }}</component>
                <p v-if="g.note" class="text-muted-foreground">{{ g.note }}</p>
                <table class="w-full border-collapse">
                    <caption class="sr-only">
                        {{
                            g.title
                        }}
                    </caption>
                    <thead class="sr-only">
                        <tr>
                            <th scope="col">Keys</th>
                            <th scope="col">What they do</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="sc in g.shortcuts" :key="sc.keys.join('+') + sc.does" class="border-t align-top">
                            <th scope="row" class="w-40 py-1.5 pr-3 text-left font-normal">
                                <kbd
                                    v-for="k in sc.keys"
                                    :key="k"
                                    class="mr-1 inline-block rounded border bg-muted px-1.5 py-0.5 font-mono text-xs"
                                    >{{ k }}</kbd
                                >
                            </th>
                            <td class="py-1.5 text-muted-foreground">{{ sc.does }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
