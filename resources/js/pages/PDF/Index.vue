<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import GameIcon from '@/components/GameIcon.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Button } from '@/components/ui/button';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
    ComboboxTrigger,
} from '@/components/ui/combobox';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle, DrawerTrigger } from '@/components/ui/drawer';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NumberField, NumberFieldContent, NumberFieldDecrement, NumberFieldIncrement, NumberFieldInput } from '@/components/ui/number-field';
import UpgradeCardView from '@/components/UpgradeCardView.vue';
import { isMobileDevice } from '@/composables/useMobileDevice';
import { cn } from '@/lib/utils';
import { ArrowUpFromLine, Check, ChevronsUpDown, CircleX, EllipsisVertical, Search, SquareMinus, SquarePlus, UserPlus } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    characters: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    upgrades: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    factions: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    keywords: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
    strategies: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    schemes: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
});
const pdfCards = ref([]);
const filterText = ref('');
const filterUpgradeText = ref('');
const selectedKeyword = ref(null);
const selectedFaction = ref(null);

const upgradeResults = computed(() => {
    const filter = filterUpgradeText.value;

    if (!filter.length && !selectedFaction.value) {
        return props.upgrades;
    }

    let filtered = props.upgrades;

    if (selectedFaction.value) {
        filtered = props.upgrades.filter((upgrade) => {
            return upgrade.faction === selectedFaction.value;
        });
    }

    return filtered.filter((upgrade) => {
        return upgrade.name.toLowerCase().includes(filter.toLowerCase());
    });
});

const totalStones = ref(50);
const stones = computed(() => {
    let stones = 0;
    pdfCards.value.forEach((card) => {
        if (card.card_type === 'miniature') {
            stones += card.cost;
        }
    });

    return stones;
});

const separateImages = ref(false);

const results = computed(() => {
    const filter = filterText.value;

    if (!filter.length && !selectedFaction.value && !selectedKeyword.value) {
        return props.characters;
    }

    let filtered = props.characters;

    if (selectedFaction.value) {
        filtered = props.characters.filter((character) => {
            return character.faction === selectedFaction.value;
        });
    }

    if (selectedKeyword.value) {
        filtered = filtered.filter((character) => {
            return (
                character.keywords.filter((keyword) => {
                    return keyword.slug === selectedKeyword.value.slug;
                }).length > 0
            );
        });
    }

    return filtered.filter((character) => {
        return character.display_name.toLowerCase().includes(filter.toLowerCase());
    });
});

const filterFaction = (factionSlug) => {
    if (factionSlug === selectedFaction.value) {
        selectedFaction.value = null;
    } else {
        selectedFaction.value = factionSlug;
    }
};

const add = (character) => {
    pdfCards.value.push(character);
    if (character.crew_upgrades.length > 0) {
        character.crew_upgrades.forEach((crewUpgrade) => {
            addUpgrade(crewUpgrade);
        });
    }

    if (character.totem_name) {
        props.characters
            .filter((filterCharacters) => {
                return filterCharacters.slug === character.totem_name;
            })
            .forEach((character) => {
                pdfCards.value.push(character);
            });
    }
};

const addUpgrade = (upgrade) => {
    pdfCards.value.push(upgrade);
};

const remove = (key) => {
    pdfCards.value.splice(key, 1);
};

const clear = () => {
    pdfCards.value = [];
};

const generatePDF = () => {
    const pdfValues = [];
    pdfCards.value.forEach((card) => {
        let idValue;
        switch (card.card_type) {
            case 'miniature':
                idValue = card.standard_miniatures[0].id;
                break;
            case 'upgrade':
                idValue = card.id;
                break;
        }

        pdfValues.push({
            card_type: card.card_type,
            id: idValue,
        });
    });

    const options = {
        separate_images: separateImages.value,
    };

    window.open(route('tools.pdf.download', { cards: btoa(JSON.stringify(pdfValues)), options: btoa(JSON.stringify(options)) }), '_blank').focus();
};

const changeTab = (tabName) => {
    if (isCurrentTab(tabName)) {
        return;
    }

    currentTab.value = tabName;
};

const currentTab = ref('characters');
const isCurrentTab = (tabName) => {
    if (!isMobileDevice() && tabName === 'list') {
        return true;
    }

    return currentTab.value === tabName;
};

