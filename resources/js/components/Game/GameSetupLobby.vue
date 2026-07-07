<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type { GameData } from '@/types/game';
import { Check, Copy, Loader2, QrCode } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    game: GameData;
    isCreator: boolean;
}>();

defineEmits<{
    'open-qr': [url: string, title: string];
}>();

const joinUrl = computed(() => route('games.join', props.game.uuid));
const linkCopied = ref(false);
const copyJoinLink = async () => {
    await navigator.clipboard.writeText(joinUrl.value);
    linkCopied.value = true;
    setTimeout(() => (linkCopied.value = false), 2000);
};
</script>

<template>
    <Card v-if="isCreator" class="mb-6">
        <CardContent class="p-4 sm:p-6">
            <h2 class="mb-3 text-lg font-semibold">Invite Opponent</h2>
            <p class="mb-4 text-sm text-muted-foreground">Share this link with your opponent to join the game.</p>
            <div class="flex items-center gap-2">
                <Input :model-value="joinUrl" readonly class="text-xs" />
                <Button variant="outline" size="sm" class="shrink-0 gap-1.5" @click="copyJoinLink">
                    <Check v-if="linkCopied" class="size-4 text-green-500" />
                    <Copy v-else class="size-4" />
                    {{ linkCopied ? 'Copied' : 'Copy' }}
                </Button>
                <Button variant="outline" size="sm" class="shrink-0" @click="$emit('open-qr', joinUrl, 'Join Game')">
                    <QrCode class="size-4" />
                </Button>
            </div>
        </CardContent>
    </Card>
    <Card v-else class="mb-6">
        <CardContent class="p-4 text-center sm:p-6">
            <Loader2 class="mx-auto mb-3 size-6 animate-spin text-muted-foreground" />
            <p class="text-sm text-muted-foreground">Waiting for the host to start the game...</p>
        </CardContent>
    </Card>
</template>
