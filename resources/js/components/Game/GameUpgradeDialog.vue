<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { ArrowUpCircle, Check, Minus } from 'lucide-vue-next';
import { computed } from 'vue';

interface AttachedUpgrade {
    id: number;
    name: string;
    front_image: string | null;
    back_image: string | null;
}

interface UpgradeOption {
    id: number;
    name: string;
    front_image: string | null;
    back_image: string | null;
    plentiful?: number;
}

interface Member {
    display_name: string;
    attached_upgrades: AttachedUpgrade[];
}

const props = defineProps<{
    open: boolean;
    member: Member | null;
    /** Already-filtered upgrade options (parent scopes to the member's keyword / allowed list). */
    options: UpgradeOption[];
    /** Subset of option IDs surfaced in the "Reference Upgrades" shortcut list. */
    referenceIds: Set<number>;
    /** Two-way search string for the "All Upgrades" section. */
    search: string;
    /** Count of how many times the upgrade id is currently in use across the crew — drives `plentiful` caps. */
    usageCount: (upgradeId: number) => number;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'update:search', value: string): void;
    (e: 'toggle', upgrade: AttachedUpgrade): void;
}>();

const memberHasUpgrade = (upgradeId: number) => (props.member?.attached_upgrades ?? []).some((u) => u.id === upgradeId);

const isAtLimit = (upgradeId: number, plentiful: number | undefined) => {
    const limit = plentiful ?? 1;
    return props.usageCount(upgradeId) >= limit;
};

const referenceList = computed(() => props.options.filter((u) => props.referenceIds.has(u.id)));
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Manage Upgrades</DialogTitle>
                <DialogDescription v-if="member">{{ member.display_name }}</DialogDescription>
            </DialogHeader>

            <!-- Current upgrades -->
            <div v-if="member?.attached_upgrades?.length" class="space-y-1">
                <div class="text-xs font-medium text-muted-foreground">Active Upgrades</div>
                <div class="space-y-0.5">
                    <div
                        v-for="upgrade in member.attached_upgrades"
                        :key="'cu-' + upgrade.id"
                        class="flex items-center justify-between rounded-md border border-amber-500/30 bg-amber-500/5 px-2 py-1 text-xs"
                    >
                        <div class="flex items-center gap-1.5">
                            <ArrowUpCircle class="size-3 shrink-0 text-amber-500" />
                            <span class="font-medium">{{ upgrade.name }}</span>
                        </div>
                        <button
                            class="rounded p-0.5 text-red-400 hover:bg-red-500/10"
                            :aria-label="'Remove ' + upgrade.name"
                            @click="emit('toggle', upgrade)"
                        >
                            <Minus class="size-3" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Reference upgrades -->
            <div v-if="referenceList.length">
                <div class="mb-1 text-xs font-medium text-muted-foreground">Reference Upgrades</div>
                <div class="max-h-32 space-y-0.5 overflow-y-auto">
                    <button
                        v-for="upgrade in referenceList"
                        :key="'ref-' + upgrade.id"
                        class="flex w-full items-center gap-2 rounded px-2 py-1 text-left text-sm transition-colors"
                        :class="[
                            memberHasUpgrade(upgrade.id) ? 'bg-amber-500/10 font-medium' : '',
                            !memberHasUpgrade(upgrade.id) && isAtLimit(upgrade.id, upgrade.plentiful)
                                ? 'cursor-not-allowed opacity-40'
                                : 'hover:bg-accent',
                        ]"
                        :disabled="!memberHasUpgrade(upgrade.id) && isAtLimit(upgrade.id, upgrade.plentiful)"
                        @click="emit('toggle', upgrade)"
                    >
                        <Check v-if="memberHasUpgrade(upgrade.id)" class="size-3 shrink-0 text-amber-500" />
                        <ArrowUpCircle v-else class="size-3 shrink-0 text-muted-foreground" />
                        <span class="min-w-0 flex-1 truncate text-xs">{{ upgrade.name }}</span>
                        <span v-if="(upgrade.plentiful ?? 1) > 1" class="shrink-0 text-[9px] text-muted-foreground">
                            {{ usageCount(upgrade.id) + (memberHasUpgrade(upgrade.id) ? 1 : 0) }}/{{ upgrade.plentiful }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- All upgrades -->
            <details class="rounded-md border">
                <summary class="cursor-pointer px-2 py-1.5 text-xs font-medium text-muted-foreground hover:text-foreground">All Upgrades</summary>
                <div class="border-t px-1 pb-1 pt-1">
                    <Input :model-value="search" placeholder="Filter..." class="mb-1" @update:model-value="(v) => emit('update:search', String(v))" />
                    <div class="max-h-36 space-y-0.5 overflow-y-auto">
                        <button
                            v-for="upgrade in options"
                            :key="upgrade.id"
                            class="flex w-full items-center gap-2 rounded px-2 py-1 text-left text-sm transition-colors"
                            :class="[
                                memberHasUpgrade(upgrade.id) ? 'bg-amber-500/10 font-medium' : '',
                                !memberHasUpgrade(upgrade.id) && isAtLimit(upgrade.id, upgrade.plentiful)
                                    ? 'cursor-not-allowed opacity-40'
                                    : 'hover:bg-accent',
                            ]"
                            :disabled="!memberHasUpgrade(upgrade.id) && isAtLimit(upgrade.id, upgrade.plentiful)"
                            @click="emit('toggle', upgrade)"
                        >
                            <Check v-if="memberHasUpgrade(upgrade.id)" class="size-3 shrink-0 text-amber-500" />
                            <ArrowUpCircle v-else class="size-3 shrink-0 text-muted-foreground" />
                            <span class="min-w-0 flex-1 truncate text-xs">{{ upgrade.name }}</span>
                            <span v-if="(upgrade.plentiful ?? 1) > 1" class="shrink-0 text-[9px] text-muted-foreground">
                                {{ usageCount(upgrade.id) + (memberHasUpgrade(upgrade.id) ? 1 : 0) }}/{{ upgrade.plentiful }}
                            </span>
                        </button>
                    </div>
                </div>
            </details>

            <DialogFooter>
                <Button variant="outline" class="w-full" @click="emit('update:open', false)">Close</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
