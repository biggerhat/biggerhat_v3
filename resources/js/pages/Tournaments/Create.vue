<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NumberField, NumberFieldContent, NumberFieldDecrement, NumberFieldIncrement, NumberFieldInput } from '@/components/ui/number-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Trophy } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    seasons: { value: string; label: string }[];
    encounter_types: { value: string; label: string }[];
}>();

const name = ref('');
const description = ref('');
const eventDate = ref('');
const location = ref('');
const encounterSize = ref(50);
const encounterType = ref('traditional');
const plannedRounds = ref(3);
const season = ref('core');
const roundTimeLimit = ref(135);

const submit = () => {
    router.post(route('tournaments.store'), {
        name: name.value,
        description: description.value || null,
        event_date: eventDate.value,
        location: location.value || null,
        encounter_size: encounterSize.value,
        encounter_type: encounterType.value,
        planned_rounds: plannedRounds.value,
        season: season.value,
        round_time_limit: roundTimeLimit.value,
    });
};
</script>

<template>
    <Head title="Create Tournament" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Create Tournament" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">Set up a new Gaining Grounds event</div>
            </template>
        </PageBanner>

        <div class="container mx-auto sm:px-4">
            <Button variant="ghost" class="mb-4 gap-1.5 text-sm" @click="router.get(route('tournaments.index'))">
                <ArrowLeft class="size-4" /> Back to Tournaments
            </Button>

            <Card class="mx-auto max-w-lg">
                <CardContent class="space-y-6 p-6">
                    <div class="flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Trophy class="size-5" />
                        </div>
                        <div>
                            <h2 class="font-semibold">New Tournament</h2>
                            <p class="text-sm text-muted-foreground">Configure the event details</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="space-y-2">
                            <Label>Tournament Name</Label>
                            <Input v-model="name" placeholder="e.g. Spring Showdown 2026" />
                        </div>

                        <div class="space-y-2">
                            <Label>Description <span class="text-xs text-muted-foreground">(optional)</span></Label>
                            <Textarea v-model="description" placeholder="Event details, rules, prizes..." rows="3" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label>Event Date</Label>
                                <Input v-model="eventDate" type="date" />
                            </div>
                            <div class="space-y-2">
                                <Label>Location <span class="text-xs text-muted-foreground">(optional)</span></Label>
                                <Input v-model="location" placeholder="Venue name / city" />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label>Encounter Size (SS)</Label>
                                <NumberField v-model="encounterSize" :min="20" :max="100" :step="5">
                                    <NumberFieldContent>
                                        <NumberFieldDecrement />
                                        <NumberFieldInput />
                                        <NumberFieldIncrement />
                                    </NumberFieldContent>
                                </NumberField>
                            </div>
                            <div class="space-y-2">
                                <Label>Planned Rounds</Label>
                                <NumberField v-model="plannedRounds" :min="1" :max="7">
                                    <NumberFieldContent>
                                        <NumberFieldDecrement />
                                        <NumberFieldInput />
                                        <NumberFieldIncrement />
                                    </NumberFieldContent>
                                </NumberField>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label>Encounter Type</Label>
                            <Select v-model="encounterType">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="et in encounter_types" :key="et.value" :value="et.value">{{ et.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label>Season</Label>
                                <Select v-model="season">
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="s in seasons" :key="s.value" :value="s.value">{{ s.label }}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="space-y-2">
                                <Label>Round Time Limit (min)</Label>
                                <NumberField v-model="roundTimeLimit" :min="30" :max="300" :step="5">
                                    <NumberFieldContent>
                                        <NumberFieldDecrement />
                                        <NumberFieldInput />
                                        <NumberFieldIncrement />
                                    </NumberFieldContent>
                                </NumberField>
                            </div>
                        </div>
                    </div>

                    <Button class="w-full" :disabled="!name || !eventDate" @click="submit">
                        <Trophy class="mr-2 size-4" /> Create Tournament
                    </Button>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
