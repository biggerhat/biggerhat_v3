<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AllegianceLogo from '@/components/AllegianceLogo.vue';

/**
 * What a player declares at check-in, driven by the ruleset: a Malifaux faction, or a TOS Allegiance
 * with an optional Garrison share code. Used by the add-player form and the edit / RSVP-to-player dialogs.
 */
defineProps<{
    declaration: string;
    factions: Record<string, { name: string; logo: string }>;
    allegiances: { id: number; slug: string; name: string }[];
    /** Hide the Garrison code input (RSVP, where a Garrison isn't linked yet). */
    hideGarrison?: boolean;
    triggerClass?: string;
}>();

const faction = defineModel<string | null>('faction', { default: null });
const allegiance = defineModel<string | null>('allegiance', { default: null });
const garrisonCode = defineModel<string>('garrisonCode', { default: '' });
</script>

<template>
    <div v-if="declaration === 'faction'" class="space-y-1">
        <Label class="text-xs">Faction</Label>
        <Select v-model="faction">
            <SelectTrigger :class="triggerClass ?? 'h-9 w-full text-xs'"><SelectValue placeholder="Select faction..." /></SelectTrigger>
            <SelectContent>
                <SelectItem v-for="(f, key) in factions" :key="key" :value="key as string">
                    <span class="flex items-center gap-1.5"><img :src="f.logo" :alt="f.name" class="size-4" /> {{ f.name }}</span>
                </SelectItem>
            </SelectContent>
        </Select>
    </div>
    <template v-else-if="declaration === 'allegiance'">
        <div class="space-y-1">
            <Label class="text-xs">Allegiance</Label>
            <Select v-model="allegiance">
                <SelectTrigger :class="triggerClass ?? 'h-9 w-full text-xs'"><SelectValue placeholder="Select allegiance..." /></SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="a in allegiances" :key="a.id" :value="String(a.id)">
                        <span class="flex items-center gap-1.5"><AllegianceLogo :allegiance="a.slug" class-name="size-4" /> {{ a.name }}</span>
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>
        <div v-if="!hideGarrison" class="space-y-1">
            <Label class="text-xs">Garrison code <span class="text-muted-foreground">(optional)</span></Label>
            <Input v-model="garrisonCode" class="h-9 text-xs" placeholder="Share code from the Garrison page" autocomplete="off" />
        </div>
    </template>
</template>
