<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useConfirm } from '@/composables/useConfirm';
import { Head, router } from '@inertiajs/vue3';
import { Check, Copy, Key, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

const confirm = useConfirm();

interface UserRef {
    id: number;
    name: string;
    email: string;
}

interface Token {
    id: number;
    name: string;
    abilities: string[];
    last_used_at: string | null;
    created_at: string | null;
    tokenable: UserRef | null;
}

const props = defineProps<{
    tokens: Token[];
    users: UserRef[];
    new_token: { plaintext: string; name: string; user_name: string } | null;
}>();

const form = ref({ user_id: '', name: '', abilities: '*' });
const copied = ref(false);

const submit = () => {
    if (!form.value.user_id || !form.value.name) return;
    router.post(
        route('admin.api_tokens.store'),
        {
            user_id: form.value.user_id,
            name: form.value.name,
            abilities: form.value.abilities.split(',').map((a) => a.trim()).filter(Boolean),
        },
        {
            onSuccess: () => {
                form.value = { user_id: '', name: '', abilities: '*' };
            },
        },
    );
};

const revoke = async (token: Token) => {
    if (!(await confirm({
        title: `Revoke "${token.name}"?`,
        message: 'Any client using this token will start failing immediately.',
        confirmLabel: 'Revoke',
        destructive: true,
    }))) return;
    router.post(route('admin.api_tokens.delete', token.id), {}, { preserveScroll: true });
};

const copyToken = async () => {
    if (!props.new_token) return;
    await navigator.clipboard.writeText(props.new_token.plaintext);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
};

const formatDate = (s: string | null) => (s ? new Date(s).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' }) : '—');
</script>

<template>
    <Head title="API Tokens - Admin" />
    <div class="container mx-auto space-y-4 px-4 py-6 lg:px-8 xl:px-12">
        <div class="flex items-center gap-2">
            <Key class="size-5" />
            <h1 class="text-2xl font-semibold tracking-tight">API Tokens</h1>
        </div>
        <p class="text-sm text-muted-foreground">
            Sanctum personal access tokens. Mint a token for a service account (e.g. the Hat Gamin bot). The plaintext shown after creation is the only
            time it's visible — copy it then.
        </p>

        <!-- Just-created token banner -->
        <Card v-if="new_token" class="border-green-500/40 bg-green-500/5">
            <CardContent class="space-y-2 p-4">
                <div class="flex items-center gap-2 text-sm font-semibold">
                    <Check class="size-4 text-green-600 dark:text-green-400" />
                    Token created for {{ new_token.user_name }} — "{{ new_token.name }}"
                </div>
                <div class="flex items-center gap-2">
                    <code class="flex-1 truncate rounded bg-muted px-2 py-1 font-mono text-xs">{{ new_token.plaintext }}</code>
                    <Button size="sm" variant="outline" @click="copyToken">
                        <Check v-if="copied" class="size-3.5 text-green-500" />
                        <Copy v-else class="size-3.5" />
                        {{ copied ? 'Copied' : 'Copy' }}
                    </Button>
                </div>
                <p class="text-xs text-muted-foreground">Store this somewhere safe. We don't keep the plaintext — only a hash.</p>
            </CardContent>
        </Card>

        <!-- Mint form -->
        <Card>
            <CardContent class="space-y-3 p-4">
                <div class="text-sm font-semibold">Mint new token</div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="flex flex-col gap-1">
                        <Label for="user_id">User</Label>
                        <Select v-model="form.user_id">
                            <SelectTrigger><SelectValue placeholder="Select user" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="u in users" :key="u.id" :value="String(u.id)">{{ u.name }} ({{ u.email }})</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label for="name">Token name</Label>
                        <Input id="name" v-model="form.name" placeholder="hat-gamin-prod" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label for="abilities">Abilities (comma-separated)</Label>
                        <Input id="abilities" v-model="form.abilities" placeholder="* (all)" />
                    </div>
                </div>
                <Button :disabled="!form.user_id || !form.name" @click="submit">Create token</Button>
            </CardContent>
        </Card>

        <!-- Existing tokens -->
        <div class="space-y-2">
            <Card v-for="token in tokens" :key="token.id">
                <CardContent class="flex items-center gap-3 p-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-semibold">{{ token.name }}</span>
                            <span v-if="token.tokenable" class="text-xs text-muted-foreground">
                                · {{ token.tokenable.name }} ({{ token.tokenable.email }})
                            </span>
                        </div>
                        <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                            <span>Created {{ formatDate(token.created_at) }}</span>
                            <span>·</span>
                            <span>Last used {{ formatDate(token.last_used_at) }}</span>
                            <span v-if="token.abilities.length" class="ml-2 flex flex-wrap gap-1">
                                <Badge v-for="ability in token.abilities" :key="ability" variant="secondary" class="text-[10px]">{{ ability }}</Badge>
                            </span>
                        </div>
                    </div>
                    <Button variant="outline" size="sm" @click="revoke(token)">
                        <Trash2 class="size-3.5" />
                    </Button>
                </CardContent>
            </Card>
            <div v-if="!tokens.length" class="py-8 text-center text-sm text-muted-foreground">No tokens yet.</div>
        </div>
    </div>
</template>
