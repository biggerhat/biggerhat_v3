<script setup lang="ts">
import GameTextEditor from '@/components/CardCreator/GameTextEditor.vue';
import IconTokenPalette from '@/components/CardCreator/IconTokenPalette.vue';
import type { MarkerData, TokenData } from '@/components/CardCreator/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { useLatestFetch } from '@/composables/useLatestFetch';
import { ChevronDown, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

/** Back face of a crew card: the tokens and markers it lists. Edits the parent's arrays in place. */
const backTokens = defineModel<TokenData[]>('tokens', { required: true });
const backMarkers = defineModel<MarkerData[]>('markers', { required: true });

const fetchLatest = useLatestFetch();

// Back face helpers
const addToken = () => backTokens.value.push({ name: '', description: null });
const removeToken = (index: number) => backTokens.value.splice(index, 1);
const addMarker = () => backMarkers.value.push({ name: '', description: null });
const removeMarker = (index: number) => backMarkers.value.splice(index, 1);

// Token/Marker search
const tokenSearchResults = ref<any[]>([]);
const markerSearchResults = ref<any[]>([]);
let tokenSearchDebounce: ReturnType<typeof setTimeout>;
let markerSearchDebounce: ReturnType<typeof setTimeout>;

const searchTokens = (q: string) => {
    clearTimeout(tokenSearchDebounce);
    if (q.length < 2) {
        tokenSearchResults.value = [];
        return;
    }
    tokenSearchDebounce = setTimeout(async () => {
        tokenSearchResults.value = (await fetchLatest<any[]>('tokens', route('api.card-creator.tokens') + '?q=' + encodeURIComponent(q))) ?? [];
    }, 300);
};

const pickToken = (official: any) => {
    backTokens.value.push({ name: official.name, description: official.description, source_id: official.source_id });
    tokenSearchResults.value = [];
};

const searchMarkers = (q: string) => {
    clearTimeout(markerSearchDebounce);
    if (q.length < 2) {
        markerSearchResults.value = [];
        return;
    }
    markerSearchDebounce = setTimeout(async () => {
        markerSearchResults.value = (await fetchLatest<any[]>('markers', route('api.card-creator.markers') + '?q=' + encodeURIComponent(q))) ?? [];
    }, 300);
};

const pickMarker = (official: any) => {
    backMarkers.value.push({ name: official.name, description: official.description, source_id: official.source_id });
    markerSearchResults.value = [];
};
</script>

<template>
    <Collapsible default-open>
        <Card>
            <CardContent class="p-4">
                <CollapsibleTrigger class="flex w-full items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-semibold">Back — Tokens & Markers</h3>
                        <Badge v-if="backTokens.length + backMarkers.length" variant="secondary" class="text-2xs">{{
                            backTokens.length + backMarkers.length
                        }}</Badge>
                    </div>
                    <ChevronDown class="size-4 text-muted-foreground transition-transform [[data-state=open]_&]:rotate-180" />
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <div class="mt-3 space-y-4">
                        <!-- Tokens -->
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <label class="text-xs font-medium text-muted-foreground">Tokens</label>
                                <Button variant="outline" size="sm" class="h-7 text-[11px]" @click="addToken"
                                    ><Plus class="mr-1 size-3" /> Custom</Button
                                >
                            </div>
                            <div class="relative mb-2">
                                <Input
                                    placeholder="Search official tokens..."
                                    class="h-8 text-xs"
                                    @input="(e: Event) => searchTokens((e.target as HTMLInputElement).value)"
                                />
                                <div v-if="tokenSearchResults.length" class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md">
                                    <button
                                        v-for="r in tokenSearchResults"
                                        :key="r.id"
                                        class="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                        @click="pickToken(r)"
                                    >
                                        <span class="font-medium">{{ r.name }}</span>
                                        <Badge variant="outline" class="ml-2 px-1 py-0 text-2xs">Official</Badge>
                                    </button>
                                </div>
                            </div>
                            <div v-for="(token, tIdx) in backTokens" :key="'token-' + tIdx" class="mb-2 flex items-start gap-2 rounded-lg border p-2">
                                <div class="flex-1 space-y-1">
                                    <template v-if="token.source_id">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-medium">{{ token.name }}</span>
                                            <Badge variant="outline" class="px-1 py-0 text-2xs">Official</Badge>
                                        </div>
                                        <div v-if="token.description" class="text-xs text-muted-foreground">
                                            {{ token.description }}
                                        </div>
                                    </template>
                                    <template v-else>
                                        <Input v-model="token.name" placeholder="Token name (e.g. Fast, Shielded)" class="h-7 text-xs font-medium" />
                                        <GameTextEditor
                                            v-model="token.description"
                                            placeholder="Token effect description..."
                                            multiline
                                            :rows="2"
                                            class="text-xs"
                                        />
                                        <IconTokenPalette class="mt-1" />
                                    </template>
                                </div>
                                <button
                                    aria-label="Remove token"
                                    class="mt-1 text-muted-foreground hover:text-destructive"
                                    @click="removeToken(tIdx)"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </div>
                        </div>

                        <!-- Markers -->
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <label class="text-xs font-medium text-muted-foreground">Markers</label>
                                <Button variant="outline" size="sm" class="h-7 text-[11px]" @click="addMarker"
                                    ><Plus class="mr-1 size-3" /> Custom</Button
                                >
                            </div>
                            <div class="relative mb-2">
                                <Input
                                    placeholder="Search official markers..."
                                    class="h-8 text-xs"
                                    @input="(e: Event) => searchMarkers((e.target as HTMLInputElement).value)"
                                />
                                <div v-if="markerSearchResults.length" class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md">
                                    <button
                                        v-for="r in markerSearchResults"
                                        :key="r.id"
                                        class="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                        @click="pickMarker(r)"
                                    >
                                        <span class="font-medium">{{ r.name }}</span>
                                        <Badge variant="outline" class="ml-2 px-1 py-0 text-2xs">Official</Badge>
                                    </button>
                                </div>
                            </div>
                            <div
                                v-for="(marker, mIdx) in backMarkers"
                                :key="'marker-' + mIdx"
                                class="mb-2 flex items-start gap-2 rounded-lg border p-2"
                            >
                                <div class="flex-1 space-y-1">
                                    <template v-if="marker.source_id">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-medium">{{ marker.name }}</span>
                                            <Badge variant="outline" class="px-1 py-0 text-2xs">Official</Badge>
                                        </div>
                                        <div v-if="marker.description" class="text-xs text-muted-foreground">
                                            {{ marker.description }}
                                        </div>
                                    </template>
                                    <template v-else>
                                        <Input v-model="marker.name" placeholder="Marker name" class="h-7 text-xs font-medium" />
                                        <GameTextEditor
                                            v-model="marker.description"
                                            placeholder="Marker description..."
                                            multiline
                                            :rows="2"
                                            class="text-xs"
                                        />
                                        <IconTokenPalette class="mt-1" />
                                    </template>
                                </div>
                                <button
                                    aria-label="Remove marker"
                                    class="mt-1 text-muted-foreground hover:text-destructive"
                                    @click="removeMarker(mIdx)"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </CollapsibleContent>
            </CardContent>
        </Card>
    </Collapsible>
</template>