const urlParams = new URLSearchParams(window.location.search);
onMounted(() => {
    if (urlParams.get('faction')) {
        filterFaction(urlParams.get('faction'));
    }

    if (urlParams.get('keyword')) {
        const filtered = props.keywords.filter((keyword) => {
            return keyword.slug === urlParams.get('keyword');
        });
        selectedKeyword.value = filtered[0];
    }
});
</script>

<template>
    <Head title="PDF Generator" />

    <div class="mb-6 h-full w-full">
        <PageBanner title="PDF Generator" />
        <div class="container mx-auto mt-1">
            <div class="grid grid-cols-6 gap-2">
                <div class="col-span-6 flex flex-row flex-nowrap items-center justify-center gap-1 md:col-span-3">
                    <div class="flex-1 flex-grow">
                        <Button
                            @click="changeTab('characters')"
                            class="w-full !rounded-none !rounded-t-sm"
                            :disabled="isCurrentTab('characters') ?? 'disabled'"
                        >
                            <UserPlus />
                            <span v-if="!isMobileDevice()">Characters</span>
                        </Button>
                    </div>
                    <div class="flex-1 flex-grow">
                        <Button
                            @click="changeTab('upgrades')"
                            class="w-full !rounded-none !rounded-t-sm"
                            :disabled="isCurrentTab('upgrades') ?? 'disabled'"
                        >
                            <ArrowUpFromLine />
                            <span v-if="!isMobileDevice()">Upgrades</span>
                        </Button>
                    </div>
                    <!--                    <div class="flex-1 flex-grow">-->
                    <!--                        <Button @click="changeTab('scenarios')" class="!rounded-none !rounded-t-sm w-full" :disabled="isCurrentTab('scenarios') ?? 'disabled'">-->
                    <!--                            <Map />-->
                    <!--                            <span v-if="!isMobileDevice()">Schemes & Strategies</span>-->
                    <!--                        </Button>-->
                    <!--                    </div>-->
                    <div class="flex-1 flex-grow md:hidden">
                        <Button @click="changeTab('list')" class="w-full !rounded-none !rounded-t-sm" :disabled="isCurrentTab('list') ?? 'disabled'"
                            >List ({{ pdfCards.length }})</Button
                        >
                    </div>
                </div>
            </div>
            <div class="grid min-h-screen grid-cols-6 gap-2">
                <div class="col-span-6 grid border border-primary pb-2 pt-1 md:col-span-3" :class="isCurrentTab('characters') ? 'block' : 'hidden'">
                    <div>
                        <div class="grid grid-cols-8">
                            <div v-for="faction in factions" v-bind:key="faction.slug">
                                <img
                                    :src="faction.logo"
                                    :alt="faction.name"
                                    class="hover:cursor-pointer"
                                    :class="selectedFaction === faction.slug ? 'opacity-100' : 'opacity-70'"
                                    @click="filterFaction(faction.slug)"
                                />
                            </div>
                        </div>
                        <div class="grid gap-1 p-2 md:grid-cols-2">
                            <div class="my-auto flex w-full">
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
                                            <ComboboxInput
                                                class="h-10 rounded-none border-0 border-b pl-9 focus-visible:ring-0"
                                                placeholder="Select Keyword..."
                                            />
                                            <span class="absolute inset-y-0 start-0 flex items-center justify-center px-3">
                                                <Search class="size-4 text-muted-foreground" />
                                            </span>
                                        </div>

                                        <ComboboxEmpty> No Keyword Found. </ComboboxEmpty>

                                        <ComboboxGroup>
                                            <ComboboxItem v-for="keyword in props.keywords" :key="keyword.slug" :value="keyword">
                                                {{ keyword.name }}

                                                <Check v-if="keyword.slug === selectedKeyword?.slug" :class="cn('ml-auto h-4 w-4')" />
                                            </ComboboxItem>
                                        </ComboboxGroup>
                                    </ComboboxList>
                                </Combobox>
                                <CircleX class="my-auto ml-2 text-destructive" v-if="selectedKeyword" @click="selectedKeyword = null" />
                            </div>
                            <div class="my-auto flex w-full">
                                <Input class="max-w-auto" v-model="filterText" placeholder="Filter Characters" />
                                <CircleX class="my-auto ml-2 text-destructive" v-if="filterText.length > 0" @click="filterText = ''" />
                            </div>
                        </div>
                        <div class="max-h-screen overflow-y-auto">
                            <div class="m-0 w-full p-0" v-for="character in results" v-bind:key="character.slug">
                                <div
                                    :class="factionBackground(character.faction)"
                                    class="mx-2 my-1 flex justify-between border border-primary transition hover:brightness-[90%]"
                                >
                                    <Drawer>
                                        <DrawerTrigger as-child>
                                            <div class="text-md w-full px-2 py-1">
                                                <span class="font-bold">{{ character.display_name }}</span>
                                                <div class="m-0 block p-0 text-xs first-letter:capitalize">
                                                    <span v-if="character.cost">Cost: {{ character.cost }} // </span>
                                                    <div v-if="character.station" class="inline-block first-letter:capitalize">
                                                        {{ character.station }} <span v-if="character.count > 1">({{ character.count }})</span>
                                                    </div>
                                                    <span v-if="character.station && character.keywords.length > 0"> // </span>
                                                    {{ character.keywords.map((keyword) => keyword.name).join(', ') }}
                                                </div>
                                            </div>
                                        </DrawerTrigger>
                                        <DrawerContent>
                                            <div class="mx-auto w-full max-w-sm">
                                                <DrawerHeader>
                                                    <DrawerTitle>{{ character.display_name }}</DrawerTitle>
                                                </DrawerHeader>
                                                <div class="p-4 pb-0">
                                                    <CharacterCardView
                                                        :miniature="character.standard_miniatures[0]"
                                                        showLink="false"
                                                        :character-slug="character.slug"
                                                    />
                                                </div>
                                                <DrawerFooter>
                                                    <div class="flex justify-center">
                                                        <div class="mx-1">
                                                            <Button variant="default" @click="add(character)"> Add To List </Button>
                                                        </div>
                                                        <div class="mx-1">
                                                            <DrawerClose as-child>
                                                                <Button variant="destructive"> Close </Button>
                                                            </DrawerClose>
                                                        </div>
                                                    </div>
                                                </DrawerFooter>
                                            </div>
                                        </DrawerContent>
                                    </Drawer>
                                    <div class="flex" @click="add(character)">
                                        <SquarePlus class="mx-1 my-auto" />
                                    </div>
                                </div>
                                <div v-if="character.crew_upgrades">
                                    <div v-for="card in character.crew_upgrades" :key="card.id" class="flex">
                                        <ArrowUpFromLine class="mx-auto my-auto ml-2" />
                                        <div
                                            :class="factionBackground(card.faction)"
                                            class="mx-2 my-1 flex w-full justify-between border border-primary hover:brightness-[90%]"
                                        >
                                            <Drawer>
                                                <DrawerTrigger as-child>
                                                    <div class="text-md w-full px-2 py-1">
                                                        <span class="font-bold">{{ card.name }}</span>
                                                        <div class="m-0 block p-0 text-xs">
                                                            <span v-if="card.type">
                                                                {{ card.type }}
                                                                <span v-if="card.master"> - {{ card.master }} </span>
                                                                <span v-if="card.count > 1">({{ card.count }})</span>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </DrawerTrigger>
                                                <DrawerContent>
                                                    <div class="mx-auto w-full max-w-sm">
                                                        <DrawerHeader>
                                                            <DrawerTitle>{{ card.name }}</DrawerTitle>
                                                        </DrawerHeader>
                                                        <div class="p-4 pb-0">
                                                            <UpgradeCardView :upgrade="card" />
                                                        </div>
                                                        <DrawerFooter>
                                                            <div class="flex justify-center">
                                                                <div class="mx-1">
                                                                    <Button variant="default" @click="addUpgrade(card)"> Add To List </Button>
                                                                </div>
                                                                <div class="mx-1">
                                                                    <DrawerClose as-child>
                                                                        <Button variant="destructive"> Close </Button>
                                                                    </DrawerClose>
                                                                </div>
                                                            </div>
                                                        </DrawerFooter>
                                                    </div>
                                                </DrawerContent>
                                            </Drawer>
                                            <div class="flex" @click="addUpgrade(card)">
                                                <SquarePlus class="mx-1 my-auto" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-span-6 grid border border-primary pb-2 pt-1 md:col-span-3" :class="isCurrentTab('upgrades') ? 'block' : 'hidden'">
                    <div>
                        <div class="grid grid-cols-8">
                            <div v-for="faction in factions" v-bind:key="faction.slug">
                                <img
                                    :src="faction.logo"
                                    :alt="faction.name"
                                    class="hover:cursor-pointer"
                                    :class="selectedFaction === faction.slug ? 'opacity-100' : 'opacity-70'"
                                    @click="filterFaction(faction.slug)"
                                />
                            </div>
                        </div>
                        <div class="my-auto flex w-full p-2">
                            <Input class="max-w-auto" v-model="filterUpgradeText" placeholder="Filter Upgrades" />
                            <CircleX class="my-auto ml-2 text-destructive" v-if="filterUpgradeText.length > 0" @click="filterUpgradeText = ''" />
                        </div>
                        <div class="max-h-screen overflow-y-auto">
                            <div
                                :class="factionBackground(upgrade.faction)"
                                class="mx-2 my-1 flex justify-between border border-primary hover:brightness-[90%]"
                                v-for="upgrade in upgradeResults"
                                v-bind:key="upgrade.slug"
                            >
                                <Drawer>
                                    <DrawerTrigger as-child>
                                        <div class="text-md w-full px-2 py-1">
                                            <span class="font-bold">{{ upgrade.name }}</span>
                                            <div class="m-0 block p-0 text-xs">
                                                <span v-if="upgrade.type">
                                                    {{ upgrade.type }}
                                                    <span v-if="upgrade.master"> - {{ upgrade.master }} </span>
                                                    <span v-if="upgrade.count > 1"> ({{ upgrade.count }})</span>
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
                                                        <Button variant="default" @click="addUpgrade(upgrade)"> Add To List </Button>
                                                    </div>
                                                    <div class="mx-1">
                                                        <DrawerClose as-child>
                                                            <Button variant="destructive"> Close </Button>
                                                        </DrawerClose>
                                                    </div>
                                                </div>
                                            </DrawerFooter>
                                        </div>
                                    </DrawerContent>
                                </Drawer>
                                <div class="flex" @click="addUpgrade(upgrade)">
                                    <SquarePlus class="mx-1 my-auto" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    class="col-span-6 grid min-h-screen border border-primary pb-2 md:col-span-3"
                    :class="isCurrentTab('scenarios') ? 'block' : 'hidden'"
                >
                    Nothing Here Yet! Check Back Soon!
                </div>
                <div class="col-span-6 grid border border-primary pb-2 md:col-span-3" :class="isCurrentTab('list') ? 'block' : 'hidden'">
                    <div>
                        <div class="flex justify-between p-2">
                            <div>
                                <Button class="mx-1 p-2" variant="destructive" @click="clear()">Clear</Button>
                                <Button class="mx-1 p-2" variant="default" :disabled="pdfCards.length < 1" @click="generatePDF()"
                                    >Generate PDF</Button
                                >
                            </div>
                            <div class="hidden md:flex">
                                <Label for="stone_count" class="my-auto mr-2"
                                    >Total <GameIcon type="soulstone" className="h-6 my-auto inline-block mx-1"
                                /></Label>
                                <NumberField id="stone_count" v-model="totalStones" :min="0" class="inline-block">
                                    <NumberFieldContent>
                                        <NumberFieldDecrement />
                                        <NumberFieldInput />
                                        <NumberFieldIncrement />
                                    </NumberFieldContent>
                                </NumberField>
                            </div>
                            <div class="my-auto inline-block hidden md:flex">
                                Spent: {{ stones }} <GameIcon type="soulstone" className="h-6 my-auto mx-1 inline-block" />
                            </div>
                            <div class="my-auto inline-block hidden md:flex">
                                Cache: {{ totalStones - stones > 6 ? 6 : Math.max(0, totalStones - stones) }}
                                <GameIcon type="soulstone" className="h-6 my-auto inline-block mx-1" />
                            </div>
                            <div class="my-auto inline-block">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <EllipsisVertical />
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent class="w-56" align="end">
                                        <DropdownMenuLabel>PDF Options</DropdownMenuLabel>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuGroup>
                                            <DropdownMenuCheckboxItem v-model="separateImages"> Separate Images </DropdownMenuCheckboxItem>
                                        </DropdownMenuGroup>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 md:hidden">
                            <div>
                                <Label for="stone_count" class="my-auto mr-2 block text-center"
                                    >Total <GameIcon type="soulstone" className="h-6 my-auto inline-block"
                                /></Label>
                                <NumberField id="stone_count" v-model="totalStones" :min="0" class="inline-block">
                                    <NumberFieldContent>
                                        <NumberFieldDecrement />
                                        <NumberFieldInput />
                                        <NumberFieldIncrement />
                                    </NumberFieldContent>
                                </NumberField>
                            </div>
                            <div class="my-auto inline-block text-center">
                                Spent: <br />{{ stones }} <GameIcon type="soulstone" className="h-6 my-auto inline-block" />
                            </div>
                            <div class="my-auto inline-block text-center">
                                Cache: <br />{{ totalStones - stones > 6 ? 6 : Math.max(0, totalStones - stones) }}
                                <GameIcon type="soulstone" className="h-6 my-auto inline-block" />
                            </div>
                        </div>
                        <div class="max-h-screen overflow-y-auto">
                            <div v-for="(card, index) in pdfCards" v-bind:key="index">
                                <div
                                    v-if="card.card_type === 'miniature'"
                                    :class="factionBackground(card.faction)"
                                    class="mx-2 my-1 flex justify-between border border-primary hover:brightness-[90%]"
                                >
                                    <Drawer>
                                        <DrawerTrigger as-child>
                                            <div class="text-md w-full px-2 py-1">
                                                <span class="font-bold">{{ card.display_name }}</span>
                                                <div class="m-0 block p-0 text-xs first-letter:capitalize">
                                                    <span v-if="card.cost">Cost: {{ card.cost }} // </span>
                                                    <div v-if="card.station" class="inline-block first-letter:capitalize">
                                                        {{ card.station }} <span v-if="card.count > 1">({{ card.count }})</span>
                                                    </div>
                                                    <span v-if="card.station && card.keywords.length > 0"> // </span>
                                                    {{ card.keywords.map((keyword) => keyword.name).join(', ') }}
                                                </div>
                                            </div>
                                        </DrawerTrigger>
                                        <DrawerContent>
                                            <div class="mx-auto w-full max-w-sm">
                                                <DrawerHeader>
                                                    <DrawerTitle>{{ card.display_name }}</DrawerTitle>
                                                </DrawerHeader>
                                                <div class="p-4 pb-0">
                                                    <CharacterCardView
                                                        :miniature="card.standard_miniatures[0]"
                                                        showLink="false"
                                                        :character-slug="card.slug"
                                                    />
                                                </div>
                                                <DrawerFooter>
                                                    <div class="flex justify-center">
                                                        <div class="mx-1">
                                                            <Button variant="destructive" @click="remove(index)"> Remove From List </Button>
                                                        </div>
                                                        <div class="mx-1">
                                                            <DrawerClose as-child>
                                                                <Button variant="destructive"> Close </Button>
                                                            </DrawerClose>
                                                        </div>
                                                    </div>
                                                </DrawerFooter>
                                            </div>
                                        </DrawerContent>
                                    </Drawer>
                                    <div class="flex" @click="remove(index)">
                                        <SquareMinus class="mx-1 my-auto" />
                                    </div>
                                </div>
                                <div v-if="card.card_type === 'upgrade'" class="flex">
                                    <ArrowUpFromLine class="mx-auto my-auto ml-2" />
                                    <div
                                        :class="factionBackground(card.faction)"
                                        class="mx-2 my-1 flex w-full justify-between border border-primary hover:brightness-[90%]"
                                    >
                                        <Drawer>
                                            <DrawerTrigger as-child>
                                                <div class="text-md w-full px-2 py-1">
                                                    <span class="font-bold">{{ card.name }}</span>
                                                    <div class="m-0 block p-0 text-xs">
                                                        <span v-if="card.type">
                                                            {{ card.type }}
                                                            <span v-if="card.master"> - {{ card.master }} </span>
                                                            <span v-if="card.count > 1">({{ card.count }})</span>
                                                        </span>
                                                    </div>
                                                </div>
                                            </DrawerTrigger>
                                            <DrawerContent>
                                                <div class="mx-auto w-full max-w-sm">
                                                    <DrawerHeader>
                                                        <DrawerTitle>{{ card.name }}</DrawerTitle>
                                                    </DrawerHeader>
                                                    <div class="p-4 pb-0">
                                                        <UpgradeCardView :upgrade="card" />
                                                    </div>
                                                    <DrawerFooter>
                                                        <div class="flex justify-center">
                                                            <div class="mx-1">
                                                                <Button variant="destructive" @click="remove(index)"> Remove From List </Button>
                                                            </div>
                                                            <div class="mx-1">
                                                                <DrawerClose as-child>
                                                                    <Button variant="destructive"> Close </Button>
                                                                </DrawerClose>
                                                            </div>
                                                        </div>
                                                    </DrawerFooter>
                                                </div>
                                            </DrawerContent>
                                        </Drawer>
                                        <div class="flex" @click="remove(index)">
                                            <SquareMinus class="mx-1 my-auto" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
