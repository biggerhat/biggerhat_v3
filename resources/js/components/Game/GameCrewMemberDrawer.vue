<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import { Button } from '@/components/ui/button';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Maximize2, X } from 'lucide-vue-next';
import { computed } from 'vue';

interface PreviewMember {
    id: number;
    display_name: string;
    front_image: string | null;
    back_image: string | null;
}

interface Miniature {
    id: number;
    display_name: string;
    front_image: string;
}

const props = defineProps<{
    open: boolean;
    member: PreviewMember | null;
    miniatures: Miniature[];
    /** Whether to show the sculpt selector. Parent computes this from isSolo / isObserver / ownership. */
    canChangeSculpt: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'sculpt-change', miniatureId: string): void;
    (e: 'open-fullscreen', src: string): void;
}>();

// Current sculpt is the miniature whose front_image matches the member's
// current front_image. Fall back to the first miniature if no match.
const currentSculptId = computed(() => {
    if (!props.member) return '';
    const match = props.miniatures.find((m) => m.front_image === props.member?.front_image);
    return String(match?.id ?? props.miniatures[0]?.id ?? '');
});
</script>

<template>
    <Drawer :open="open" @update:open="emit('update:open', $event)">
        <DrawerContent>
            <button
                class="absolute right-3 top-3 z-10 rounded-full bg-muted p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                aria-label="Close"
                @click="emit('update:open', false)"
            >
                <X class="size-4" />
            </button>
            <div v-if="member" class="mx-auto w-full max-w-md sm:max-w-3xl">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ member.display_name }}</DrawerTitle>
                    <div v-if="canChangeSculpt && miniatures.length > 1" class="mt-2 flex justify-center">
                        <Select :model-value="currentSculptId" @update:model-value="(v) => emit('sculpt-change', v as string)">
                            <SelectTrigger class="w-auto gap-2 text-xs">
                                <SelectValue placeholder="Select sculpt" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="mini in miniatures" :key="mini.id" :value="String(mini.id)">
                                    {{ mini.display_name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </DrawerHeader>
                <div v-if="member.front_image" class="px-4 pb-2">
                    <!-- Desktop: side-by-side combo view -->
                    <div class="hidden items-start justify-center gap-2 sm:flex">
                        <div class="relative">
                            <img
                                :src="'/storage/' + member.front_image"
                                :alt="member.display_name + ' front'"
                                class="max-h-[65dvh] w-auto rounded-lg object-contain"
                            />
                            <button
                                class="absolute bottom-2 right-2 rounded-full bg-black/40 p-1.5 text-white/70 backdrop-blur-sm transition-all hover:bg-black/70 hover:text-white"
                                title="View fullscreen"
                                aria-label="View fullscreen"
                                @click="emit('open-fullscreen', '/storage/' + member.front_image)"
                            >
                                <Maximize2 class="size-3.5" />
                            </button>
                        </div>
                        <div v-if="member.back_image" class="relative">
                            <img
                                :src="'/storage/' + member.back_image"
                                :alt="member.display_name + ' back'"
                                class="max-h-[65dvh] w-auto rounded-lg object-contain"
                            />
                            <button
                                class="absolute bottom-2 right-2 rounded-full bg-black/40 p-1.5 text-white/70 backdrop-blur-sm transition-all hover:bg-black/70 hover:text-white"
                                title="View fullscreen"
                                aria-label="View fullscreen"
                                @click="emit('open-fullscreen', '/storage/' + member.back_image)"
                            >
                                <Maximize2 class="size-3.5" />
                            </button>
                        </div>
                    </div>
                    <!-- Mobile: flip card -->
                    <div class="flex min-h-0 flex-1 items-start justify-center sm:hidden [&_img]:max-h-[65dvh] [&_img]:w-auto [&_img]:object-contain">
                        <CharacterCardView
                            :key="member.front_image"
                            :miniature="{
                                id: member.id,
                                display_name: member.display_name,
                                slug: '',
                                front_image: member.front_image,
                                back_image: member.back_image,
                            }"
                            :show-link="false"
                            :show-collection="false"
                        />
                    </div>
                </div>
                <div v-else class="px-4 py-8 pb-2 text-center text-sm text-muted-foreground">No card image available</div>
                <DrawerFooter class="shrink-0 pt-2">
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>
</template>
