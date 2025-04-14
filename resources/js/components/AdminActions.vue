<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger
} from '@/components/ui/tooltip'

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogClose,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog'

const props = defineProps({
    name: {
        type: String,
        required: false,
        default() {
            return '';
        }
    },
    editRoute: {
        type: String,
        required: false,
        default() {
            return null;
        }
    },
    deleteRoute: {
        type: String,
        required: false,
        default() {
            return null;
        }
    }
})
</script>

<template>
    <TooltipProvider>
        <Tooltip>
            <TooltipTrigger>
                <Button class="dark:bg-[#449e48] mx-2" v-if="props.editRoute" @click="router.get(props.editRoute)">
                    <Pencil class="h-4 w-4" />
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                <p>Edit {{ props.name }}</p>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>


    <Dialog>
        <DialogTrigger>
            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger>
                        <Button class="bg-destructive mx-2" v-if="props.deleteRoute">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                        <p>Delete {{ props.name }}</p>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    Delete {{ props.name }}
                </DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete {{ props.name }}?
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="sm:justify-start">
                <DialogClose as-child>
                    <Button class="bg-destructive" v-if="props.deleteRoute" @click="router.post(props.deleteRoute)">
                        Delete {{ props.name }}
                    </Button>
                </DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
