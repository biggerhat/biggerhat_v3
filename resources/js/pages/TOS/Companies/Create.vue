<script setup lang="ts">
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import HeadingEyebrow from '@/components/HeadingEyebrow.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Check, Shield } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Allegiance {
    id: number;
    slug: string;
    name: string;
    type: string;
    is_syndicate: boolean;
    color_slug: string | null;
}

interface GarrisonOption {
    id: number;
    slug: string;
    name: string;
    allegiance_id: number;
    format: string;
    allegiance: { id: number; slug: string; name: string; color_slug: string | null };
    updated_at: string;
}

interface FormatOption {
    value: string;
    label: string;
    description: string;
}

const props = defineProps<{
    allegiances: Allegiance[];
    garrisons: GarrisonOption[];
    preselect_garrison_id: number | null;
    formats: FormatOption[];
}>();

const NONE = 'none';

const form = useForm({
    name: '',
    allegiance_id: null as number | null,
    garrison_id: props.preselect_garrison_id,
    format: null as string | null,
    envoy_allegiance_id: null as number | null,
    notes: '',
});

// Sentinel-backed local bindings (Reka Select disallows empty-string values).
const formatSelection = ref<string>(NONE);
const envoySelection = ref<string>(NONE);

// When the user picks a Garrison, the allegiance is snapped to match —
// the picker below is locked and the chosen allegiance card is highlighted.
const linkedGarrison = computed<GarrisonOption | null>(() =>
    form.garrison_id ? (props.garrisons.find((g) => g.id === form.garrison_id) ?? null) : null,
);

if (linkedGarrison.value) {
    form.allegiance_id = linkedGarrison.value.allegiance_id;
    formatSelection.value = linkedGarrison.value.format || NONE;
}

function selectGarrison(g: GarrisonOption | null) {
    form.garrison_id = g?.id ?? null;
    if (g) {
        form.allegiance_id = g.allegiance_id;
        // A Garrison-linked Company inherits (and locks to) the Garrison's format.
        formatSelection.value = g.format || NONE;
    }
}

// Envoy is a second, different Allegiance from the Primary.
const envoyOptions = computed(() => props.allegiances.filter((a) => a.id !== form.allegiance_id));

const selectedFormat = computed<FormatOption | null>(() => props.formats.find((f) => f.value === formatSelection.value) ?? null);

const typeFilter = ref<'all' | 'earth' | 'malifaux'>('all');

const filteredAllegiances = computed(() => {
    if (typeFilter.value === 'all') return props.allegiances;
    return props.allegiances.filter((a) => a.type === typeFilter.value);
});

const groupedAllegiances = computed(() => {
    const main = filteredAllegiances.value.filter((a) => !a.is_syndicate);
    const syndicates = filteredAllegiances.value.filter((a) => a.is_syndicate);
    return { main, syndicates };
});

function submit() {
    form.format = formatSelection.value === NONE ? null : formatSelection.value;
    // Drop the Envoy if it collides with the Primary (e.g. Primary changed after picking).
    form.envoy_allegiance_id =
        envoySelection.value === NONE || Number(envoySelection.value) === form.allegiance_id ? null : Number(envoySelection.value);
    form.post(route('tos.companies.store'));
}
</script>

