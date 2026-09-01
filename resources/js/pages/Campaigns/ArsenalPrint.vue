<script setup lang="ts">
import GameIcon from '@/components/GameIcon.vue';
import GameText from '@/components/GameText.vue';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';

// Bare document view, no app chrome — same reasoning as the headless
// Browsershot capture pages (CaptureCombinedCrewCard.vue etc.): a sidebar/
// header printing alongside the card content isn't "flat black and white."
defineOptions({ layout: null });

interface TriggerData {
    name: string;
    suits: string | null;
    stone_cost: number;
    description: string | null;
}

interface ActionData {
    name: string;
    type: string | null;
    stat: number | string | null;
    stat_suits: string | null;
    range: number | string | null;
    damage: string | null;
    description: string | null;
    is_signature: boolean;
    stone_cost: number;
    triggers: TriggerData[];
}

interface AbilityData {
    name: string;
    suits: string | null;
    description: string | null;
    defensive_ability_type: string | null;
    costs_stone: boolean;
}

interface StatBlock {
    name: string;
    health: number;
    defense: number;
    willpower: number;
    speed: number;
    size: number | null;
    characteristics: string[];
    abilities: AbilityData[];
    actions: ActionData[];
}

const props = defineProps<{
    campaign: { id: number };
    crew: { id: number; share_code: string; name: string; faction: string };
    leader: StatBlock | null;
    totem: StatBlock | null;
    models: { name: string; cost: number | null; station: string | null }[];
    equipment: { name: string; br: number | null; cc: number | null; description: string | null }[];
}>();

const printPage = () => window.print();
</script>

<template>
    <Head :title="`Print — ${crew.name}`" />

    <!-- Print button (hidden in print) -->
    <div class="mx-auto max-w-4xl p-4 print:hidden">
        <div class="mb-4 flex items-center justify-between">
            <Link :href="route('campaigns.crews.arsenal.show', [props.campaign.id, crew.share_code])">
                <Button variant="ghost" size="sm" class="gap-1.5"> <ArrowLeft class="size-4" /> Back to Arsenal Sheet </Button>
            </Link>
            <Button class="gap-1.5" @click="printPage"> <Printer class="size-4" /> Print / Save as PDF </Button>
        </div>
    </div>

    <!-- Printable content -->
    <div class="mx-auto max-w-4xl p-4 text-[11px] leading-snug text-black print:p-0 print:text-[9px]">
        <div class="mb-3 flex items-center justify-between border-b border-black/20 pb-2">
            <div class="text-lg font-bold print:text-sm">{{ crew.name }}</div>
            <div class="text-right text-xs text-black/60 print:text-[8px]">{{ crew.faction.replace('_', ' ') }} &middot; biggerhat.net</div>
        </div>

        <!-- Leader / Totem stat blocks -->
        <div v-if="leader || totem" class="mb-3 grid grid-cols-1 gap-3 sm:grid-cols-2 print:grid-cols-2 print:gap-2">
            <div
                v-for="block in [leader, totem].filter(Boolean) as StatBlock[]"
                :key="block.name"
                class="break-inside-avoid rounded border border-black/20 p-2 print:rounded-none print:p-1.5"
            >
                <div class="mb-1 flex items-baseline justify-between">
                    <span class="text-sm font-bold print:text-[10px]">{{ block.name }}</span>
                    <span v-if="block.characteristics.length" class="text-[9px] text-black/60 print:text-[7px]">{{
                        block.characteristics.join(', ')
                    }}</span>
                </div>
                <div class="mb-1.5 flex gap-3 text-[10px] font-medium print:text-[8px]">
                    <span>Df {{ block.defense }}</span>
                    <span>Wp {{ block.willpower }}</span>
                    <span>Spd {{ block.speed }}</span>
                    <span>Hp {{ block.health }}</span>
                    <span v-if="block.size">Sz {{ block.size }}</span>
                </div>

                <div v-if="block.abilities.length" class="mb-1.5 space-y-0.5">
                    <div v-for="ab in block.abilities" :key="ab.name">
                        <span class="font-bold">
                            <GameIcon v-if="ab.suits" :type="ab.suits" class-name="inline-block h-2.5" />
                            {{ ab.name }}
                        </span>
                        <span v-if="ab.defensive_ability_type" class="text-[9px] text-black/60 print:text-[7px]">
                            ({{ ab.defensive_ability_type }})</span
                        >
                        <span v-if="ab.description" class="text-black/70"> - <GameText :text="ab.description" icon-class="h-2.5 inline-block align-text-bottom" /></span>
                    </div>
                </div>

                <div v-if="block.actions.length" class="space-y-1">
                    <div v-for="action in block.actions" :key="action.name" class="border-t border-dashed border-black/10 pt-0.5">
                        <div class="flex items-baseline gap-1">
                            <GameIcon v-if="action.type" :type="action.type" class-name="inline-block h-2.5 shrink-0" />
                            <span class="font-bold">{{ action.name }}</span>
                            <span v-if="action.stat" class="text-black/70"
                                >{{ action.stat }}<GameIcon v-if="action.stat_suits" :type="action.stat_suits" class-name="inline-block h-2.5"
                            /></span>
                            <span v-if="action.range" class="text-black/70">Rg {{ action.range }}</span>
                            <span v-if="action.damage" class="text-black/70">Dmg {{ action.damage }}</span>
                        </div>
                        <div v-if="action.description" class="text-black/70">
                            <GameText :text="action.description" icon-class="h-2.5 inline-block align-text-bottom" />
                        </div>
                        <div v-if="action.triggers.length" class="ml-2 space-y-0.5">
                            <div v-for="t in action.triggers" :key="t.name">
                                <GameIcon v-if="t.suits" :type="t.suits" class-name="inline-block h-2.5" />
                                <span class="font-semibold">{{ t.name }}</span>
                                <span v-if="t.description" class="text-black/70"> - <GameText :text="t.description" icon-class="h-2.5 inline-block align-text-bottom" /></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Arsenal roster -->
        <div v-if="models.length" class="mb-3">
            <p class="mb-1 text-[10px] font-bold uppercase tracking-wide print:text-[8px]">Arsenal ({{ models.length }})</p>
            <div class="grid grid-cols-2 gap-x-4 gap-y-0.5 sm:grid-cols-3 print:grid-cols-4">
                <div v-for="(m, i) in models" :key="`${m.name}-${i}`" class="flex justify-between border-b border-dotted border-black/20">
                    <span>{{ m.name }}</span>
                    <span v-if="m.cost != null" class="text-black/60">{{ m.cost }}ss</span>
                </div>
            </div>
        </div>

        <!-- Equipment locker -->
        <div v-if="equipment.length">
            <p class="mb-1 text-[10px] font-bold uppercase tracking-wide print:text-[8px]">Equipment ({{ equipment.length }})</p>
            <div class="space-y-0.5">
                <div v-for="(e, i) in equipment" :key="`${e.name}-${i}`" class="border-b border-dotted border-black/20 pb-0.5">
                    <div class="flex justify-between font-semibold">
                        <span>{{ e.name }}</span>
                        <span class="text-black/60">
                            <template v-if="e.br != null">BR {{ e.br }}</template>
                            <template v-if="e.cc != null"> &middot; {{ e.cc }} cc</template>
                        </span>
                    </div>
                    <div v-if="e.description" class="text-black/70">
                        <GameText :text="e.description" icon-class="h-2.5 inline-block align-text-bottom" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
