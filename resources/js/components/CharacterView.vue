<script setup lang="ts">
import {usePage} from '@inertiajs/vue3';
import {Card, CardContent, CardDescription, CardHeader, CardTitle} from "@/components/ui/card";
import {SharedData} from "@/types";
import CharacterCardView from "@/components/CharacterCardView.vue";

const page = usePage<SharedData>();

function isMobileDevice() {
    return /Mobi|Android/i.test(navigator.userAgent);
}

const props = defineProps({
    character: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        }
    },
    miniature: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        }
    },
})
</script>

<template>
    <div class="container flex flex-1 flex-col gap-4 rounded-xl p-4 mb-8 mx-auto">
        <div class="grid auto-rows-min gap-2 md:grid-cols-8">
            <div class="flex flex-col space-y-1.5 md:col-span-4" v-if="props.miniature.combination_image && !isMobileDevice()">
                <img :src='"/storage/" + props.miniature.combination_image' :alt="miniature.display_name" class="rounded">
            </div>
            <div v-else class="flex flex-col space-y-1.5 md:col-span-2 md:col-start-2">
                <CharacterCardView :miniature="props.miniature" show-link="false" />
            </div>
            <div class="flex flex-col space-y-1.5 md:col-span-2">
                <Card class="w-full rounded-none border-none m-0 p-0">
                    <CardHeader class="px-4 py-2 border-primary border-l-2 border-b-2">
                        <CardTitle class="text-lg font-normal">
                            {{ miniature.display_name }}
                        </CardTitle>
                        <CardDescription v-if="miniature.name || miniature.title" class="italic">
                            {{ character.display_name }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="px-0 border-l border-r py-0">
                        <Link v-if="character.faction === 'bayou'" :href="route('factions.view', character.faction)" class="bg-bayou block p-2 m-0 w-full h-full text-md border-b hover:bg-secondary">
                            <span class="block m-0 p-0 text-xs">Faction</span>
                            {{ page['props']['faction_info'][character['faction']]['name'] }}
                        </Link>
                        <Link v-else-if="character.faction === 'arcanists'" :href="route('factions.view', character.faction)" class="bg-arcanists block p-2 m-0 w-full h-full text-md border-b hover:bg-secondary">
                            <span class="block m-0 p-0 text-xs">Faction</span>
                            {{ page['props']['faction_info'][character['faction']]['name'] }}
                        </Link>
                        <Link v-else-if="character.faction === 'explorers_society'" :href="route('factions.view', character.faction)" class="bg-explorerssociety block p-2 m-0 w-full h-full text-md border-b hover:bg-secondary">
                            <span class="block m-0 p-0 text-xs">Faction</span>
                            {{ page['props']['faction_info'][character['faction']]['name'] }}
                        </Link>
                        <Link v-else-if="character.faction === 'guild'" :href="route('factions.view', character.faction)" class="bg-guild block p-2 m-0 w-full h-full text-md border-b hover:bg-secondary">
                            <span class="block m-0 p-0 text-xs">Faction</span>
                            {{ page['props']['faction_info'][character['faction']]['name'] }}
                        </Link>
                        <Link v-else-if="character.faction === 'neverborn'" :href="route('factions.view', character.faction)" class="bg-neverborn block p-2 m-0 w-full h-full text-md border-b hover:bg-secondary">
                            <span class="block m-0 p-0 text-xs">Faction</span>
                            {{ page['props']['faction_info'][character['faction']]['name'] }}
                        </Link>
                        <Link v-else-if="character.faction === 'outcasts'" :href="route('factions.view', character.faction)" class="bg-outcasts block p-2 m-0 w-full h-full text-md border-b hover:bg-secondary">
                            <span class="block m-0 p-0 text-xs">Faction</span>
                            {{ page['props']['faction_info'][character['faction']]['name'] }}
                        </Link>
                        <Link v-else-if="character.faction === 'resurrectionists'" :href="route('factions.view', character.faction)" class="bg-resurrectionists block p-2 m-0 w-full h-full text-md border-b hover:bg-secondary">
                            <span class="block m-0 p-0 text-xs">Faction</span>
                            {{ page['props']['faction_info'][character['faction']]['name'] }}
                        </Link>
                        <Link v-else-if="character.faction === 'ten_thunders'" :href="route('factions.view', character.faction)" class="bg-tenthunders block p-2 m-0 w-full h-full text-md border-b hover:bg-secondary">
                            <span class="block m-0 p-0 text-xs">Faction</span>
                            {{ page['props']['faction_info'][character['faction']]['name'] }}
                        </Link>
                        <div class="border-primary" v-if="character.keywords.length > 0">
                            <Link :href="route('keywords.view', keyword.slug)" class="block p-2 m-0 w-full h-full border-b hover:bg-secondary text-md" v-for="keyword in character.keywords">
                                <span class="block m-0 p-0 text-xs">Keyword</span>
                                {{ keyword.name }}
                            </Link>
                        </div>
                        <div class="border-primary" v-if="character.crew_upgrades.length > 0">
                            <Link :href="route('upgrades.view', upgrade.slug)" class="block p-2 m-0 w-full h-full border-b hover:bg-secondary text-md" v-for="upgrade in character.crew_upgrades">
                                <span class="block m-0 p-0 text-xs">Crew Upgrade</span>
                                {{ upgrade.name }}
                            </Link>
                        </div>
                        <div class="border-primary" v-if="character.totem">
                            <Link :href="route('characters.view', {character: character.totem.slug, miniature: character.totem.standard_miniatures[0].id, slug: character.totem.standard_miniatures[0].slug})" class="block p-2 m-0 w-full h-full border-b hover:bg-secondary text-md">
                                <span class="block m-0 p-0 text-xs">Totem</span>
                                {{ character.totem.display_name }}
                            </Link>
                        </div>
                        <div class="border-primary" v-if="character.is_totem_for">
                            <Link :href="route('characters.view', {character: character.is_totem_for.slug, miniature: character.is_totem_for.standard_miniatures[0].id, slug: character.is_totem_for.standard_miniatures[0].slug})" class="block p-2 m-0 w-full h-full border-b hover:bg-secondary text-md">
                                <span class="block m-0 p-0 text-xs">Totem For</span>
                                {{ character.is_totem_for.display_name }}
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>
            <div class="flex flex-col space-y-1.5 md:col-span-2">
                <Card class="w-full border-none m-0 p-0 !rounded-none">
                    <CardHeader class="px-4 py-2 border-primary border-l-2 border-b-2">
                        <CardTitle class="text-lg font-normal">
                            Miniature Sculpts
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="px-0 border-l border-r py-0">
                        <Link :href="route('characters.view', {character: character.slug, miniature: sculpt.id, slug: sculpt.slug})" class="block p-2 m-0 w-full h-full border-b hover:bg-secondary text-md" :class="{'bg-secondary': sculpt.id === props.miniature.id }" v-for="sculpt in character.miniatures">
                            {{ sculpt.display_name }}
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
