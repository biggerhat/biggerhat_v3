<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

interface Props {
    title: string;
    class?: HTMLAttributes['class'];
    accentColor?: string;
}

const props = defineProps<Props>();
</script>

<template>
    <div :class="cn('mb-4 flex w-full bg-secondary', props.class, accentColor ? `border-b-4 ${accentColor}` : '')">
        <div class="container mx-auto items-center">
            <div class="flex justify-between">
                <div class="flex w-full py-2 md:py-4">
                    <slot name="logo" />
                    <div class="flex w-full justify-between md:block" id="page-banner">
                        <div class="my-auto p-2 text-xl font-bold">{{ title }}</div>
                        <slot name="subtitle" />
                    </div>
                </div>
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
