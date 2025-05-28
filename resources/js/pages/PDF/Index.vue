<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import {Input} from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { PlusCircle, MinusCircle, CircleX, SquarePlus, SquareMinus } from "lucide-vue-next";
import {
    Drawer,
    DrawerClose,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
    DrawerTrigger,
} from '@/components/ui/drawer'
import CharacterCardView from "@/components/CharacterCardView.vue";
import { cleanObject } from '@/composables/CleanObject';
import {Select, SelectContent, SelectItem, SelectTrigger, SelectValue} from "@/components/ui/select";

const props = defineProps({
    characters: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    factions: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    keywords: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        }
    }
});
const pdfCharacters = ref([]);
const filterText = ref('');

const filterParams = ref({
  keyword: null,
  faction: null,
})

const results = computed(() => {
    const filter = filterText.value;

    if (!filter.length) {
        return props.characters;
    }

    return props.characters.filter(character => {
        return character.display_name.toLowerCase().includes(filter.toLowerCase());
    });
});

const filterFaction = (factionSlug) => {
    if (factionSlug === filterParams.value.faction) {
        filterParams.value.faction = null;
    } else {
        filterParams.value.faction = factionSlug;
    }

    filter();
};

const filter = () => {
  router.visit(route('tools.pdf.index'), {
    data: cleanObject(filterParams.value),
    only: ['characters'],
    preserveState: true,
  });
};

const add = (character) => {
    pdfCharacters.value.push(character);
};

const remove = (key) => {
    pdfCharacters.value.splice(key, 1);
};

const clear = () => {
    pdfCharacters.value = [];
}

const generatePDF = () => {
    const miniatureValues = [];
    pdfCharacters.value.forEach((character) => {
        miniatureValues.push(character.standard_miniatures[0].id);
    });
    window.open(route('tools.pdf.download', {miniatures: miniatureValues}), '_blank').focus();
}

const urlParams = new URLSearchParams(window.location.search);
onMounted(() => {
  filterParams.value.faction = urlParams.get('faction');
  filterParams.value.keyword = urlParams.get('keyword');
});

</script>

