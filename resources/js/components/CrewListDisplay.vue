<script setup lang="ts">
import GameIcon from '@/components/GameIcon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import UpgradeFlipCard from '@/components/UpgradeFlipCard.vue';
import { Maximize2, Shield, ShieldAlert, Star, Swords } from 'lucide-vue-next';
import { ref, watch } from 'vue';

export interface CrewUpgradeDisplay {
    id?: number;
    name: string;
    front_image: string | null;
    back_image: string | null;
    is_active?: boolean;
}

export interface CrewMemberDisplay {
    display_name: string;
    faction: string;
    cost: number;
    effective_cost?: number;
    category: string;
    front_image?: string | null;
    back_image?: string | null;
}

defineProps<{
    members: CrewMemberDisplay[];
    crewUpgrades?: CrewUpgradeDisplay[];
    compact?: boolean;
}>();

const factionBackground = (faction: string): string => {
    if (!faction) return '';
    switch (faction.toLowerCase()) {
        case 'explorers_society':
            return 'bg-explorerssociety';
        case 'ten_thunders':
            return 'bg-tenthunders';
        default:
            return `bg-${faction}`;
    }
};

const categoryLabel = (cat: string): string =>
    ({
        leader: 'Leader',
        totem: 'Totem',
        'in-keyword': 'In Keyword',
        versatile: 'Versatile',
        ook: 'Out of Keyword',
        'fixed-crew': 'Preset',
        required: 'Required',
    })[cat] ?? cat;

const categoryColor = (cat: string): string =>
    ({
        leader: 'bg-amber-400/20 text-amber-200',
        totem: 'bg-purple-400/20 text-purple-200',
        'in-keyword': 'bg-green-400/20 text-green-200',
        versatile: 'bg-blue-400/20 text-blue-200',
        ook: 'bg-red-400/20 text-red-200',
        'fixed-crew': 'bg-cyan-400/20 text-cyan-200',
        required: 'bg-orange-400/20 text-orange-200',
    })[cat] ?? '';

// Member card preview drawer
const memberPreview = ref<CrewMemberDisplay | null>(null);
const memberDrawerOpen = ref(false);
const memberFullscreenOpen = ref(false);
const memberFullscreenSide = ref<'front' | 'back'>('front');

const openMemberPreview = (member: CrewMemberDisplay) => {
    if (!member.front_image) return;
    memberPreview.value = member;
    memberDrawerOpen.value = true;
};

const openMemberFullscreen = (side: 'front' | 'back') => {
    memberFullscreenSide.value = side;
    memberFullscreenOpen.value = true;
};

watch(memberDrawerOpen, (val) => {
    if (!val) memberPreview.value = null;
});

// Upgrade preview drawer
const upgradePreview = ref<CrewUpgradeDisplay | null>(null);
const upgradeDrawerOpen = ref(false);

const openUpgradePreview = (upgrade: CrewUpgradeDisplay) => {
    if (!upgrade.front_image) return;
    upgradePreview.value = upgrade;
    upgradeDrawerOpen.value = true;
};

watch(upgradeDrawerOpen, (val) => {
    if (!val) upgradePreview.value = null;
});
</script>

