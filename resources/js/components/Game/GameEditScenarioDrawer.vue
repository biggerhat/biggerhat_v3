<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Drawer, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dices, X } from 'lucide-vue-next';

interface DeploymentOption {
    value: string;
    label: string;
}
interface StrategyOption {
    id: number;
    name: string;
}
interface SchemeOption {
    id: number;
    name: string;
}

defineProps<{
    open: boolean;
    deployments: DeploymentOption[];
    strategies: StrategyOption[];
    editDeployment: string | null;
    editStrategy: string | null;
    editSchemePool: (string | null)[];
    /** Returns the scheme options for a given scheme-pool index. Parent computes this
     *  (it has the full scheme data + game context). */
    availableSchemes: (index: number) => SchemeOption[];
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'update:editDeployment', value: string | null): void;
    (e: 'update:editStrategy', value: string | null): void;
    (e: 'update:editSchemePoolAt', index: number, value: string | null): void;
    (e: 'regenerate'): void;
    (e: 'save'): void;
}>();
</script>

<template>
    <Drawer :open="open" @update:open="emit('update:open', $event)">
        <DrawerContent>
            <button
                class="absolute right-3 top-3 z-10 rounded-full bg-muted p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                aria-label="Close"
                @click="emit('update:open', false)"
            >
                <X class="size-4" />
            </button>
            <div class="mx-auto w-full max-w-md">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">Edit Scenario</DrawerTitle>
                </DrawerHeader>
                <div class="space-y-4 px-4 pb-4">
                    <div class="space-y-1.5">
                        <label class="text-xs font-medium text-muted-foreground">Deployment</label>
                        <Select :model-value="editDeployment ?? undefined" @update:model-value="(v) => emit('update:editDeployment', (v as string) ?? null)">
                            <SelectTrigger><SelectValue placeholder="Select Deployment" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="d in deployments" :key="d.value" :value="d.value">{{ d.label }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-medium text-muted-foreground">Strategy</label>
                        <Select :model-value="editStrategy ?? undefined" @update:model-value="(v) => emit('update:editStrategy', (v as string) ?? null)">
                            <SelectTrigger><SelectValue placeholder="Select Strategy" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="s in strategies" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-medium text-muted-foreground">Scheme Pool</label>
                        <div class="space-y-2">
                            <Select
                                v-for="(_, idx) in editSchemePool"
                                :key="'es-' + idx"
                                :model-value="editSchemePool[idx] ?? undefined"
                                @update:model-value="(v) => emit('update:editSchemePoolAt', idx, (v as string) ?? null)"
                            >
                                <SelectTrigger><SelectValue :placeholder="'Scheme ' + (idx + 1)" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in availableSchemes(idx)" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>
                <DrawerFooter class="flex-row gap-2 pt-2">
                    <Button variant="outline" class="flex-1 gap-1" @click="emit('regenerate')">
                        <Dices class="size-3.5" /> Randomize
                    </Button>
                    <Button class="flex-1" @click="emit('save')">Save</Button>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>
</template>
