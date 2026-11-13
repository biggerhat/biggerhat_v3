<script setup lang="ts">
import AbilityCard from '@/components/AbilityCard.vue';
import ActionCard from '@/components/ActionCard.vue';
import CharacterCardView from '@/components/CharacterCardView.vue';
import PowerBarBubbles from '@/components/Game/PowerBarBubbles.vue';
import GameIcon from '@/components/GameIcon.vue';
import { Badge } from '@/components/ui/badge';
import { factionDeep } from '@/composables/useFactionColor';
import { computed } from 'vue';
import {
    ArrowUpCircle,
    Banana,
    Check,
    Circle,
    Eye,
    Footprints,
    Heart,
    HeartCrack,
    Minus,
    Plus,
    Puzzle,
    Replace,
    Shield,
    ShieldAlert,
    X,
} from 'lucide-vue-next';

/**
 * One living crew model in the play screen: activation, name, stats, health (pips and stepper), tokens, inline card and attached upgrades.
 * It renders for both sides; `editable` is the only difference (your own crew unless you are observing, the opponent's only in a solo game).
 * The component owns no game state: every change is emitted and Games/Show.vue performs it, exactly as it did before the extraction.
 * Typed `any` for the member like the rest of the play screen (Show.vue has two shadowing CrewMember interfaces).
 */
const props = defineProps<{
    member: any;
    /** The owning player's faction, used for the row colour when the model carries none. */
    fallbackFaction?: string | null;
    editable: boolean;
    /** The inline card preview is open for this model. */
    cardOpen: boolean;
    isCampaign: boolean;
    upgradePowerMax: (upgradeId: number) => number;
    lootCardById: (id: number) => { value_label?: string; suit?: string | null } | null;
    lootCardSuitIcon: (suit: string | null | undefined) => string | null;
}>();

defineEmits<{
    'toggle-activated': [];
    'open-upgrades': [];
    'open-injury': [];
    'open-tokens': [];
    replace: [];
    preview: [];
    'toggle-card': [];
    'health-pip': [pip: number];
    'health-delta': [delta: number];
    'token-info': [tokenId: number];
    'remove-token': [tokenId: number];
    'upgrade-preview': [upgrade: any];
    'remove-upgrade': [upgradeId: number];
    'power-bar': [upgradeId: number, value: number];
}>();

// Campaign Leader/Totem (pg 31, 52): a server-rendered card image once generated, else the raw actions/abilities as a stat block.
// Defense / Willpower / Speed / Size, shown on the pips row on phones and on its own row from `sm`.
const stats = computed(() =>
    [
        { title: 'Defense', icon: Shield, value: props.member.defense },
        { title: 'Willpower', icon: ShieldAlert, value: props.member.willpower },
        { title: 'Speed', icon: Footprints, value: props.member.speed },
        { title: 'Size', icon: Banana, value: props.member.size },
    ].filter((x) => x.value),
);

const hasCustomCharacterCard = (member: any): boolean =>
    !!member.custom_character?.front_image ||
    (member.custom_character?.actions?.length ?? 0) > 0 ||
    (member.custom_character?.abilities?.length ?? 0) > 0;
</script>

