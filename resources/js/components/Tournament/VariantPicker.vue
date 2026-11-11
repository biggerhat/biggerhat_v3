<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import type { RulesetVariant } from '@/types/tournament';

/**
 * Optional rule variants a TO can switch on for an event (the ruleset's catalog). The model is
 * `{ key: true }` for plain variants and `{ key: number }` for parameterised ones (e.g. Bans X).
 */
const props = defineProps<{ variants: RulesetVariant[] }>();
const model = defineModel<Record<string, boolean | number>>({ default: () => ({}) });

const isOn = (v: RulesetVariant) => model.value[v.key] !== undefined && model.value[v.key] !== false;

const toggle = (v: RulesetVariant, on: boolean | 'indeterminate') => {
    const next = { ...model.value };
    if (on === true) next[v.key] = v.param ? v.param.default : true;
    else delete next[v.key];
    model.value = next;
};

const setParam = (v: RulesetVariant, raw: string | number) => {
    const n = Math.round(Number(raw));
    if (!Number.isFinite(n) || !v.param) return;
    model.value = { ...model.value, [v.key]: Math.min(v.param.max, Math.max(v.param.min, n)) };
};

defineExpose({ count: () => props.variants.filter(isOn).length });
</script>

<template>
    <ul class="space-y-3">
        <li v-for="v in variants" :key="v.key" class="flex items-start gap-3">
            <Checkbox :id="`variant-${v.key}`" class="mt-0.5" :model-value="isOn(v)" @update:model-value="(on) => toggle(v, on)" />
            <div class="min-w-0 flex-1">
                <label :for="`variant-${v.key}`" class="text-sm font-medium">{{ v.label }}</label>
                <p class="text-2xs text-muted-foreground">{{ v.description }}</p>
                <div v-if="v.param && isOn(v)" class="mt-1.5 flex items-center gap-2">
                    <label :for="`variant-${v.key}-param`" class="text-2xs text-muted-foreground">{{ v.param.label }}</label>
                    <Input
                        :id="`variant-${v.key}-param`"
                        type="number"
                        class="h-8 w-20 text-center text-sm"
                        :min="v.param.min"
                        :max="v.param.max"
                        :model-value="Number(model[v.key])"
                        @update:model-value="(raw) => setParam(v, raw)"
                    />
                </div>
            </div>
        </li>
    </ul>
</template>
