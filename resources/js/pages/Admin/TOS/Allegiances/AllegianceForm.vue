<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { TosSelectOption } from '@/types/tos';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

interface Allegiance {
    id: number;
    slug: string;
    name: string;
    short_name: string | null;
    type: string;
    secondary_type: string | null;
    is_syndicate: boolean;
    description: string | null;
    logo_path: string | null;
    color_slug: string | null;
    sort_order: number;
}

const props = defineProps<{
    allegiance?: Allegiance | null;
    allegiance_types: TosSelectOption[];
}>();

const formInfo = ref({
    name: '' as string,
    short_name: null as string | null,
    type: 'earth' as string,
    secondary_type: null as string | null,
    is_syndicate: false as boolean,
    description: null as string | null,
    logo_path: null as File | null,
    color_slug: null as string | null,
    sort_order: 0 as number,
});

/** Server-side path of the current logo when editing — used only for preview, never re-sent. */
const existingLogo = computed<string | null>(() => {
    const path = props.allegiance?.logo_path;
    if (!path) return null;
    return path.startsWith('/') || path.startsWith('http') ? path : `/storage/${path}`;
});

const submit = () => {
    // forceFormData is required because logo_path is a File. Without it Inertia
    // JSON-encodes the payload and the file silently becomes a string, so the
    // server's hasFile() check fails and the image update is skipped.
    const options = { forceFormData: true };
    if (props.allegiance) {
        router.post(route('admin.tos.allegiances.update', props.allegiance.slug), formInfo.value, options);
    } else {
        router.post(route('admin.tos.allegiances.store'), formInfo.value, options);
    }
};

onMounted(() => {
    if (!props.allegiance) return;
    // Don't copy logo_path over — it's a string on the server, but formInfo's
    // logo_path is a File-or-null payload. Leaving it null means "no change".
    formInfo.value.name = props.allegiance.name;
    formInfo.value.short_name = props.allegiance.short_name;
    formInfo.value.type = props.allegiance.type;
    formInfo.value.secondary_type = props.allegiance.secondary_type ?? null;
    formInfo.value.is_syndicate = props.allegiance.is_syndicate;
    formInfo.value.description = props.allegiance.description;
    formInfo.value.color_slug = props.allegiance.color_slug;
    formInfo.value.sort_order = props.allegiance.sort_order;
});
</script>

<template>
    <Head title="TOS Allegiance - Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader>
                <CardTitle>{{ allegiance ? 'Edit Allegiance' : 'New Allegiance' }}</CardTitle>
                <CardDescription>The Other Side allegiance / syndicate</CardDescription>
            </CardHeader>
            <CardContent>
                <form>
                    <div class="grid w-full items-center gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="formInfo.name" placeholder="King's Empire" />
                            <InputError :message="usePage().props.errors.name" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="short_name">Short Name</Label>
                            <Input id="short_name" v-model="formInfo.short_name" placeholder="KE" />
                            <InputError :message="usePage().props.errors.short_name" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="type">Type</Label>
                            <Select id="type" v-model="formInfo.type">
                                <SelectTrigger>
                                    <SelectValue placeholder="Type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="t in props.allegiance_types" :key="t.value" :value="t.value">{{ t.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="usePage().props.errors.type" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="secondary_type">Secondary Type (Hybrid only)</Label>
                            <Select id="secondary_type" v-model="formInfo.secondary_type">
                                <SelectTrigger>
                                    <SelectValue placeholder="None — single-type Allegiance" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="null">None</SelectItem>
                                    <SelectItem v-for="t in props.allegiance_types" :key="t.value" :value="t.value">{{ t.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-[11px] text-muted-foreground">
                                Set only when an Allegiance lists both Earth and Malifaux on its card. Hybrid Allegiances pull
                                Neutral hires, Envoys, and type-restricted Stratagems from both sides.
                            </p>
                            <InputError :message="usePage().props.errors.secondary_type" />
                        </div>
                        <div class="flex items-center gap-2">
                            <Checkbox id="is_syndicate" v-model:checked="formInfo.is_syndicate" />
                            <Label for="is_syndicate">Syndicate (hireable into matching-type allegiances via Envoy)</Label>
                            <InputError :message="usePage().props.errors.is_syndicate" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="description">Description</Label>
                            <Textarea id="description" v-model="formInfo.description" placeholder="Lore / overview text" />
                            <InputError :message="usePage().props.errors.description" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="logo_path">Logo</Label>
                            <img
                                v-if="existingLogo"
                                :src="existingLogo"
                                :alt="formInfo.name || 'Current logo'"
                                class="h-20 w-20 rounded border object-cover"
                            />
                            <Input
                                id="logo_path"
                                type="file"
                                accept="image/*"
                                @input="formInfo.logo_path = ($event.target as HTMLInputElement).files?.[0] ?? null"
                            />
                            <p class="text-[11px] text-muted-foreground">
                                {{ existingLogo ? 'Choose a new file to replace the current logo, or leave empty to keep it.' : 'PNG / JPG up to 30 MB.' }}
                            </p>
                            <InputError :message="usePage().props.errors.logo_path" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="color_slug">Color Slug</Label>
                            <Input id="color_slug" v-model="formInfo.color_slug" placeholder="abyssinia" />
                            <InputError :message="usePage().props.errors.color_slug" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="sort_order">Sort Order</Label>
                            <Input id="sort_order" v-model.number="formInfo.sort_order" type="number" min="0" />
                            <InputError :message="usePage().props.errors.sort_order" />
                        </div>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button variant="outline" @click="router.get(route('admin.tos.allegiances.index'))">Cancel</Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