<template>
    <!-- Crew Cards -->
    <div v-if="crewUpgrades?.length" class="mb-2 space-y-1">
        <div
            v-for="(upgrade, idx) in crewUpgrades"
            :key="idx"
            class="flex items-center gap-1.5 rounded-md border px-2 py-1 transition-colors"
            :class="[
                upgrade.is_active ? 'border-amber-500/50 bg-amber-500/10' : 'border-border/50 bg-accent/30 opacity-60',
                upgrade.front_image ? 'cursor-pointer hover:bg-accent' : '',
            ]"
            @click="openUpgradePreview(upgrade)"
        >
            <Star class="size-3 shrink-0" :class="upgrade.is_active ? 'fill-amber-500 text-amber-500' : 'text-muted-foreground'" />
            <div class="min-w-0 flex-1">
                <div class="text-xs font-semibold">{{ upgrade.name }}</div>
                <div class="text-[10px] text-muted-foreground">
                    {{ upgrade.is_active ? 'Active Crew Card' : 'Crew Card' }}
                </div>
            </div>
        </div>
    </div>

    <!-- Member Rows -->
    <div class="space-y-0.5">
        <div
            v-for="(member, index) in members"
            :key="index"
            :class="factionBackground(member.faction)"
            class="rounded-md border border-white/20 text-white transition-colors hover:brightness-110"
            :style="{ cursor: member.front_image ? 'pointer' : 'default', padding: compact ? '2px 8px' : '4px 8px' }"
            @click="openMemberPreview(member)"
        >
            <div class="flex items-center justify-between">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5" :class="compact ? 'text-xs font-semibold' : 'text-sm font-semibold'">
                        <TooltipProvider v-if="member.category === 'leader' || member.category === 'totem' || member.category === 'ook'">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Shield v-if="member.category === 'leader'" class="size-3.5 shrink-0 text-amber-300" />
                                    <Swords v-if="member.category === 'totem'" class="size-3.5 shrink-0 text-purple-300" />
                                    <ShieldAlert v-if="member.category === 'ook'" class="size-3.5 shrink-0 text-red-300" />
                                </TooltipTrigger>
                                <TooltipContent side="top">
                                    <p class="text-xs">{{ categoryLabel(member.category) }}</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                        <span class="truncate">{{ member.display_name }}</span>
                    </div>
                    <div v-if="!compact" class="flex items-center gap-1.5 text-xs text-white/70">
                        <span v-if="member.category === 'ook'" class="flex items-center text-sm font-bold text-white">
                            {{ member.effective_cost ?? member.cost
                            }}<GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                            <span class="text-xs font-normal text-red-300">({{ member.cost - 1 }}+1)</span>
                        </span>
                        <span v-else class="flex items-center text-sm font-bold text-white">
                            {{ member.effective_cost ?? member.cost
                            }}<GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                        </span>
                        <Badge :class="categoryColor(member.category)" class="px-1 py-0 text-[10px]">
                            {{ categoryLabel(member.category) }}
                        </Badge>
                    </div>
                </div>
                <span v-if="compact" class="ml-2 shrink-0 text-xs font-bold text-white">
                    {{ member.effective_cost ?? member.cost }}<GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                </span>
            </div>
        </div>
    </div>

    <!-- Member Card Preview Drawer -->
    <Drawer v-model:open="memberDrawerOpen">
        <DrawerContent>
            <div v-if="memberPreview" class="mx-auto w-full" :class="memberPreview.back_image ? 'max-w-2xl' : 'max-w-sm'">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ memberPreview.display_name }}</DrawerTitle>
                </DrawerHeader>
                <!-- Mobile: flip card -->
                <div
                    class="flex min-h-0 flex-1 items-start justify-center px-4 pb-2 sm:hidden [&_img]:max-h-[55dvh] [&_img]:w-auto [&_img]:object-contain"
                >
                    <UpgradeFlipCard
                        :front-image="memberPreview.front_image!"
                        :back-image="memberPreview.back_image"
                        :alt-text="memberPreview.display_name"
                        :show-link="false"
                    />
                </div>
                <!-- Desktop: side by side -->
                <div class="hidden items-start justify-center gap-4 px-4 pb-2 sm:flex">
                    <div class="relative">
                        <img
                            :src="'/storage/' + memberPreview.front_image"
                            :alt="memberPreview.display_name + ' (front)'"
                            loading="lazy"
                            decoding="async"
                            class="max-h-[55dvh] w-auto rounded-lg"
                        />
                        <button
                            @click.stop="openMemberFullscreen('front')"
                            class="absolute bottom-3 right-2 rounded-full bg-black/40 p-1.5 text-white/70 backdrop-blur-sm transition-all hover:bg-black/70 hover:text-white"
                            title="View fullscreen"
                        >
                            <Maximize2 class="size-3.5" />
                        </button>
                    </div>
                    <div v-if="memberPreview.back_image" class="relative">
                        <img
                            :src="'/storage/' + memberPreview.back_image"
                            :alt="memberPreview.display_name + ' (back)'"
                            loading="lazy"
                            decoding="async"
                            class="max-h-[55dvh] w-auto rounded-lg"
                        />
                        <button
                            @click.stop="openMemberFullscreen('back')"
                            class="absolute bottom-3 right-2 rounded-full bg-black/40 p-1.5 text-white/70 backdrop-blur-sm transition-all hover:bg-black/70 hover:text-white"
                            title="View fullscreen"
                        >
                            <Maximize2 class="size-3.5" />
                        </button>
                    </div>
                </div>
                <DrawerFooter class="shrink-0 pt-2">
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>

    <!-- Member Fullscreen Dialog -->
    <Dialog v-model:open="memberFullscreenOpen">
        <DialogContent class="max-h-[95dvh] max-w-[95vw] border-none bg-black/95 p-2 sm:max-w-fit sm:p-4">
            <DialogTitle class="sr-only">{{ memberPreview?.display_name }} ({{ memberFullscreenSide }})</DialogTitle>
            <div v-if="memberPreview" class="flex items-center justify-center">
                <img
                    :src="'/storage/' + (memberFullscreenSide === 'back' ? memberPreview.back_image : memberPreview.front_image)"
                    :alt="memberPreview.display_name"
                    loading="lazy"
                    decoding="async"
                    class="max-h-[90dvh] w-auto rounded-lg object-contain"
                />
            </div>
        </DialogContent>
    </Dialog>

    <!-- Upgrade Preview Drawer -->
    <Drawer v-model:open="upgradeDrawerOpen">
        <DrawerContent>
            <div v-if="upgradePreview" class="mx-auto w-full max-w-sm">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ upgradePreview.name }}</DrawerTitle>
                    <div class="mt-1 text-center text-xs text-muted-foreground">Crew Card</div>
                </DrawerHeader>
                <div
                    class="flex min-h-0 flex-1 items-start justify-center px-4 pb-2 [&_img]:max-h-[55dvh] [&_img]:w-auto [&_img]:object-contain"
                >
                    <UpgradeFlipCard
                        :front-image="upgradePreview.front_image!"
                        :back-image="upgradePreview.back_image"
                        :alt-text="upgradePreview.name"
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