<template>
    <div
        :class="factionDeep(member.faction ?? fallbackFaction ?? '')"
        class="rounded-md border border-white/20 px-2 py-1.5 text-white"
        :style="member.is_activated ? 'opacity: 0.7' : ''"
    >
        <div class="min-w-0 flex-1">
            <!-- Line 1: Activation + Name (+ desktop action buttons) -->
            <div class="flex items-center gap-1">
                <template v-if="editable">
                    <button
                        class="shrink-0 rounded p-2.5 hover:bg-white/20 sm:p-0.5"
                        @click="$emit('toggle-activated')"
                        :title="member.is_activated ? 'Mark unactivated' : 'Mark activated'"
                    >
                        <Check v-if="member.is_activated" class="size-5 text-green-400 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)] sm:size-3.5" />
                        <Circle v-else class="size-5 text-white/50 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)] sm:size-3.5" />
                    </button>
                    <button
                        class="hidden shrink-0 rounded bg-black/30 p-1.5 text-amber-200 hover:bg-black/50 sm:inline-flex"
                        aria-label="Upgrades"
                        title="Upgrades"
                        @click.stop="$emit('open-upgrades')"
                    >
                        <ArrowUpCircle class="size-4" />
                    </button>
                    <button
                        v-if="isCampaign"
                        class="hidden shrink-0 rounded bg-black/30 p-1.5 text-rose-200 hover:bg-black/50 sm:inline-flex"
                        aria-label="Attach Injury"
                        title="Attach Injury"
                        @click.stop="$emit('open-injury')"
                    >
                        <HeartCrack class="size-4" />
                    </button>
                    <button
                        class="hidden shrink-0 rounded bg-black/30 p-1.5 text-cyan-200 hover:bg-black/50 sm:inline-flex"
                        aria-label="Tokens"
                        title="Tokens"
                        @click.stop="$emit('open-tokens')"
                    >
                        <Puzzle class="size-4" />
                    </button>
                    <button
                        class="hidden shrink-0 rounded bg-black/30 p-1.5 text-blue-200 hover:bg-black/50 sm:inline-flex"
                        aria-label="Replace"
                        title="Replace"
                        @click.stop="$emit('replace')"
                    >
                        <Replace class="size-4" />
                    </button>
                </template>
                <template v-else>
                    <Check v-if="member.is_activated" class="size-3.5 shrink-0 text-green-400 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]" />
                    <Circle v-else class="size-3.5 shrink-0 text-white/50 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]" />
                </template>
                <button class="min-w-0 cursor-pointer truncate text-base font-semibold hover:underline" @click="$emit('preview')">
                    {{ member.display_name }}
                </button>
                <button
                    v-if="member.front_image || hasCustomCharacterCard(member)"
                    class="shrink-0 rounded p-1 hover:bg-white/20 sm:ml-auto"
                    aria-label="Toggle card preview"
                    title="Toggle card preview"
                    @click.stop="$emit('toggle-card')"
                >
                    <Eye class="size-4 drop-shadow-[0_1px_2px_rgba(0,0,0,0.6)]" :class="cardOpen ? 'text-amber-300' : 'text-white'" />
                </button>
                <!-- Phones: health stepper on the name line (from `sm` it sits on the stats row below, as before). -->
                <div v-if="editable" class="ml-auto flex shrink-0 items-center gap-0.5 sm:hidden">
                    <button
                        :aria-label="`Decrease health of ${member.display_name}`"
                        class="rounded border p-2.5 hover:bg-muted"
                        @click="$emit('health-delta', -1)"
                    >
                        <Minus class="size-4" />
                    </button>
                    <span class="flex min-w-[3rem] items-center justify-center gap-0.5 text-sm font-bold">
                        <Heart class="size-3.5" :class="member.current_health <= Math.ceil(member.max_health / 2) ? 'text-red-300' : ''" />
                        {{ member.current_health }}/{{ member.max_health }}
                    </span>
                    <button
                        :aria-label="`Increase health of ${member.display_name}`"
                        class="rounded border p-2.5 hover:bg-muted"
                        @click="$emit('health-delta', 1)"
                    >
                        <Plus class="size-4" />
                    </button>
                </div>
                <span v-else class="ml-auto flex shrink-0 items-center gap-0.5 text-sm font-bold sm:hidden">
                    <Heart class="size-3.5" :class="member.current_health <= Math.ceil(member.max_health / 2) ? 'text-red-300' : ''" />
                    {{ member.current_health }}/{{ member.max_health }}
                </span>
            </div>
            <!-- Line 2 (phones): health pips, stats and the action buttons -->
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 pl-9 sm:hidden">
                <div class="flex gap-0.5">
                    <button
                        v-for="pip in member.max_health"
                        :key="'hp-m-' + pip"
                        type="button"
                        class="relative size-3.5 rounded-sm transition-colors after:absolute after:-inset-x-px after:-inset-y-2.5 after:content-['']"
                        :class="[
                            pip <= member.current_health
                                ? member.current_health <= Math.ceil(member.max_health / 2)
                                    ? 'bg-red-400/90'
                                    : 'bg-white/60'
                                : 'bg-black/30 ring-1 ring-inset ring-white/10',
                            !editable ? 'cursor-default' : 'cursor-pointer hover:bg-white/80',
                        ]"
                        :disabled="!editable"
                        :aria-label="`Set health to ${pip} of ${member.max_health}`"
                        @click.stop="$emit('health-pip', pip)"
                    />
                </div>

                <div v-if="stats.length" class="flex gap-1.5 text-sm font-semibold text-white drop-shadow-[0_1px_2px_rgba(0,0,0,0.6)]">
                    <span v-for="stat in stats" :key="stat.title" :title="stat.title">
                        <component :is="stat.icon" class="mr-0.5 inline size-4" />{{ stat.value }}
                    </span>
                </div>
                <div v-if="editable" class="ml-auto flex shrink-0 gap-1 sm:hidden">
                    <button
                        class="flex items-center gap-1 rounded bg-black/60 p-1.5 text-neutral-50 active:bg-black/70"
                        title="Upgrades"
                        @click.stop="$emit('open-upgrades')"
                    >
                        <ArrowUpCircle class="size-3.5" /><span class="sr-only">Upgrades</span>
                    </button>
                    <button
                        v-if="isCampaign"
                        class="flex items-center gap-1 rounded bg-black/30 p-1.5 text-rose-200 active:bg-black/50"
                        @click.stop="$emit('open-injury')"
                    >
                        <HeartCrack class="size-3.5" /><span class="sr-only">Injury</span>
                    </button>
                    <button
                        class="flex items-center gap-1 rounded bg-black/60 p-1.5 text-neutral-50 active:bg-black/70"
                        title="Tokens"
                        @click.stop="$emit('open-tokens')"
                    >
                        <Puzzle class="size-3.5" /><span class="sr-only">Tokens</span>
                    </button>
                    <button
                        class="flex items-center gap-1 rounded bg-black/60 p-1.5 text-neutral-50 active:bg-black/70"
                        title="Replace"
                        @click.stop="$emit('replace')"
                    >
                        <Replace class="size-3.5" /><span class="sr-only">Replace</span>
                    </button>
                </div>
            </div>
            <!-- Line 3 (from `sm`): stats + pips + health controls on one row. Phones show these on lines 1-2 above. -->
            <div class="mt-0.5 hidden flex-wrap items-center gap-x-2 gap-y-1 sm:flex sm:pl-6">
                <div
                    v-if="stats.length || member.characteristics?.length"
                    class="flex gap-1.5 text-xs font-semibold text-white drop-shadow-[0_1px_2px_rgba(0,0,0,0.6)]"
                >
                    <span v-for="stat in stats" :key="stat.title" :title="stat.title">
                        <component :is="stat.icon" class="mr-0.5 inline size-3.5" />{{ stat.value }}
                    </span>
                    <span
                        v-if="member.characteristics?.length"
                        class="text-2xs font-normal italic text-white/70"
                        :title="member.characteristics.join(', ')"
                        >({{ member.characteristics.join(', ') }})</span
                    >
                </div>
                <!-- Desktop: health pips inline -->
                <div class="hidden min-w-0 flex-wrap gap-0.5 sm:flex">
                    <button
                        v-for="pip in member.max_health"
                        :key="'hp-' + pip"
                        type="button"
                        class="size-3 rounded-sm transition-colors"
                        :class="[
                            pip <= member.current_health
                                ? member.current_health <= Math.ceil(member.max_health / 2)
                                    ? 'bg-red-400/90'
                                    : 'bg-white/60'
                                : 'bg-black/30 ring-1 ring-inset ring-white/10',
                            !editable ? 'cursor-default' : 'cursor-pointer hover:bg-white/80',
                        ]"
                        :disabled="!editable"
                        :aria-label="`Set health to ${pip} of ${member.max_health}`"
                        @click.stop="$emit('health-pip', pip)"
                    />
                </div>
                <template v-if="editable">
                    <div class="ml-auto flex shrink-0 items-center gap-0.5">
                        <button
                            :aria-label="`Decrease health of ${member.display_name}`"
                            class="rounded border p-2 hover:bg-muted sm:p-1"
                            @click="$emit('health-delta', -1)"
                        >
                            <Minus class="size-4 sm:size-3.5" />
                        </button>
                        <span class="flex min-w-[3rem] items-center justify-center gap-0.5 text-sm font-bold">
                            <Heart class="size-3.5" :class="member.current_health <= Math.ceil(member.max_health / 2) ? 'text-red-300' : ''" />
                            {{ member.current_health }}/{{ member.max_health }}
                        </span>
                        <button
                            :aria-label="`Increase health of ${member.display_name}`"
                            class="rounded border p-2 hover:bg-muted sm:p-1"
                            @click="$emit('health-delta', 1)"
                        >
                            <Plus class="size-4 sm:size-3.5" />
                        </button>
                    </div>
                </template>
                <span v-else class="ml-auto flex shrink-0 items-center justify-center gap-0.5 text-sm font-bold">
                    <Heart class="size-3.5" :class="member.current_health <= Math.ceil(member.max_health / 2) ? 'text-red-300' : ''" />
                    {{ member.current_health }}/{{ member.max_health }}
                </span>
            </div>
        </div>
        <!-- Token badges -->
        <div v-if="member.attached_tokens?.length" class="mt-1 flex flex-wrap gap-1">
            <Badge
                v-for="token in member.attached_tokens"
                :key="token.id"
                variant="secondary"
                class="group cursor-pointer gap-0.5 border border-cyan-500/50 bg-cyan-900/60 px-1.5 py-0.5 text-xs font-medium text-cyan-200 transition-colors hover:bg-cyan-800/70"
                @click="$emit('token-info', token.id)"
            >
                {{ token.name }}
                <button
                    v-if="editable"
                    class="ml-0.5 shrink-0 rounded-full bg-white/15 p-0.5 text-white/80 transition-colors hover:bg-red-500/60 hover:text-white"
                    aria-label="Remove token"
                    @click.stop="$emit('remove-token', token.id)"
                >
                    <X class="size-2.5" />
                </button>
            </Badge>
        </div>
        <!-- Inline card preview — kept above the attached-upgrades list so a
             unit with many attachments never pushes it out of view. -->
        <Transition
            enter-active-class="transition-all duration-300 ease-out"
            leave-active-class="transition-all duration-200 ease-in"
            enter-from-class="max-h-0 opacity-0"
            enter-to-class="max-h-[600px] opacity-100"
            leave-from-class="max-h-[600px] opacity-100"
            leave-to-class="max-h-0 opacity-0"
        >
            <div
                v-if="cardOpen && member.front_image"
                class="mt-2 overflow-hidden [&_img]:max-h-[70dvh] [&_img]:w-auto [&_img]:object-contain xl:[&_img]:max-h-[50dvh]"
            >
                <CharacterCardView
                    :miniature="{
                        id: member.id,
                        display_name: member.display_name,
                        slug: '',
                        front_image: member.front_image,
                        back_image: member.back_image,
                    }"
                    :show-link="false"
                    :show-collection="false"
                />
            </div>
            <!-- Campaign Leader/Totem (pg 31, 52) — server-rendered card image, once generated. -->
            <div
                v-else-if="cardOpen && member.custom_character?.front_image"
                class="mt-2 overflow-hidden [&_img]:max-h-[70dvh] [&_img]:w-auto [&_img]:object-contain xl:[&_img]:max-h-[50dvh]"
            >
                <CharacterCardView
                    :miniature="{
                        id: member.custom_character.id,
                        display_name: member.custom_character.display_name,
                        slug: '',
                        front_image: member.custom_character.front_image,
                        back_image: member.custom_character.back_image,
                        card_image_generated_at: member.custom_character.card_image_generated_at,
                    }"
                    :show-link="false"
                    :show-collection="false"
                />
            </div>
            <!-- No image generated yet — render the JSON stat block instead. -->
            <div v-else-if="cardOpen && hasCustomCharacterCard(member)" class="mt-2 space-y-2 overflow-hidden">
                <ActionCard v-for="(a, i) in member.custom_character.actions" :key="`action-${member.id}-${i}`" :action="a" :hide-footer="true" />
                <AbilityCard
                    v-for="(ab, i) in member.custom_character.abilities"
                    :key="`ability-${member.id}-${i}`"
                    :ability="ab"
                    :hide-footer="true"
                />
            </div>
        </Transition>
        <!-- Attached upgrades — collapsible so a unit with several
             Equipment/Injury attachments doesn't grow the column
             unboundedly (mirrors the References <details> pattern below). -->
        <details v-if="member.attached_upgrades?.length" open class="mt-1 rounded-lg border border-white/10">
            <summary class="cursor-pointer px-2 py-1 text-2xs font-medium text-white/70 hover:text-white">
                Attached Upgrades ({{ member.attached_upgrades.length }})
            </summary>
            <div class="space-y-0.5 p-1.5 pt-0.5">
                <div
                    v-for="upgrade in member.attached_upgrades"
                    :key="'au-' + upgrade.id"
                    class="rounded bg-black/20 px-2 py-1 text-sm"
                    :class="upgrade.front_image || upgrade.description ? 'cursor-pointer hover:bg-black/30' : ''"
                >
                    <div
                        class="flex items-center gap-1.5"
                        :role="upgrade.front_image || upgrade.description ? 'button' : undefined"
                        :tabindex="upgrade.front_image || upgrade.description ? 0 : undefined"
                        @click="$emit('upgrade-preview', upgrade)"
                        @keydown.enter="$emit('upgrade-preview', upgrade)"
                    >
                        <ArrowUpCircle class="size-3.5 shrink-0 text-amber-300" />
                        <template v-if="upgrade.loot_side">
                            <span
                                class="inline-flex h-4 min-w-4 shrink-0 items-center justify-center rounded bg-amber-400 px-1 text-2xs font-bold uppercase text-black"
                                :title="`Side ${upgrade.loot_side.toUpperCase()} active`"
                            >
                                {{ upgrade.loot_side.toUpperCase() }}
                            </span>
                            <span
                                v-if="lootCardById(upgrade.loot_card_id)"
                                class="inline-flex h-4 shrink-0 items-center gap-0.5 rounded border border-white/30 bg-black/30 px-1 font-mono text-2xs tabular-nums"
                            >
                                {{ lootCardById(upgrade.loot_card_id)?.value_label
                                }}<GameIcon
                                    v-if="lootCardSuitIcon(lootCardById(upgrade.loot_card_id)?.suit)"
                                    :type="lootCardSuitIcon(lootCardById(upgrade.loot_card_id)?.suit) as string"
                                    class-name="h-2.5 inline-block"
                                />
                            </span>
                        </template>
                        <span class="min-w-0 flex-1 truncate font-medium">{{ upgrade.name }}</span>
                        <button
                            v-if="editable"
                            class="shrink-0 rounded-full bg-white/15 p-0.5 text-white/80 transition-colors hover:bg-red-500/60 hover:text-white"
                            aria-label="Remove upgrade"
                            @click.stop="$emit('remove-upgrade', upgrade.id)"
                        >
                            <X class="size-3" />
                        </button>
                    </div>
                    <PowerBarBubbles
                        v-if="upgradePowerMax(upgrade.id) > 0"
                        class="mt-1 pl-5"
                        :max="upgradePowerMax(upgrade.id)"
                        :current="upgrade.current_power_bar ?? upgradePowerMax(upgrade.id)"
                        :readonly="!editable"
                        compact
                        @update="(v) => $emit('power-bar', upgrade.id, v)"
                    />
                </div>
            </div>
        </details>
    </div>
</template>
