<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Check, Minus, Plus } from 'lucide-vue-next';
import { computed } from 'vue';

interface AttachedToken {
    id: number;
    name: string;
}

interface TokenOption {
    id: number;
    name: string;
    slug: string;
    description: string | null;
}

interface Member {
    display_name: string;
    attached_tokens: AttachedToken[];
}

const props = defineProps<{
    open: boolean;
    member: Member | null;
    /** Full catalogue of tokens available in the game. */
    tokens: TokenOption[];
    /** Subset of token IDs surfaced as the "Reference Tokens" shortcut list. */
    referenceTokenIds: Set<number>;
    /** Two-way filter string for the "All Tokens" section. */
    search: string;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'update:search', value: string): void;
    (e: 'toggle', tokenId: number, tokenName: string): void;
    (e: 'remove', tokenId: number): void;
}>();

const memberHasToken = (tokenId: number) => (props.member?.attached_tokens ?? []).some((t) => t.id === tokenId);

const referenceTokens = computed(() => props.tokens.filter((t) => props.referenceTokenIds.has(t.id)));

const filteredTokens = computed(() => {
    const needle = props.search.toLowerCase();
    return needle ? props.tokens.filter((t) => t.name.toLowerCase().includes(needle)) : props.tokens;
});
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Manage Tokens</DialogTitle>
                <DialogDescription v-if="member">{{ member.display_name }}</DialogDescription>
            </DialogHeader>

            <!-- Current tokens -->
            <div v-if="member?.attached_tokens?.length" class="space-y-1">
                <div class="text-xs font-medium text-muted-foreground">Active Tokens</div>
                <div class="flex flex-wrap gap-1">
                    <Badge
                        v-for="token in member.attached_tokens"
                        :key="'current-' + token.id"
                        variant="secondary"
                        class="cursor-pointer gap-1 pr-1"
                        @click="emit('remove', token.id)"
                    >
                        {{ token.name }}
                        <Minus class="size-3 text-red-400" />
                    </Badge>
                </div>
            </div>

            <!-- Reference tokens -->
            <div v-if="referenceTokens.length">
                <div class="mb-1 text-xs font-medium text-muted-foreground">Reference Tokens</div>
                <div class="max-h-32 space-y-0.5 overflow-y-auto">
                    <button
                        v-for="token in referenceTokens"
                        :key="'ref-' + token.id"
                        class="flex w-full items-center gap-2 rounded px-2 py-1 text-left text-sm transition-colors"
                        :class="memberHasToken(token.id) ? 'bg-primary/10 font-medium' : 'hover:bg-accent'"
                        @click="emit('toggle', token.id, token.name)"
                    >
                        <Check v-if="memberHasToken(token.id)" class="size-3 shrink-0 text-green-500" />
                        <Plus v-else class="size-3 shrink-0 text-muted-foreground" />
                        {{ token.name }}
                    </button>
                </div>
            </div>

            <!-- All tokens -->
            <details class="rounded-md border">
                <summary class="cursor-pointer px-2 py-1.5 text-xs font-medium text-muted-foreground hover:text-foreground">All Tokens</summary>
                <div class="border-t px-1 pb-1 pt-1">
                    <Input :model-value="search" placeholder="Filter..." class="mb-1" @update:model-value="(v) => emit('update:search', String(v))" />
                    <div class="max-h-36 space-y-0.5 overflow-y-auto">
                        <button
                            v-for="token in filteredTokens"
                            :key="token.id"
                            class="flex w-full items-center gap-2 rounded px-2 py-1 text-left text-sm transition-colors"
                            :class="memberHasToken(token.id) ? 'bg-primary/10 font-medium' : 'hover:bg-accent'"
                            @click="emit('toggle', token.id, token.name)"
                        >
                            <Check v-if="memberHasToken(token.id)" class="size-3 shrink-0 text-green-500" />
                            <Plus v-else class="size-3 shrink-0 text-muted-foreground" />
                            {{ token.name }}
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
