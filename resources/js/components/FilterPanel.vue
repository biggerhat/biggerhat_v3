<script setup lang="ts">
import { ref } from 'vue';
import { useMediaQuery } from '@vueuse/core';
import { SlidersHorizontal } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Sheet, SheetClose, SheetContent, SheetFooter, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import {
    Drawer,
    DrawerClose,
    DrawerContent,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
    DrawerTrigger,
} from '@/components/ui/drawer';

interface Props {
    filterCount?: number;
}

withDefaults(defineProps<Props>(), {
    filterCount: 0,
});

const emit = defineEmits<{
    filter: [];
    clear: [];
}>();

const isDesktop = useMediaQuery('(min-width: 768px)');
const sheetOpen = ref(false);
const drawerCloseRef = ref(null);

function handleFilter() {
    if (isDesktop.value) {
        sheetOpen.value = false;
    } else {
        drawerCloseRef.value?.click();
    }
    emit('filter');
}

function handleClear() {
    if (isDesktop.value) {
        sheetOpen.value = false;
    } else {
        drawerCloseRef.value?.click();
    }
    emit('clear');
}
</script>

<template>
    <div v-if="isDesktop">
        <Sheet v-model:open="sheetOpen">
            <SheetTrigger as-child>
                <Button variant="outline" class="border-2 border-primary">
                    <SlidersHorizontal class="h-4 w-4 mr-2" />
                    Filters
                    <Badge v-if="filterCount > 0" variant="default" class="ml-1 px-1.5 py-0 text-[10px] leading-4">
                        {{ filterCount }}
                    </Badge>
                </Button>
            </SheetTrigger>
            <SheetContent side="right" class="flex flex-col">
                <SheetHeader>
                    <SheetTitle>Filter & Sort</SheetTitle>
                </SheetHeader>
                <div class="flex-1 overflow-y-auto py-4">
                    <slot />
                </div>
                <SheetFooter class="flex flex-row gap-2 sm:justify-start">
                    <SheetClose as-child>
                        <Button @click="handleFilter">Search</Button>
                    </SheetClose>
                    <SheetClose as-child>
                        <Button variant="outline" @click="handleClear">Clear</Button>
                    </SheetClose>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    </div>

    <div v-else>
        <Drawer>
            <DrawerTrigger as-child>
                <div class="relative">
                    <Button variant="outline" class="border-2 border-primary">
                        <SlidersHorizontal class="h-4 w-4" />
                    </Button>
                    <Badge
                        v-if="filterCount > 0"
                        variant="default"
                        class="absolute -top-2 -right-2 px-1.5 py-0 text-[10px] leading-4 min-w-[18px] justify-center"
                    >
                        {{ filterCount }}
                    </Badge>
                </div>
            </DrawerTrigger>
            <DrawerContent>
                <div class="mx-auto w-full max-w-sm">
                    <DrawerHeader>
                        <DrawerTitle class="text-center">Filter & Sort</DrawerTitle>
                    </DrawerHeader>
                    <div class="px-4 pb-2 max-h-[60vh] overflow-y-auto">
                        <slot />
                    </div>
                    <DrawerFooter>
                        <DrawerClose as-child>
                            <button ref="drawerCloseRef" class="hidden" />
                        </DrawerClose>
                        <div class="flex justify-center gap-2">
                            <Button @click="handleFilter">Search</Button>
                            <Button variant="outline" @click="handleClear">Clear</Button>
                            <DrawerClose as-child>
                                <Button variant="destructive">Close</Button>
                            </DrawerClose>
                        </div>
                    </DrawerFooter>
                </div>
            </DrawerContent>
        </Drawer>
    </div>
</template>
