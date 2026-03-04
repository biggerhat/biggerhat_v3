<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { isMobileDevice } from '@/composables/useMobileDevice';
import { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';

const page = usePage<SharedData>();

const props = defineProps({
    character: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        },
    },
    miniature: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        },
    },
});
</script>

<template>
    <div class="container mx-auto mb-8 flex flex-1 flex-col gap-4 rounded-xl p-4">
        <div class="grid auto-rows-min gap-2 md:grid-cols-8">
            <div v-if="props.miniature.combination_image && !isMobileDevice()" class="flex flex-col space-y-1.5 md:col-span-4">
                <img :src="'/storage/' + props.miniature.combination_image" :alt="miniature.display_name" class="w-full rounded" />
            </div>
            <div v-else class="flex flex-col space-y-1.5 md:col-span-2 md:col-start-2">
                <CharacterCardView :miniature="props.miniature" :show-link="false" />
            </div>
            <div class="flex flex-col space-y-1.5 md:col-span-2">
                <Card class="m-0 w-full rounded-none border-none p-0">
                    <CardHeader class="border-b-2 border-l-2 border-primary px-4 py-2">
                        <CardTitle class="text-lg font-normal">
                            {{ miniature.display_name }}
                        </CardTitle>
                        <CardDescription v-if="miniature.name || miniature.title" class="italic">
                            {{ character.display_name }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="border-l border-r px-0 py-0">
                        <Link
                            :href="route('factions.view', character.faction)"
                            class="text-md m-0 block h-full w-full border-b p-2 hover:bg-secondary"
                            :class="factionBackground(character.faction)"
                        >
                            <span class="m-0 block p-0 text-xs">Faction</span>
                            {{ page['props']['faction_info'][character['faction']]['name'] }}
                        </Link>
                        <div class="border-primary" v-if="character.keywords.length > 0">
                            <Link
                                :href="route('keywords.view', keyword.slug)"
                                class="text-md m-0 block h-full w-full border-b p-2 hover:bg-secondary"
                                v-for="keyword in character.keywords"
                                :key="`keywords-${keyword.id}`"
                            >
                                <span class="m-0 block p-0 text-xs">Keyword</span>
                                {{ keyword.name }}
                            </Link>
                        </div>
                        <div class="border-primary" v-if="character.crew_upgrades.length > 0">
                            <Link
                                :href="route('upgrades.view', upgrade.slug)"
                                class="text-md m-0 block h-full w-full border-b p-2 hover:bg-secondary"
                                v-for="upgrade in character.crew_upgrades"
                                :key="`upgrades-${upgrade.id}`"
                            >
                                <span class="m-0 block p-0 text-xs">Crew Upgrade</span>
                                {{ upgrade.name }}
                            </Link>
                        </div>
                        <div class="border-primary" v-if="character.totem">
                            <Link
                                :href="
                                    route('characters.view', {
                                        character: character.totem.slug,
                                        miniature: character.totem.standard_miniatures[0].id,
                                        slug: character.totem.standard_miniatures[0].slug,
                                    })
                                "
                                class="text-md m-0 block h-full w-full border-b p-2 hover:bg-secondary"
                            >
                                <span class="m-0 block p-0 text-xs">Totem</span>
                                {{ character.totem.display_name }}
                            </Link>
                        </div>
                        <div class="border-primary" v-if="character.is_totem_for">
                            <Link
                                :href="
                                    route('characters.view', {
                                        character: character.is_totem_for.slug,
                                        miniature: character.is_totem_for.standard_miniatures[0].id,
                                        slug: character.is_totem_for.standard_miniatures[0].slug,
                                    })
                                "
                                class="text-md m-0 block h-full w-full border-b p-2 hover:bg-secondary"
                            >
                                <span class="m-0 block p-0 text-xs">Totem For</span>
                                {{ character.is_totem_for.display_name }}
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>
            <div class="flex flex-col space-y-1.5 md:col-span-2">
                <Card class="m-0 w-full !rounded-none border-none p-0">
                    <CardHeader class="border-b-2 border-l-2 border-primary px-4 py-2">
                        <CardTitle class="text-lg font-normal"> Miniature Sculpts </CardTitle>
                    </CardHeader>
                    <CardContent class="border-l border-r px-0 py-0">
                        <Link
                            :href="route('characters.view', { character: character.slug, miniature: sculpt.id, slug: sculpt.slug })"
                            class="text-md m-0 block h-full w-full border-b p-2 hover:bg-secondary"
                            :class="{ 'bg-secondary': sculpt.id === props.miniature.id }"
                            v-for="sculpt in character.miniatures"
                            :key="`sculpt-${sculpt.id}`"
                        >
                            {{ sculpt.display_name }}
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
