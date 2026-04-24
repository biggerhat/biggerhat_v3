<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchableMultiselect from '@/components/SearchableMultiselect.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

interface EnvoyRow {
    id: number;
    allegiance_id: number;
    name: string;
    keyword: string | null;
    restriction: string;
    body: string | null;
    image_path: string | null;
    sort_order: number;
    abilities: Array<{ id: number }>;
}

const props = defineProps<{
    envoy?: EnvoyRow | null;
    allegiances: Array<{ id: number; name: string; is_syndicate: boolean }>;
    restrictions: Array<{ name: string; value: string }>;
    abilities: Array<{ id: number; name: string }>;
}>();

const formInfo = ref({
    allegiance_id: null as number | null,
    name: '' as string,
    keyword: null as string | null,
    restriction: 'malifaux' as string,
    body: null as string | null,
    image_path: null as File | null,
    sort_order: 0 as number,
    ability_ids: [] as string[],
});

const existingImage = computed<string | null>(() => {
    const path = props.envoy?.image_path;
    if (!path) return null;
    return path.startsWith('/') || path.startsWith('http') ? path : `/storage/${path}`;
});

const submit = () => {
    const payload = {
        ...formInfo.value,
        ability_ids: formInfo.value.ability_ids.map((v) => Number.parseInt(v, 10)),
    };
    if (props.envoy) router.post(route('admin.tos.envoys.update', props.envoy.slug), payload);
    else router.post(route('admin.tos.envoys.store'), payload);
};

onMounted(() => {
    if (!props.envoy) return;
    formInfo.value.allegiance_id = props.envoy.allegiance_id;
    formInfo.value.name = props.envoy.name;
    formInfo.value.keyword = props.envoy.keyword;
    formInfo.value.restriction = props.envoy.restriction;
    formInfo.value.body = props.envoy.body;
    formInfo.value.sort_order = props.envoy.sort_order;
    formInfo.value.ability_ids = props.envoy.abilities.map((a) => String(a.id));
});
</script>

<template>
    <Head title="TOS Envoy — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader><CardTitle>{{ envoy ? 'Edit Envoy' : 'New Envoy' }}</CardTitle></CardHeader>
            <CardContent class="space-y-3">
                <div>
                    <Label for="allegiance_id">Allegiance / Syndicate</Label>
                    <Select v-model.number="formInfo.allegiance_id">
                        <SelectTrigger><SelectValue placeholder="Allegiance" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="a in allegiances" :key="a.id" :value="a.id">
                                {{ a.name }}<span v-if="a.is_syndicate"> (Syndicate)</span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="usePage().props.errors.allegiance_id" />
                </div>
                <div>
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="formInfo.name" />
                    <InputError :message="usePage().props.errors.name" />
                </div>
                <div>
                    <Label for="keyword">Keyword</Label>
                    <Input id="keyword" v-model="formInfo.keyword" placeholder="Envoy" />
                </div>
                <div>
                    <Label for="restriction">Restriction</Label>
                    <Select v-model="formInfo.restriction">
                        <SelectTrigger><SelectValue placeholder="Restriction" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="r in restrictions" :key="r.value" :value="r.value">{{ r.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div>
                    <Label for="body">Body</Label>
                    <Textarea id="body" v-model="formInfo.body" />
                </div>
                <div class="space-y-1.5">
                    <Label for="image_path">Card Image</Label>
                    <img
                        v-if="existingImage"
                        :src="existingImage"
                        :alt="formInfo.name || 'Current image'"
                        class="h-40 w-auto rounded border object-cover"
                    />
                    <Input
                        id="image_path"
                        type="file"
                        accept="image/*"
                        @input="formInfo.image_path = ($event.target as HTMLInputElement).files?.[0] ?? null"
                    />
                    <p class="text-[11px] text-muted-foreground">
                        {{ existingImage ? 'Choose a new file to replace, or leave empty to keep.' : 'PNG / JPG up to 30 MB.' }}
                    </p>
                    <InputError :message="usePage().props.errors.image_path" />
                </div>
                <div>
                    <Label for="sort_order">Sort Order</Label>
                    <Input id="sort_order" v-model.number="formInfo.sort_order" type="number" min="0" />
                </div>
                <div>
                    <Label>Abilities</Label>
                    <SearchableMultiselect
                        v-model="formInfo.ability_ids"
                        placeholder="Search abilities…"
                        :options="abilities"
                        option-value="id"
                    />
                </div>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button variant="outline" @click="router.get(route('admin.tos.envoys.index'))">Cancel</Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
