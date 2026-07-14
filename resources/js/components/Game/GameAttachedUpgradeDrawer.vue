<script setup lang="ts">
import AbilityCard from '@/components/AbilityCard.vue';
import ActionCard from '@/components/ActionCard.vue';
import BonanzaCardImage from '@/components/Bonanza/BonanzaCardImage.vue';
import UpgradeFlipCard from '@/components/UpgradeFlipCard.vue';
import { Button } from '@/components/ui/button';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { X } from 'lucide-vue-next';

interface UpgradeAction {
    name: string;
    type?: string;
    is_signature?: boolean;
    stone_cost?: number;
    range?: number | string | null;
    range_type?: string;
    stat?: number | string | null;
    stat_suits?: string | null;
    stat_modifier?: string | null;
    resisted_by?: string | null;
    target_number?: number | string | null;
    target_suits?: string | null;
    damage?: string | null;
    description?: string | null;
    triggers?: Array<{ id?: number; name: string; suits?: string; stone_cost?: number; description?: string }>;
}

interface UpgradeAbility {
    name: string;
    suits?: string | null;
    defensive_ability_type?: string | null;
    costs_stone?: boolean;
    description?: string | null;
}

interface UpgradePreview {
    name: string;
    front_image: string;
    back_image: string | null;
    description?: string | null;
    // Full granted actions/abilities (Campaign equipment, pg 19) — rendered
    // as proper ActionCard/AbilityCard so a player can see the real rules
    // text, not just the flavor description.
    actions?: UpgradeAction[];
    abilities?: UpgradeAbility[];
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
                    <BonanzaCardImage :image="upgrade.front_image" :name="upgrade.name" :initial-side="upgrade.loot_side ?? 'a'" />
                </div>
                <template v-else>
                    <div
                        v-if="upgrade.front_image"
                        class="flex min-h-0 flex-1 items-start justify-center px-4 pb-2 [&_img]:max-h-[65dvh] [&_img]:w-auto [&_img]:object-contain"
                    >
                        <UpgradeFlipCard
                            :front-image="upgrade.front_image"
                            :back-image="upgrade.back_image"
                            :alt-text="upgrade.name"
                            :show-link="false"
                        />
                    </div>
                    <!-- No card art uploaded — show the effect text instead of a blank preview. -->
                    <template v-else>
                        <p
                            v-if="!upgrade.description && !upgrade.actions?.length && !upgrade.abilities?.length"
                            class="px-4 pb-2 text-sm leading-relaxed text-muted-foreground"
                        >
                            No description available.
                        </p>
                        <p v-else-if="upgrade.description" class="px-4 pb-2 text-sm leading-relaxed text-muted-foreground">
                            {{ upgrade.description }}
                        </p>
                        <!-- Granted actions/abilities (Campaign equipment, pg 19) — full rules text, not just flavor. -->
                        <div v-if="upgrade.actions?.length || upgrade.abilities?.length" class="max-h-[40dvh] space-y-2 overflow-y-auto px-4 pb-2">
                            <ActionCard v-for="(a, i) in upgrade.actions ?? []" :key="`eq-action-${i}`" :action="a" :hide-footer="true" />
                            <AbilityCard v-for="(ab, i) in upgrade.abilities ?? []" :key="`eq-ability-${i}`" :ability="ab" :hide-footer="true" />
                        </div>
                    </template>
                </template>
                <DrawerFooter class="shrink-0 pt-2">
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>
</template>
