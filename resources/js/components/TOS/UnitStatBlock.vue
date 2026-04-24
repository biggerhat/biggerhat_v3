<script setup lang="ts">
interface Side {
    side: string;
    speed: number;
    defense: number;
    willpower: number;
    armor: number;
}

defineProps<{
    side: Side;
    label?: string;
}>();
</script>

<template>
    <div class="rounded-md border bg-card p-3">
        <p v-if="label" class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">{{ label }}</p>
        <div class="grid grid-cols-4 gap-2 text-center text-xs">
            <div>
                <p class="text-[10px] uppercase text-muted-foreground">Sp</p>
                <p class="text-base font-semibold tabular-nums">{{ side.speed }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase text-muted-foreground">Df</p>
                <p class="text-base font-semibold tabular-nums">{{ side.defense }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase text-muted-foreground">Wp</p>
                <p class="text-base font-semibold tabular-nums">{{ side.willpower }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase text-muted-foreground">Ar</p>
                <p class="text-base font-semibold tabular-nums">{{ side.armor }}</p>
            </div>
        </div>
    </div>
</template>