<template>
    <Head title="PDF Generator" />

    <div class="w-full h-full mb-6">
        <div class="flex w-full bg-secondary">
            <div class="container mx-auto items-center">
                <div class="flex justify-between">
                    <div class="py-1 md:py-4 flex w-full">
                        <div class="flex justify-between w-full md:block" id="page-banner">
                            <div class="p-2 font-bold text-xl my-auto">PDF Generator</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="container mx-auto mt-6">
            <div class="grid grid-cols-6 gap-2 md:min-h-screen">
                <div class="grid col-span-3 border border-primary pb-2">
                    <div>
                        <div class="grid grid-cols-8">
                            <div v-for="faction in factions" v-bind:key="faction.slug">
                                <img :src="faction.logo" :alt="faction.name" class="hover:cursor-pointer" :class="filterParams.faction === faction.slug ? 'opacity-100' : 'opacity-70'" @click="filterFaction(faction.slug)" />
                            </div>
                        </div>
                        <div class="p-2 grid md:grid-cols-2 gap-1">
                          <div class="flex w-full my-auto">
                            <Input class="max-w-auto" v-model="filterText" placeholder="Filter Characters" />
                            <CircleX class="text-destructive my-auto ml-2" v-if="filterText.length > 0" @click="filterText = ''" />
                          </div>
                          <div class="flex w-full my-auto">
                            <Select v-model="filterParams.keyword">
                              <SelectTrigger class="border-2 border-secondary rounded max-w-auto">
                                <SelectValue placeholder="Keyword" />
                              </SelectTrigger>
                              <SelectContent>
                                <SelectItem v-for="keyword in props.keywords" :value="keyword.slug" :key="keyword.slug" :onSelect="filter()">
                                  {{ keyword.name }}
                                </SelectItem>
                              </SelectContent>
                            </Select>
                            <CircleX class="text-destructive my-auto ml-2" v-if="filterParams.keyword" @click="filterParams.keyword = null" />
                          </div>
                        </div>
                        <div :class="factionBackground(character.faction)" class="border border-primary hover:bg-secondary mx-2 my-1 flex justify-between" v-for="character in results" v-bind:key="character.slug">
                            <Drawer>
                                <DrawerTrigger as-child>
                                    <div class="py-1 px-2 w-full text-md">
                                      <span class="font-bold">{{ character.display_name }}</span>
                                      <div class="block m-0 p-0 text-xs first-letter:capitalize">
                                        <span v-if="character.station" class="first-letter:capitalize">{{ character.station }} <span v-if="character.count > 1">({{ character.count }})</span></span>
                                        <span v-if="character.station && character.keywords.length > 0"> // </span>
                                        {{ character.keywords.map(keyword => keyword.name).join(', ')}}
                                      </div>
                                    </div>
                                </DrawerTrigger>
                                <DrawerContent>
                                    <div class="mx-auto w-full max-w-sm">
                                        <DrawerHeader>
                                            <DrawerTitle>{{ character.display_name }}</DrawerTitle>
                                        </DrawerHeader>
                                        <div class="p-4 pb-0">
                                            <CharacterCardView :miniature="character.standard_miniatures[0]" showLink="false" />
                                        </div>
                                        <DrawerFooter>
                                          <div class="flex justify-center">
                                            <div class="mx-1">
                                              <Button variant="default" @click="add(character)">
                                                Add To List
                                              </Button>
                                            </div>
                                            <div class="mx-1">
                                              <DrawerClose as-child>
                                                <Button variant="destructive">
                                                  Close
                                                </Button>
                                              </DrawerClose>
                                            </div>
                                          </div>
                                        </DrawerFooter>
                                    </div>
                                </DrawerContent>
                            </Drawer>
                            <div class="flex" @click="add(character)">
                                <SquarePlus class="my-auto mx-1" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="grid col-span-3 border border-primary pb-2">
                    <div>
                        <div class="p-2">
                            <Button class="p-2 mx-1" variant="destructive" @click="clear()">Clear</Button>
                            <Button class="p-2 mx-1" variant="default" @click="generatePDF()">Generate PDF</Button>
                        </div>
                      <div :class="factionBackground(character.faction)" class="border border-primary hover:bg-secondary mx-2 my-1 flex justify-between" v-for="(character, index) in pdfCharacters" v-bind:key="character.slug">
                        <Drawer>
                          <DrawerTrigger as-child>
                            <div class="py-1 px-2 w-full text-md">
                              <span class="font-bold">{{ character.display_name }}</span>
                              <div class="block m-0 p-0 text-xs first-letter:capitalize">
                                <span v-if="character.station" class="first-letter:capitalize">{{ character.station }} <span v-if="character.count > 1">({{ character.count }})</span></span>
                                <span v-if="character.station && character.keywords.length > 0"> // </span>
                                {{ character.keywords.map(keyword => keyword.name).join(', ')}}
                              </div>
                            </div>
                          </DrawerTrigger>
                          <DrawerContent>
                            <div class="mx-auto w-full max-w-sm">
                              <DrawerHeader>
                                <DrawerTitle>{{ character.display_name }}</DrawerTitle>
                              </DrawerHeader>
                              <div class="p-4 pb-0">
                                <CharacterCardView :miniature="character.standard_miniatures[0]" showLink="false" />
                              </div>
                              <DrawerFooter>
                                <div class="flex justify-center">
                                  <div class="mx-1">
                                    <Button variant="default" @click="add(character)">
                                      Add To List
                                    </Button>
                                  </div>
                                  <div class="mx-1">
                                    <DrawerClose as-child>
                                      <Button variant="destructive">
                                        Close
                                      </Button>
                                    </DrawerClose>
                                  </div>
                                </div>
                              </DrawerFooter>
                            </div>
                          </DrawerContent>
                        </Drawer>
                        <div class="flex" @click="remove(index)">
                          <SquareMinus class="my-auto mx-1" />
                        </div>
                      </div>
<!--                        <div class="border border-primary hover:bg-secondary mx-2 my-1 p-2 flex justify-between" v-for="(character, key) in pdfCharacters" v-bind:key="character.slug">-->
<!--                            <div>{{ character.display_name }}</div>-->
<!--                            <div>-->
<!--                                <MinusCircle @click="remove(key)" />-->
<!--                            </div>-->
<!--                        </div>-->
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
