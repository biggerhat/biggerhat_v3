<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import {Input} from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { cn } from '@/lib/utils'
import { PlusCircle, MinusCircle, CircleX, SquarePlus, SquareMinus, Check, Search, ChevronsUpDown, UserPlus, ArrowUpFromLine, Map } from "lucide-vue-next";
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import CharacterCardView from "@/components/CharacterCardView.vue";
import { cleanObject } from '@/composables/CleanObject';
import {Select, SelectContent, SelectItem, SelectTrigger, SelectValue} from "@/components/ui/select";
import { Combobox, ComboboxAnchor, ComboboxEmpty, ComboboxGroup, ComboboxInput, ComboboxItem, ComboboxItemIndicator, ComboboxList, ComboboxTrigger } from '@/components/ui/combobox'
import UpgradeCardView from "@/components/UpgradeCardView.vue";

const props = defineProps({
    characters: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    upgrades: {
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
    },
    strategies: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    schemes: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    }
});
const pdfCharacters = ref([]);
const pdfUpgrades = ref([]);
const pdfStrategies = ref([]);
const pdfSchemes = ref([]);
const filterText = ref('');
const filterUpgradeText = ref('');
const filterScenarioText = ref('');

const filterParams = ref({
  keyword: null,
  faction: null,
})

const selectedKeyword = ref(null);

watch(selectedKeyword, (keyword) => {
  filterParams.value.keyword = keyword?.slug;
  filter();
});

