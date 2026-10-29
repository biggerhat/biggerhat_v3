<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { router } from '@inertiajs/vue3';
import { Eye, Pencil, Trash2 } from 'lucide-vue-next';

import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

const props = defineProps({
    name: {
        type: String,
        required: false,
        default() {
            return '';
        },
    },
    previewRoute: {
        type: String,
        required: false,
        default() {
            return null;
        },
    },
    editRoute: {
        type: String,
        required: false,
        default() {
            return null;
        },
    },
    deleteRoute: {
        type: String,
        required: false,
        default() {
            return null;
        },
    },
});
</script>

<template>
    <TooltipProvider v-if="props.previewRoute">
        <Tooltip>
            <TooltipTrigger as-child>
                <Button aria-label="Preview" class="mx-2" variant="outline" @click="router.get(props.previewRoute)">
                    <Eye class="h-4 w-4" />
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                <p>Preview {{ props.name }}</p>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>

    <TooltipProvider v-if="props.editRoute">
        <Tooltip>
            <TooltipTrigger as-child>
                <Button aria-label="Edit" class="mx-2 dark:bg-[#449e48]" @click="router.get(props.editRoute)">
                    <Pencil class="h-4 w-4" />
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                <p>Edit {{ props.name }}</p>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>

    <Dialog v-if="props.deleteRoute">
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger as-child>
                    <DialogTrigger as-child>
                        <Button aria-label="Delete" class="mx-2 bg-destructive">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </DialogTrigger>
                </TooltipTrigger>
                <TooltipContent>
                    <p>Delete {{ props.name }}</p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
        <DialogContent>
            <DialogHeader>
                <DialogTitle> Delete {{ props.name }} </DialogTitle>
                <DialogDescription> Are you sure you want to delete {{ props.name }}? </DialogDescription>
            </DialogHeader>

            <DialogFooter class="sm:justify-start">
                <DialogClose as-child>
                    <Button class="bg-destructive" @click="router.post(props.deleteRoute)"> Delete {{ props.name }} </Button>
                </DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