<template>
    <Head title="New Company — TOS" />
    <div class="relative pb-12">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="New Company" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Pick an Allegiance, name the Company, then start hiring.
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto max-w-3xl space-y-4 sm:px-4">
            <Link :href="route('tos.companies.index')" class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground">
                <ArrowLeft class="size-3" /> All Companies
            </Link>

            <Card>
                <CardContent class="space-y-5 p-4 sm:p-6">
                    <!-- Step 1: Name -->
                    <div class="space-y-1.5">
                        <Label for="name">Company name</Label>
                        <Input id="name" v-model="form.name" type="text" required maxlength="120" placeholder="e.g. The Iron Vanguard" />
                        <p v-if="form.errors.name" class="text-[11px] text-rose-600">{{ form.errors.name }}</p>
                    </div>

                    <!-- Optional: Build from Garrison -->
                    <div v-if="garrisons.length" class="space-y-2">
                        <div class="flex items-baseline justify-between gap-2">
                            <div>
                                <Label class="flex items-center gap-1.5"
                                    ><Shield class="size-3.5" /> Build from Garrison
                                    <span class="text-[11px] font-normal text-muted-foreground">(optional)</span></Label
                                >
                                <p class="mt-0.5 text-[11px] text-muted-foreground">
                                    Restricts the hiring pool to a tournament Garrison you've declared. Allegiance locks to match.
                                </p>
                            </div>
                            <Button
                                v-if="linkedGarrison"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-6 px-2 text-[11px]"
                                @click="selectGarrison(null)"
                                >Clear</Button
                            >
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <button
                                v-for="g in garrisons"
                                :key="g.id"
                                type="button"
                                :class="[
                                    'group relative flex items-center gap-3 overflow-hidden rounded-lg border p-2 text-left text-xs transition-all',
                                    form.garrison_id === g.id
                                        ? 'border-primary bg-primary/5 ring-1 ring-primary/40'
                                        : 'hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-sm',
                                ]"
                                @click="selectGarrison(g)"
                            >
                                <div
                                    :class="[
                                        'absolute left-0 top-0 h-full w-1',
                                        g.allegiance.color_slug ? `bg-${g.allegiance.color_slug}` : 'bg-primary/40',
                                    ]"
                                />
                                <div class="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted/40 ring-1 ring-border/50">
                                    <AllegianceLogo :allegiance="g.allegiance.slug" class-name="size-5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[12px] font-semibold">{{ g.name }}</p>
                                    <p class="truncate text-[10px] text-muted-foreground">{{ g.allegiance.name }}</p>
                                </div>
                                <Check v-if="form.garrison_id === g.id" class="size-4 shrink-0 text-primary" />
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Allegiance picker -->
                    <div class="space-y-2">
                        <div class="flex items-end justify-between">
                            <div>
                                <Label>Allegiance</Label>
                                <p class="mt-0.5 text-[11px] text-muted-foreground">
                                    <span v-if="linkedGarrison"
                                        >Locked to <strong>{{ linkedGarrison.allegiance.name }}</strong> by your Garrison.</span
                                    >
                                    <span v-else>Locked once chosen — pick the side this Company fights for.</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-1">
                                <Button
                                    v-for="t in ['all', 'earth', 'malifaux'] as const"
                                    :key="t"
                                    type="button"
                                    :variant="typeFilter === t ? 'default' : 'outline'"
                                    size="sm"
                                    class="h-6 px-2 text-[11px] capitalize"
                                    @click="typeFilter = t"
                                    >{{ t === 'all' ? 'All' : t }}</Button
                                >
                            </div>
                        </div>

                        <!-- Main Allegiances -->
                        <div v-if="groupedAllegiances.main.length" class="space-y-1.5">
                            <HeadingEyebrow>Allegiances</HeadingEyebrow>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <button
                                    v-for="a in groupedAllegiances.main"
                                    :key="a.id"
                                    type="button"
                                    :disabled="!!linkedGarrison && a.id !== form.allegiance_id"
                                    :class="[
                                        'group relative flex items-center gap-3 overflow-hidden rounded-lg border p-3 text-left text-xs transition-all',
                                        form.allegiance_id === a.id
                                            ? 'border-primary bg-primary/5 ring-1 ring-primary/40'
                                            : 'hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-sm',
                                        !!linkedGarrison && a.id !== form.allegiance_id ? 'cursor-not-allowed opacity-40' : '',
                                    ]"
                                    @click="form.allegiance_id = a.id"
                                >
                                    <div :class="['absolute left-0 top-0 h-full w-1', a.color_slug ? `bg-${a.color_slug}` : 'bg-primary/40']" />
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted/40 ring-1 ring-border/50">
                                        <AllegianceLogo :allegiance="a.slug" class-name="size-7" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-semibold">{{ a.name }}</p>
                                        <p class="text-[10px] capitalize text-muted-foreground">{{ a.type }}</p>
                                    </div>
                                    <Check v-if="form.allegiance_id === a.id" class="size-4 shrink-0 text-primary" />
                                </button>
                            </div>
                        </div>

                        <!-- Syndicates -->
                        <div v-if="groupedAllegiances.syndicates.length" class="space-y-1.5 pt-1">
                            <HeadingEyebrow>Syndicates</HeadingEyebrow>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <button
                                    v-for="a in groupedAllegiances.syndicates"
                                    :key="a.id"
                                    type="button"
                                    :disabled="!!linkedGarrison && a.id !== form.allegiance_id"
                                    :class="[
                                        'group relative flex items-center gap-3 overflow-hidden rounded-lg border p-3 text-left text-xs transition-all',
                                        form.allegiance_id === a.id
                                            ? 'border-primary bg-primary/5 ring-1 ring-primary/40'
                                            : 'hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-sm',
                                        !!linkedGarrison && a.id !== form.allegiance_id ? 'cursor-not-allowed opacity-40' : '',
                                    ]"
                                    @click="form.allegiance_id = a.id"
                                >
                                    <div :class="['absolute left-0 top-0 h-full w-1', a.color_slug ? `bg-${a.color_slug}` : 'bg-primary/40']" />
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted/40 ring-1 ring-border/50">
                                        <AllegianceLogo :allegiance="a.slug" class-name="size-7" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <p class="truncate font-semibold">{{ a.name }}</p>
                                            <Badge variant="outline" class="px-1 py-0 text-[9px]">Syndicate</Badge>
                                        </div>
                                        <p class="text-[10px] capitalize text-muted-foreground">{{ a.type }}</p>
                                    </div>
                                    <Check v-if="form.allegiance_id === a.id" class="size-4 shrink-0 text-primary" />
                                </button>
                            </div>
                        </div>

                        <p v-if="form.errors.allegiance_id" class="text-[11px] text-rose-600">{{ form.errors.allegiance_id }}</p>
                    </div>

                    <!-- Step 3: Format (game size) -->
                    <div class="space-y-1.5">
                        <Label>Format <span class="text-muted-foreground">(optional)</span></Label>
                        <p class="text-[11px] text-muted-foreground">
                            <span v-if="linkedGarrison">Inherited from your Garrison.</span>
                            <span v-else>Game size — sets the Commander limit and any Scrip bonus.</span>
                        </p>
                        <Select v-model="formatSelection" :disabled="!!linkedGarrison">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Casual (no format)" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="NONE">Casual (no format)</SelectItem>
                                <SelectItem v-for="f in formats" :key="f.value" :value="f.value">{{ f.label }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="selectedFormat" class="text-[11px] text-muted-foreground">{{ selectedFormat.description }}</p>
                    </div>

                    <!-- Step 4: Envoy (second Allegiance) -->
                    <div class="space-y-1.5">
                        <Label>Envoy <span class="text-muted-foreground">(optional)</span></Label>
                        <p class="text-[11px] text-muted-foreground">
                            A second Allegiance — its Squad units &amp; Assets become hireable (Standard card effects only).
                        </p>
                        <Select v-model="envoySelection">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="No Envoy" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="NONE">No Envoy</SelectItem>
                                <SelectItem v-for="a in envoyOptions" :key="a.id" :value="String(a.id)">
                                    {{ a.name }}<span v-if="a.is_syndicate"> (Syndicate)</span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.envoy_allegiance_id" class="text-[11px] text-rose-600">{{ form.errors.envoy_allegiance_id }}</p>
                    </div>

                    <!-- Step 5: Notes -->
                    <div class="space-y-1.5">
                        <Label for="notes">Notes <span class="text-muted-foreground">(optional)</span></Label>
                        <Textarea id="notes" v-model="form.notes" rows="3" placeholder="Strategy, planned matchups, list-building notes…" />
                    </div>

                    <div class="flex justify-end gap-2 border-t pt-4">
                        <Button as="a" :href="route('tos.companies.index')" variant="ghost" size="sm">Cancel</Button>
                        <Button :disabled="form.processing || !form.name || !form.allegiance_id" size="sm" @click="submit"> Create Company </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
