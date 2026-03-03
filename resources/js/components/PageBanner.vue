<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

interface Props {
    title: string;
    class?: HTMLAttributes['class'];
    accentColor?: string;
}

const props = defineProps<Props>();
</script>

<template>
    <div :class="cn('flex w-full bg-secondary mb-4', props.class, accentColor ? `border-b-4 ${accentColor}` : '')">
        <div class="container mx-auto items-center">
            <div class="flex justify-between">
                <div class="py-2 md:py-4 flex w-full">
                    <slot name="logo" />
                    <div class="flex justify-between w-full md:block" id="page-banner">
                        <div class="p-2 font-bold text-xl my-auto">{{ title }}</div>
                        <slot name="subtitle" />
                    </div>
                </div>
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
