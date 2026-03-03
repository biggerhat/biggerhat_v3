<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import UpgradeCardView from '@/components/UpgradeCardView.vue';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { computed } from 'vue';

const props = defineProps({
    keyword: {
        type: [Object, Array],
        required: true,
        default() {
            return {};
        },
    },
});

const charCount = computed(() => props.keyword?.characters?.length ?? 0);
const { delays } = useStaggeredEntry(charCount);
</script>
<template>
    <div>
        <div v-if="Object.keys(keyword.characters).length > 0" class="mb-6">
            <!--                    <div class="relative flex py-5 items-center">-->
            <!--                        <div class="flex-grow border-t border-primary"></div>-->
            <!--                        <span class="flex-shrink mx-4 text-lg text-primary">{{ keyword.keyword.name }}</span>-->
            <!--                        <div class="flex-grow border-t border-primary"></div>-->
            <!--                    </div>-->
            <div class="grid w-full lg:grid-cols-6">
                <div class="grid hidden lg:block">
                    <div v-if="Object.keys(keyword.masters).length > 0">
                        <CharacterCardView :miniature="keyword.masters[0]['standard_miniatures'][0]" />
                    </div>
                </div>
                <div class="grid hidden lg:block">
                    <div v-if="Object.keys(keyword.masters).length > 0">
                        <CharacterCardView :miniature="keyword.masters[0]['totem']['standard_miniatures'][0]" />
                    </div>
                </div>
                <div class="col-span-6 lg:col-span-2">
                    <div class="relative flex items-center py-5">
                        <div class="flex-grow border-t border-primary"></div>
                        <span class="mx-4 flex-shrink text-lg text-primary">{{ keyword.keyword.name }}</span>
                        <div class="flex-grow border-t border-primary"></div>
                    </div>
                    This is keyword information.
                </div>
                <div class="grid hidden lg:block">
                    <div v-if="Object.keys(keyword.masters).length > 1">
                        <CharacterCardView :miniature="keyword.masters[1]['standard_miniatures'][0]" />
                    </div>
                </div>
                <div class="grid hidden lg:block">
                    <div v-if="Object.keys(keyword.masters).length > 1">
                        <CharacterCardView :miniature="keyword.masters[1]['totem']['standard_miniatures'][0]" />
                    </div>
                </div>
                <div class="grid hidden lg:block">
                    <div v-if="Object.keys(keyword.masters).length > 0">
                        <div v-if="Object.keys(keyword.masters[0]['crew_upgrades']).length > 0">
                            <UpgradeCardView v-for="upgrade in keyword.masters[0]['crew_upgrades']" v-bind:key="upgrade.slug" :upgrade="upgrade" />
                        </div>
                    </div>
                </div>
                <!--                        <div class="hidden lg:block grid">-->
                <!--                            <CharacterCardView v-if="Object.keys(keyword.masters).length > 0" :miniature="keyword.masters[0]['standard_miniatures'][0]" />-->
                <!--                            <div v-if="Object.keys(keyword.masters).length > 0">-->
                <!--                                <div v-if="Object.keys(keyword.masters[0]['crew_upgrades']).length > 0">-->
                <!--                                    <UpgradeCardView v-for="upgrade in keyword.masters[0]['crew_upgrades']" v-bind:key="upgrade.slug" :upgrade="upgrade" />-->
                <!--                                </div>-->
                <!--                            </div>-->
                <!--                        </div>-->
                <div class="lg:col-span-4">
                    <div class="grid w-full lg:grid-cols-4">
                        <div
                            v-for="(character, index) in keyword.characters"
                            v-bind:key="character.slug"
                            class="animate-fade-in-up opacity-0"
                            :style="delays[index]"
                        >
                            <CharacterCardView :miniature="character.standard_miniatures[0]" :character-slug="character.slug" />
                        </div>
                    </div>
                </div>
                <div class="grid hidden lg:block">
                    <div v-if="Object.keys(keyword.masters).length > 1">
                        <div v-if="Object.keys(keyword.masters[1]['crew_upgrades']).length > 0">
                            <UpgradeCardView v-for="upgrade in keyword.masters[1]['crew_upgrades']" v-bind:key="upgrade.slug" :upgrade="upgrade" />
                        </div>
                    </div>
                </div>
                <!--                        <div class="hidden lg:block grid"><CharacterCardView v-if="Object.keys(keyword.masters).length > 1" :miniature="keyword.masters[1]['standard_miniatures'][0]" /></div>-->
            </div>
        </div>
    </div>
</template>
