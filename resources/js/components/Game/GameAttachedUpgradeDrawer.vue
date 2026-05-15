<script setup lang="ts">
import BonanzaCardImage from '@/components/Bonanza/BonanzaCardImage.vue';
import UpgradeFlipCard from '@/components/UpgradeFlipCard.vue';
import { Button } from '@/components/ui/button';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { X } from 'lucide-vue-next';

interface UpgradePreview {
    name: string;
    front_image: string;
    back_image: string | null;
    loot_card_id?: number;
    loot_side?: 'a' | 'b';
}

defineProps<{
    open: boolean;
    upgrade: UpgradePreview | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();
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
            <div v-if="upgrade" class="mx-auto w-full max-w-md">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="flex items-center justify-center gap-2 text-center">
                        <span
                            v-if="upgrade.loot_side"
                            class="inline-flex h-5 min-w-5 items-center justify-center rounded bg-amber-400 px-1 text-xs font-bold uppercase text-black"
                        >
                            {{ upgrade.loot_side.toUpperCase() }}
                        </span>
                        <span>{{ upgrade.name }}</span>
                    </DrawerTitle>
                    <div class="mt-1 text-center text-xs text-muted-foreground">
                        {{ upgrade.loot_card_id ? 'Loot Card' : 'Crew Card' }}
                    </div>
                </DrawerHeader>
                <div
                    v-if="upgrade.loot_card_id"
                    class="flex min-h-0 flex-1 items-start justify-center px-4 pb-2 [&_img]:max-h-[65dvh] [&_img]:w-auto [&_img]:object-contain"
                >
                    <BonanzaCardImage :image="upgrade.front_image" :name="upgrade.name" />
                </div>
                <div
                    v-else
                    class="flex min-h-0 flex-1 items-start justify-center px-4 pb-2 [&_img]:max-h-[65dvh] [&_img]:w-auto [&_img]:object-contain"
                >
                    <UpgradeFlipCard
                        :front-image="upgrade.front_image"
                        :back-image="upgrade.back_image"
                        :alt-text="upgrade.name"
                        :show-link="false"
                    />
                </div>
                <DrawerFooter class="shrink-0 pt-2">
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>
</template>
