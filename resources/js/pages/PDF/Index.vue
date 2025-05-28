<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import {Input} from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { PlusCircle, MinusCircle, CircleX, Eye } from "lucide-vue-next";
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
    }
});
const pdfCharacters = ref([]);
const filterText = ref('');
const currentFaction = ref('');

const results = computed(() => {
    let filter = filterText.value;

    if (!filter.length) {
        return props.characters;
    }

    return props.characters.filter(character => {
        return character.display_name.toLowerCase().includes(filter.toLowerCase());
    });
});

const filterFaction = (factionSlug) => {
    if (factionSlug === currentFaction.value) {
        currentFaction.value = '';

        router.visit(route('tools.pdf.index'), {
            only: ['characters'],
            preserveState: true,
        });
    } else {
        currentFaction.value = factionSlug;

        router.visit(route('tools.pdf.index'), {
            data: { faction: currentFaction.value, },
            only: ['characters'],
            preserveState: true,
        });
    }
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
    let miniatureValues = [];
    pdfCharacters.value.forEach((character) => {
        miniatureValues.push(character.standard_miniatures[0].id);
    });
    window.open(route('tools.pdf.download', {miniatures: miniatureValues}), '_blank').focus();
}
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
                                <img :src="faction.logo" :alt="faction.name" class="hover:cursor-pointer" :class="currentFaction === faction.slug ? 'opacity-100' : 'opacity-70'" @click="filterFaction(faction.slug)" />
                            </div>
                        </div>
                        <div class="p-2 flex">
                            <Input class="max-w-auto" v-model="filterText" placeholder="Filter Characters" />
                            <CircleX class="text-destructive my-auto ml-2" v-if="filterText.length > 0" @click="filterText = ''" />
                        </div>
                        <div class="border border-primary hover:bg-secondary mx-2 my-1 flex justify-between" v-for="character in results" v-bind:key="character.slug">
                            <Drawer>
                                <DrawerTrigger as-child>
                                    <div class="p-2 w-full">{{ character.display_name }}</div>
                                </DrawerTrigger>
                                <DrawerContent>
                                    <div class="mx-auto w-full max-w-sm">
                                        <DrawerHeader>
                                            <DrawerTitle>{{ character.display_name }}</DrawerTitle>
                                        </DrawerHeader>
                                        <div class="p-4 pb-0">
                                            <CharacterCardView :miniature="character.standard_miniatures[0]" />
                                        </div>
                                        <DrawerFooter>
                                            <DrawerClose as-child>
                                                <Button variant="destructive">
                                                    Close
                                                </Button>
                                            </DrawerClose>
                                        </DrawerFooter>
                                    </div>
                                </DrawerContent>
                            </Drawer>
                            <div class="flex" @click="add(character)">
                                <PlusCircle class="my-auto mx-1" />
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
                        <div class="border border-primary hover:bg-secondary mx-2 my-1 p-2 flex justify-between" v-for="(character, key) in pdfCharacters" v-bind:key="character.slug">
                            <div>{{ character.display_name }}</div>
                            <div>
                                <MinusCircle @click="remove(key)" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
