<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import { useTournament } from '@/composables/useTournament';
import { Loader2, MessageSquare } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * Connects the event to a Discord channel webhook. BiggerHat then posts there when registration opens, when a round starts
 * (with the pairings), when the organizer posts an announcement and when the event finishes. The URL is a secret: it is
 * never shown again once saved.
 */
const props = defineProps<{ tournament: { uuid: string }; connected: boolean }>();
const emit = defineEmits<{ (e: 'saved'): void }>();

const toast = useToast();
const confirm = useConfirm();
const { doModalAction } = useTournament(computed(() => props.tournament as never));

const url = ref('');
const busy = ref<'save' | 'test' | 'remove' | null>(null);
const error = ref<string | null>(null);

const run = async (kind: 'save' | 'test' | 'remove', request: () => ReturnType<typeof doModalAction>, done: string) => {
    busy.value = kind;
    error.value = null;
    const { ok, error: message } = await request();
    busy.value = null;
    if (!ok) {
        error.value = message;
        return;
    }
    toast.success(done);
    if (kind !== 'test') {
        url.value = '';
        emit('saved');
    }
};

const save = () =>
    run(
        'save',
        () => doModalAction(route('tournaments.discord', props.tournament.uuid), 'PUT', { webhook_url: url.value.trim() }),
        'Discord connected',
    );
const test = () => run('test', () => doModalAction(route('tournaments.discord.test', props.tournament.uuid), 'POST'), 'Test message sent');
const remove = async () => {
    if (
        !(await confirm({
            title: 'Disconnect Discord?',
            message: 'BiggerHat stops posting this event to the channel.',
            confirmLabel: 'Disconnect',
            destructive: true,
        }))
    )
        return;
    await run('remove', () => doModalAction(route('tournaments.discord', props.tournament.uuid), 'PUT', { webhook_url: '' }), 'Discord disconnected');
};
</script>

<template>
    <Card class="mb-4" data-testid="discord-card">
        <CardContent class="space-y-3 p-4 sm:p-5">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h2 class="flex items-center gap-1.5 text-sm font-semibold"><MessageSquare class="size-4" aria-hidden="true" /> Discord</h2>
                    <p class="text-xs text-muted-foreground">
                        Post this event's news to a Discord channel: registration opening, each round's pairings, your announcements and the final
                        results.
                    </p>
                </div>
                <Badge v-if="connected" variant="outline" class="shrink-0 border-green-600/40 text-2xs text-green-700 dark:text-green-400"
                    >Connected</Badge
                >
            </div>
            <div class="space-y-1.5">
                <Input
                    v-model="url"
                    type="password"
                    autocomplete="off"
                    :placeholder="connected ? 'Paste a new webhook URL to replace the current one' : 'https://discord.com/api/webhooks/…'"
                    aria-label="Discord webhook URL"
                    @keydown.enter.prevent="url.trim() && save()"
                />
                <p class="text-2xs text-muted-foreground">
                    In Discord: Channel settings → Integrations → Webhooks → New Webhook → Copy Webhook URL. Anyone with the URL can post to the
                    channel, so it is kept private and not shown again.
                </p>
            </div>
            <p v-if="error" class="text-xs text-destructive" role="alert">{{ error }}</p>
            <div class="flex flex-wrap gap-2">
                <Button type="button" size="sm" :disabled="!url.trim() || busy !== null" @click="save">
                    <Loader2 v-if="busy === 'save'" class="mr-1 size-3.5 animate-spin" /> {{ connected ? 'Replace webhook' : 'Connect' }}
                </Button>
                <template v-if="connected">
                    <Button type="button" size="sm" variant="outline" :disabled="busy !== null" @click="test">
                        <Loader2 v-if="busy === 'test'" class="mr-1 size-3.5 animate-spin" /> Send test message
                    </Button>
                    <Button type="button" size="sm" variant="ghost" class="text-muted-foreground" :disabled="busy !== null" @click="remove"
                        >Disconnect</Button
                    >
                </template>
            </div>
        </CardContent>
    </Card>
</template>
