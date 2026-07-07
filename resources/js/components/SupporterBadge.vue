<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Heart } from 'lucide-vue-next';
</script>

<template>
    <Badge variant="outline" class="gap-1 border-rose-500/30 bg-rose-500/10 text-[10px] text-rose-600 dark:text-rose-400">
        <Heart class="size-2.5 fill-current" />
        Supporter
    </Badge>
</template>