const upgradeResults = computed(() => {
    const filter = filterUpgradeText.value;

    if (!filter.length) {
        return props.upgrades;
    }

    return props.upgrades.filter(upgrade => {
        return upgrade.name.toLowerCase().includes(filter.toLowerCase());
    });
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

const addUpgrade = (upgrade) => {
    pdfUpgrades.value.push(upgrade);
}

const addStrategy = (strategy) => {
    pdfStrategies.value.push(strategy);
}

const addScheme = (scheme) => {
    pdfSchemes.value.push(scheme);
}

const remove = (key) => {
    pdfCharacters.value.splice(key, 1);
};

const removeUpgrade = (key) => {
    pdfUpgrades.value.splice(key, 1);
}

const removeStrategy = (key) => {
    pdfStrategies.value.splice(key, 1);
}

const removeScheme = (key) => {
    pdfSchemes.value.splice(key, 1);
}

const clear = () => {
    pdfCharacters.value = [];
    pdfUpgrades.value = [];
    pdfStrategies.value = [];
    pdfSchemes.value = [];
}

const generatePDF = () => {
    const miniatureValues = [];
    const upgradeValues = [];
    const strategyValues = [];
    const schemeValues = [];

    pdfCharacters.value.forEach((character) => {
        miniatureValues.push(character.standard_miniatures[0].id);
    });
    pdfUpgrades.value.forEach((upgrade) => {
        upgradeValues.push(upgrade.id);
    })

    window.open(route('tools.pdf.download', {miniatures: btoa(miniatureValues), upgrades: btoa(upgradeValues)}), '_blank').focus();
}

const urlParams = new URLSearchParams(window.location.search);
onMounted(() => {
  filterParams.value.faction = urlParams.get('faction');
  filterParams.value.keyword = urlParams.get('keyword');
});

function isMobileDevice() {
    return /Mobi|Android/i.test(navigator.userAgent);
}

const changeTab = (tabName) => {
    if (isCurrentTab(tabName)) {
        return;
    }

    currentTab.value = tabName;
}

const currentTab = ref('characters');
const isCurrentTab = (tabName) => {
    if (!isMobileDevice() && tabName === 'list') {
        return true;
    }

    return currentTab.value === tabName;
}


const charactersToggle = ref(true);
const charactersVisible = computed(() => {
    if (!isMobileDevice()) {
        return true;
    }

    return charactersToggle.value;
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
        <div class="container mx-auto mt-1">
            <div class="grid grid-cols-6 gap-2">
                <div class="flex flex-row flex-nowrap items-center gap-1 justify-center col-span-6 md:col-span-3">
                    <div class="flex-1 flex-grow">
                        <Button @click="changeTab('characters')" class="!rounded-none !rounded-t-sm w-full" :disabled="isCurrentTab('characters') ?? 'disabled'">
                            <UserPlus />
                            <span v-if="!isMobileDevice()">Characters</span>
                        </Button>
                    </div>
                    <div class="flex-1 flex-grow">
                        <Button @click="changeTab('upgrades')" class="!rounded-none !rounded-t-sm w-full" :disabled="isCurrentTab('upgrades') ?? 'disabled'">
                            <ArrowUpFromLine />
                            <span v-if="!isMobileDevice()">Upgrades</span>
                        </Button>
                    </div>
                    <div class="flex-1 flex-grow">
                        <Button @click="changeTab('scenarios')" class="!rounded-none !rounded-t-sm w-full" :disabled="isCurrentTab('scenarios') ?? 'disabled'">
                            <Map />
                            <span v-if="!isMobileDevice()">Schemes & Strategies</span>
                        </Button>
                    </div>
                    <div class="flex-1 flex-grow md:hidden">
                        <Button @click="changeTab('list')" class="!rounded-none !rounded-t-sm w-full" :disabled="isCurrentTab('list') ?? 'disabled'">List ({{ pdfCharacters.length }})</Button>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-6 gap-2 md:min-h-screen">
                <div class="grid md:col-span-3 col-span-6 border border-primary pb-2 pt-1 min-h-screen" :class="isCurrentTab('characters') ? 'block' : 'hidden'">
                    <div>
                        <div class="grid grid-cols-8">
                            <div v-for="faction in factions" v-bind:key="faction.slug">
                                <img :src="faction.logo" :alt="faction.name" class="hover:cursor-pointer" :class="filterParams.faction === faction.slug ? 'opacity-100' : 'opacity-70'" @click="filterFaction(faction.slug)" />
                            </div>
                        </div>
                        <div class="p-2 grid md:grid-cols-2 gap-1">
                            <div class="flex w-full my-auto">
                                <Combobox v-model="selectedKeyword" by="label">
                                    <ComboboxAnchor as-child>
                                        <ComboboxTrigger as-child>
                                            <Button variant="outline" class="justify-between">
                                                {{ selectedKeyword?.name ?? 'Select Keyword' }}
                                                <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                            </Button>
                                        </ComboboxTrigger>
                                    </ComboboxAnchor>

                                    <ComboboxList class="max-h-80 overflow-y-auto">
                                        <div class="relative w-full items-center">
                                            <ComboboxInput class="pl-9 focus-visible:ring-0 border-0 border-b rounded-none h-10" placeholder="Select Keyword..." />
                                            <span class="absolute start-0 inset-y-0 flex items-center justify-center px-3">
                                    <Search class="size-4 text-muted-foreground" />
                                  </span>
                                        </div>

                                        <ComboboxEmpty>
                                            No Keyword Found.
                                        </ComboboxEmpty>

                                        <ComboboxGroup>
                                            <ComboboxItem
                                                v-for="keyword in props.keywords"
                                                :key="keyword.slug"
                                                :value="keyword"
                                            >
                                                {{ keyword.name }}

                                                <Check v-if="keyword.slug === selectedKeyword?.slug" :class="cn('ml-auto h-4 w-4')" />
                                            </ComboboxItem>
                                        </ComboboxGroup>
                                    </ComboboxList>
                                </Combobox>
                                <CircleX class="text-destructive my-auto ml-2" v-if="selectedKeyword" @click="selectedKeyword = null" />
                            </div>
                          <div class="flex w-full my-auto">
                            <Input class="max-w-auto" v-model="filterText" placeholder="Filter Characters" />
                            <CircleX class="text-destructive my-auto ml-2" v-if="filterText.length > 0" @click="filterText = ''" />
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
                <div class="grid md:col-span-3 col-span-6 border border-primary pb-2 min-h-screen" :class="isCurrentTab('upgrades') ? 'block' : 'hidden'">
                    <div class="px-2 pt-2">
                        <div class="flex w-full my-auto pb-2">
                            <Input class="max-w-auto" v-model="filterUpgradeText" placeholder="Filter Upgrades" />
                            <CircleX class="text-destructive my-auto ml-2" v-if="filterUpgradeText.length > 0" @click="filterUpgradeText = ''" />
                        </div>
                        <div class="border border-primary hover:bg-secondary my-1 flex justify-between" v-for="upgrade in upgradeResults" v-bind:key="upgrade.slug">
                            <Drawer>
                                <DrawerTrigger as-child>
                                    <div class="py-1 px-2 w-full text-md">
                                        <span class="font-bold">{{ upgrade.name }}</span>
                                        <div class="block m-0 p-0 text-xs">
                                            <span v-if="upgrade.type">
                                                {{ upgrade.type }}
                                                <span v-if="upgrade.master"> - {{ upgrade.master }} </span>
                                                <span v-if="upgrade.count > 1">({{ upgrade.count }})</span>
                                            </span>
                                        </div>
                                    </div>
                                </DrawerTrigger>
                                <DrawerContent>
                                    <div class="mx-auto w-full max-w-sm">
                                        <DrawerHeader>
                                            <DrawerTitle>{{ upgrade.name }}</DrawerTitle>
                                        </DrawerHeader>
                                        <div class="p-4 pb-0">
                                            <UpgradeCardView :upgrade="upgrade" />
                                        </div>
                                        <DrawerFooter>
                                            <div class="flex justify-center">
                                                <div class="mx-1">
                                                    <Button variant="default" @click="addUpgrade(upgrade)">
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
                            <div class="flex" @click="addUpgrade(upgrade)">
                                <SquarePlus class="my-auto mx-1" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="grid md:col-span-3 col-span-6 border border-primary pb-2 min-h-screen" :class="isCurrentTab('scenarios') ? 'block' : 'hidden'">
                    Nothing Here Yet! Check Back Soon!
                </div>
                <div class="grid md:col-span-3 col-span-6 border border-primary pb-2 min-h-screen" :class="isCurrentTab('list') ? 'block' : 'hidden'">
                    <div>
                        <div class="p-2">
                            <Button class="p-2 mx-1" variant="destructive" @click="clear()">Clear</Button>
                            <Button class="p-2 mx-1" variant="default" :disabled="pdfCharacters.length < 1" @click="generatePDF()">Generate PDF</Button>
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
                                    <Button variant="destructive" @click="remove(character)">
                                      Remove From List
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
                        <div class="border border-primary hover:bg-secondary my-1 mx-2 flex justify-between" v-for="(upgrade, index) in pdfUpgrades" v-bind:key="upgrade.slug">
                            <Drawer>
                                <DrawerTrigger as-child>
                                    <div class="py-1 px-2 w-full text-md">
                                        <span class="font-bold">{{ upgrade.name }}</span>
                                        <div class="block m-0 p-0 text-xs">
                                            <span v-if="upgrade.type">
                                                {{ upgrade.type }}
                                                <span v-if="upgrade.master"> - {{ upgrade.master }} </span>
                                                <span v-if="upgrade.count > 1">({{ upgrade.count }})</span>
                                            </span>
                                        </div>
                                    </div>
                                </DrawerTrigger>
                                <DrawerContent>
                                    <div class="mx-auto w-full max-w-sm">
                                        <DrawerHeader>
                                            <DrawerTitle>{{ upgrade.name }}</DrawerTitle>
                                        </DrawerHeader>
                                        <div class="p-4 pb-0">
                                            <UpgradeCardView :upgrade="upgrade" />
                                        </div>
                                        <DrawerFooter>
                                            <div class="flex justify-center">
                                                <div class="mx-1">
                                                    <Button variant="destructive" @click="removeUpgrade(index)">
                                                        Remove From List
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
                            <div class="flex" @click="removeUpgrade(index)">
                                <SquareMinus class="my-auto mx-1" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
