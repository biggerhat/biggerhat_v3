<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Head, useForm } from '@inertiajs/vue3';

interface Allegiance {
    id: number;
    slug: string;
    name: string;
    type: string;
    is_syndicate: boolean;
    color_slug: string | null;
}

defineProps<{
    allegiances: Allegiance[];
}>();

const form = useForm({
    name: '',
    allegiance_id: null as number | null,
    notes: '',
});

function submit() {
    form.post(route('tos.companies.store'));
}
</script>

<template>
    <Head title="New Company — TOS" />
    <div class="relative">
        <PageBanner title="New Company" class="mb-2" />

        <div class="container mx-auto max-w-2xl space-y-4 sm:px-4">
            <Card>
                <CardContent class="space-y-4 p-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium">Company name</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            maxlength="120"
                            class="w-full rounded-md border bg-background px-3 py-1.5 text-sm"
                            placeholder="e.g. The Iron Vanguard"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-[11px] text-rose-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium">Allegiance</label>
                        <p class="mb-2 text-[11px] text-muted-foreground">
                            Allegiance is locked once chosen — pick the side this Company fights for.
                        </p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <button
                                v-for="a in allegiances"
                                :key="a.id"
                                type="button"
                                :class="[
                                    'flex items-center gap-2 rounded-md border p-2 text-left text-xs transition',
                                    form.allegiance_id === a.id ? 'border-primary bg-primary/5 ring-1 ring-primary/40' : 'hover:border-primary/30',
                                ]"
                                @click="form.allegiance_id = a.id"
                            >
                                <span :class="['h-8 w-1 rounded-sm', a.color_slug ? `bg-${a.color_slug}` : 'bg-primary/40']" />
                                <div class="min-w-0">
                                    <p class="font-medium">{{ a.name }}</p>
                                    <p class="text-[10px] capitalize text-muted-foreground">
                                        {{ a.type }}{{ a.is_syndicate ? ' syndicate' : '' }}
                                    </p>
                                </div>
                            </button>
                        </div>
                        <p v-if="form.errors.allegiance_id" class="mt-1 text-[11px] text-rose-600">{{ form.errors.allegiance_id }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium">Notes (optional)</label>
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            class="w-full rounded-md border bg-background px-3 py-1.5 text-sm"
                        />
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button as="a" :href="route('tos.companies.index')" variant="ghost" size="sm">Cancel</Button>
                        <Button :disabled="form.processing || !form.name || !form.allegiance_id" size="sm" @click="submit">
                            Create Company
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
