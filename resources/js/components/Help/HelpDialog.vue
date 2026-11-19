<script setup lang="ts">
import HelpContent from '@/components/Help/HelpContent.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { helpTabs, helpTopic } from '@/lib/help';
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

/**
 * A feature's help in a dialog, one section at a time, opened at the section that matters where help was asked for (terrain from the terrain
 * editor, shortcuts from `?`). Needs only the topic's slug; the text is in lib/help/topics.
 */
const props = defineProps<{ open: boolean; topic: string; section?: string }>();
const emit = defineEmits<{ 'update:open': [open: boolean] }>();

const help = computed(() => helpTopic(props.topic));
const tabs = computed(() => (help.value ? helpTabs(help.value) : []));
const current = ref(props.section ?? tabs.value[0]?.id ?? '');
watch(
    () => props.open,
    (open) => {
        if (open) current.value = tabs.value.some((t) => t.id === props.section) ? (props.section as string) : (tabs.value[0]?.id ?? '');
    },
);
</script>

<template>
    <Dialog v-if="help" :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[85vh] max-w-3xl overflow-hidden p-0">
            <div class="flex max-h-[85vh] flex-col" data-testid="help-dialog">
                <DialogHeader class="border-b p-4 pb-3">
                    <DialogTitle>{{ help.title }} help</DialogTitle>
                    <DialogDescription>{{ help.summary }}</DialogDescription>
                </DialogHeader>
                <div class="flex min-h-0 flex-1 flex-col sm:flex-row">
                    <nav class="shrink-0 border-b p-2 sm:w-48 sm:border-b-0 sm:border-r" aria-label="Help sections">
                        <ul class="flex gap-1 overflow-x-auto sm:flex-col sm:overflow-visible">
                            <li v-for="t in tabs" :key="t.id" class="shrink-0">
                                <button
                                    type="button"
                                    class="min-h-8 w-full rounded-md px-2.5 py-1 text-left text-sm hover:bg-muted"
                                    :class="current === t.id ? 'bg-muted font-semibold' : ''"
                                    :aria-current="current === t.id ? 'true' : undefined"
                                    :data-testid="`help-tab-${t.id}`"
                                    @click="current = t.id"
                                >
                                    {{ t.title }}
                                </button>
                            </li>
                        </ul>
                    </nav>
                    <div class="min-h-0 flex-1 overflow-y-auto p-4" tabindex="0" aria-label="Help text" data-testid="help-scroll">
                        <HelpContent :topic="help" :section="current" :heading-level="3" />
                        <p class="mt-6 border-t pt-3 text-xs text-muted-foreground">
                            <Link :href="route('help.show', help.slug)" class="underline">Open all of this help as a page</Link> to read it in full,
                            print it or share it. <Link :href="route('help.index')" class="underline">Help for other features</Link>.
                        </p>
                    </div>
                </div>
                <div class="flex justify-end border-t p-3">
                    <Button variant="outline" size="sm" data-testid="help-close" @click="emit('update:open', false)">Close</Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
