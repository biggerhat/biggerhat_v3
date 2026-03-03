<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="w-full text-center">
        <div class="mx-1">
            <Skeleton class="aspect-[3/4] w-full rounded-lg" />
        </div>
        <Skeleton class="mx-auto mt-2 h-4 w-24" />
    </div>
</template>
