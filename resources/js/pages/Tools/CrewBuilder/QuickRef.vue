<script setup lang="ts">
import GameIcon from '@/components/GameIcon.vue';
import GameText from '@/components/GameText.vue';
import { Button } from '@/components/ui/button';
import { Head } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';

defineProps<{
    crew: {
        name: string;
        faction: string;
        encounter_size: number;
        soulstone_pool: number;
        master: string;
    };
    members: {
        display_name: string;
        cost: number;
        health: number;
        defense: number;
        defense_suit: string | null;
        willpower: number;
        willpower_suit: string | null;
        speed: number;
        size: number | null;
        station: string | null;
        category: string;
        keywords: string[];
        characteristics: string[];
        abilities: { name: string; suits: string | null; description: string | null; defensive_ability_type: string | null; costs_stone: boolean }[];
        actions: {
            name: string;
            type: string | null;
            stat: string | null;
            stat_suits: string | null;
            range: string | null;
            damage: string | null;
            description: string | null;
            is_signature: boolean;
            stone_cost: number;
            triggers: { name: string; suits: string | null; stone_cost: number; description: string | null }[];
        }[];
    }[];
}>();

const printPage = () => window.print();
</script>

<template>
    <Head :title="`Quick Ref - ${crew.name}`" />

    <!-- Print button (hidden in print) -->
    <div class="mx-auto max-w-4xl p-4 print:hidden">
        <div class="mb-4 flex items-center justify-between">
            <Button variant="ghost" size="sm" class="gap-1.5" @click="$inertia?.visit ? undefined : history.back()">
                <ArrowLeft class="size-4" /> Back
            </Button>
            <Button class="gap-1.5" @click="printPage"> <Printer class="size-4" /> Print Quick Reference </Button>
        </div>
    </div>

    <!-- Printable content -->
    <div class="mx-auto max-w-4xl p-4 text-[11px] leading-snug print:p-0 print:text-[9px]">
        <!-- Crew header -->
        <div class="mb-3 flex items-center justify-between border-b pb-2">
            <div>
                <div class="text-lg font-bold print:text-sm">{{ crew.name }}</div>
                <div class="text-xs text-muted-foreground print:text-[8px]">
                    {{ crew.master }} &middot; {{ crew.faction.replace('_', ' ') }} &middot; {{ crew.encounter_size }}ss &middot; Pool:
                    {{ crew.soulstone_pool }}ss
                </div>
            </div>
            <div class="text-right text-xs text-muted-foreground print:text-[8px]">{{ members.length }} models &middot; biggerhat.net</div>
        </div>

        <!-- Model cards in 2-column grid -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 print:grid-cols-2 print:gap-2">
            <div
                v-for="member in members"
                :key="member.display_name"
                class="rounded border p-2 print:break-inside-avoid print:rounded-none print:border-black/20 print:p-1.5"
            >
                <!-- Name + stats header -->
                <div class="mb-1 flex items-baseline justify-between">
                    <span class="text-sm font-bold print:text-[10px]">{{ member.display_name }}</span>
                    <span v-if="member.cost" class="text-[10px] font-medium text-muted-foreground print:text-[8px]">{{ member.cost }}ss</span>
                </div>

                <!-- Stat line -->
                <div class="mb-1.5 flex gap-3 text-[10px] font-medium print:text-[8px]">
                    <span
                        >Df {{ member.defense
                        }}<template v-if="member.defense_suit"><GameIcon :type="member.defense_suit" class-name="inline-block h-2.5" /></template
                    ></span>
                    <span
                        >Wp {{ member.willpower
                        }}<template v-if="member.willpower_suit"><GameIcon :type="member.willpower_suit" class-name="inline-block h-2.5" /></template
                    ></span>
                    <span>Spd {{ member.speed }}</span>
                    <span>Hp {{ member.health }}</span>
                    <span v-if="member.size">Sz {{ member.size }}</span>
                </div>

                <!-- Keywords -->
                <div v-if="member.keywords.length" class="mb-1 text-[9px] text-muted-foreground print:text-[7px]">
                    <span class="font-semibold">KW:</span> {{ member.keywords.join(', ') }}
                </div>

                <!-- Abilities -->
                <div v-if="member.abilities.length" class="mb-1.5 space-y-0.5">
                    <div v-for="ab in member.abilities" :key="ab.name">
                        <span class="font-bold">
                            <GameIcon v-if="ab.suits" :type="ab.suits" class-name="inline-block h-2.5" />
                            {{ ab.name }}
                        </span>
                        <span v-if="ab.defensive_ability_type" class="text-[9px] text-muted-foreground print:text-[7px]">
                            ({{ ab.defensive_ability_type }})</span
                        >
                        <span v-if="ab.description" class="text-muted-foreground">
                            - <GameText :text="ab.description" icon-class="h-2.5 inline-block align-text-bottom"
                        /></span>
                    </div>
                </div>

                <!-- Actions -->
                <div v-if="member.actions.length" class="space-y-1">
                    <div v-for="action in member.actions" :key="action.name" class="border-t border-dashed pt-0.5 print:border-black/10">
                        <div class="flex items-baseline gap-1">
                            <GameIcon v-if="action.type" :type="action.type" class-name="inline-block h-2.5 shrink-0" />
                            <span class="font-bold">{{ action.name }}</span>
                            <span v-if="action.stat" class="text-muted-foreground"
                                >{{ action.stat }}<GameIcon v-if="action.stat_suits" :type="action.stat_suits" class-name="inline-block h-2.5"
                            /></span>
                            <span v-if="action.range" class="text-muted-foreground">Rg {{ action.range }}</span>
                            <span v-if="action.damage" class="text-muted-foreground">Dmg {{ action.damage }}</span>
                        </div>
                        <div v-if="action.description" class="text-muted-foreground">
                            <GameText :text="action.description" icon-class="h-2.5 inline-block align-text-bottom" />
                        </div>
                        <div v-if="action.triggers.length" class="ml-2 space-y-0.5">
                            <div v-for="t in action.triggers" :key="t.name">
                                <GameIcon v-if="t.suits" :type="t.suits" class-name="inline-block h-2.5" />
                                <span class="font-semibold">{{ t.name }}</span>
                                <span v-if="t.description" class="text-muted-foreground">
                                    - <GameText :text="t.description" icon-class="h-2.5 inline-block align-text-bottom"
                                /></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
