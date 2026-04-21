<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Loader2 } from 'lucide-vue-next';

interface CharacterOption {
    id: number;
    name?: string;
    display_name?: string;
    front_image: string | null;
    station?: string | null;
    type?: string | null;
    count?: number | null;
    summon_target_number?: number | null;
}

// Small helper for the metadata row under the character name. Shows either the
// station (minion / peon / master / …) or the `type` label (for linked-character
// reference rows like "Summons") followed by the Summon Target Number if set.
const metaParts = (char: CharacterOption): string[] => {
    const parts: string[] = [];
    if (char.station) parts.push(char.station);
    else if (char.type) parts.push(char.type);
    if (char.summon_target_number) parts.push(`STN: ${char.summon_target_number}`);
    return parts;
};

defineProps<{
    open: boolean;
    referenceCharacters: CharacterOption[];
    results: CharacterOption[];
    search: string;
    loading: boolean;
    /** Current count of this character in the player's crew — drives `count` cap enforcement. */
    crewCount: (characterId: number) => number;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'update:search', value: string): void;
    (e: 'select', character: CharacterOption): void;
}>();
</script>

<template>
    <Dialog
        :open="open"
        @update:open="
            (v) => {
                emit('update:open', v);
                // Reset the search on close so re-opening doesn't show stale results.
                if (!v) emit('update:search', '');
            }
        "
    >
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Summon Character</DialogTitle>
                <DialogDescription>Select a reference character or search for any character.</DialogDescription>
            </DialogHeader>

            <!-- Reference characters -->
            <div v-if="referenceCharacters.length">
                <div class="mb-1 text-xs font-medium text-muted-foreground">Reference Characters</div>
                <div class="max-h-40 space-y-0.5 overflow-y-auto">
                    <button
                        v-for="char in referenceCharacters"
                        :key="'ref-sum-' + char.id"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition-colors"
                        :class="crewCount(char.id) >= (char.count ?? 99) ? 'cursor-not-allowed opacity-40' : 'hover:bg-accent'"
                        :disabled="crewCount(char.id) >= (char.count ?? 99)"
                        @click="emit('select', char)"
                    >
                        <img v-if="char.front_image" :src="'/storage/' + char.front_image" :alt="char.display_name" class="size-8 rounded object-cover" />
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-medium">{{ char.display_name }}</div>
                            <div v-if="metaParts(char).length" class="flex items-center gap-1.5 text-[10px] capitalize text-muted-foreground">
                                <template v-for="(part, i) in metaParts(char)" :key="i">
                                    <span v-if="i > 0" class="text-muted-foreground/40">·</span>
                                    <span>{{ part }}</span>
                                </template>
                            </div>
                        </div>
                        <span v-if="crewCount(char.id) > 0" class="shrink-0 text-[10px] text-muted-foreground">
                            {{ crewCount(char.id) }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- Search all characters -->
            <details class="rounded-md border">
                <summary class="cursor-pointer px-2 py-1.5 text-xs font-medium text-muted-foreground hover:text-foreground">Search All Characters</summary>
                <div class="border-t px-1 pb-1 pt-1">
                    <Input
                        :model-value="search"
                        placeholder="Search..."
                        class="mb-1"
                        @update:model-value="(v) => emit('update:search', String(v))"
                    />
                    <div class="max-h-36 space-y-0.5 overflow-y-auto">
                        <div v-if="loading" class="flex justify-center py-3">
                            <Loader2 class="size-4 animate-spin text-muted-foreground" />
                        </div>
                        <template v-else-if="results.length">
                            <button
                                v-for="char in results"
                                :key="char.id"
                                class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition-colors"
                                :class="crewCount(char.id) >= (char.count ?? 1) ? 'cursor-not-allowed opacity-40' : 'hover:bg-accent'"
                                :disabled="crewCount(char.id) >= (char.count ?? 1)"
                                @click="emit('select', char)"
                            >
                                <img v-if="char.front_image" :src="char.front_image" :alt="char.display_name ?? char.name" class="size-8 rounded object-cover" />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-medium">{{ char.display_name ?? char.name }}</div>
                                    <div v-if="metaParts(char).length" class="flex items-center gap-1.5 text-xs capitalize text-muted-foreground">
                                        <template v-for="(part, i) in metaParts(char)" :key="i">
                                            <span v-if="i > 0" class="text-muted-foreground/40">·</span>
                                            <span>{{ part }}</span>
                                        </template>
                                    </div>
                                </div>
                                <span v-if="crewCount(char.id) > 0" class="shrink-0 text-[10px] text-muted-foreground">
                                    {{ crewCount(char.id) }}/{{ char.count ?? 1 }}
                                </span>
                            </button>
                        </template>
                        <div v-else-if="search.length >= 2" class="py-3 text-center text-xs text-muted-foreground">No characters found</div>
                    </div>
                </div>
            </details>
        </DialogContent>
    </Dialog>
</template>
