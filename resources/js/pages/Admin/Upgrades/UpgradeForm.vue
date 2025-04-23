<script setup lang='ts'>
    import { ref, onMounted } from 'vue';
    import { router } from '@inertiajs/vue3';
    import { Button } from '@/components/ui/button';
    import {
        Card,
        CardContent,
        CardDescription,
        CardFooter,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { Input } from '@/components/ui/input'
    import { Label } from '@/components/ui/label'
    import { Textarea } from '@/components/ui/textarea'
    import {Select, SelectContent, SelectItem, SelectTrigger, SelectValue} from "@/components/ui/select";
    import {
        NumberField,
        NumberFieldContent,
        NumberFieldDecrement,
        NumberFieldIncrement, NumberFieldInput
    } from "@/components/ui/number-field";
    import {Switch} from "@/components/ui/switch";
    import CustomMultiselect from "@/components/CustomMultiselect.vue";

    const props = defineProps({
        upgrade: {
            type: [Object, Array],
            required: false,
            default() {
                return null;
            }
        },
        characters: {
            type: [Object, Array],
            required: false,
            default() {
                return [];
            }
        },
        upgrade_types: {
            type: [Object, Array],
            required: false,
            default() {
                return [];
            }
        },
        tokens: {
            type: [Object, Array],
            required: false,
            default() {
                return [];
            }
        },
        markers: {
            type: [Object, Array],
            required: false,
            default() {
                return [];
            }
        },
        triggers: {
            type: [Object, Array],
            required: false,
            default() {
                return [];
            }
        },
        actions: {
            type: [Object, Array],
            required: false,
            default() {
                return [];
            }
        },
        abilities: {
            type: [Object, Array],
            required: false,
            default() {
                return [];
            }
        }
    });

    const formInfo = ref({
        name: null,
        master_id: null,
        type: null,
        description: null,
        power_bar_count: null,
        front_image: null,
        back_image: null,
        combination_image: null,
        plentiful: null,
        limitations: null,
        tokens: [],
        markers: [],
        triggers: [],
        actions: [],
        signature_actions: [],
        abilities: [],
    });

    const submit = () => {
        router.post(props.upgrade ? route("admin.upgrades.update", props.upgrade.slug) : route("admin.upgrades.store"),
            formInfo.value
        );
    };

    onMounted(() => {
        formInfo.value.name = props.upgrade?.name ?? null;
        formInfo.value.master_id = props.upgrade?.master_id ?? null;
        formInfo.value.type = props.upgrade?.type ?? null;
        formInfo.value.description = props.upgrade?.description ?? null;
        formInfo.value.power_bar_count = props.upgrade?.power_bar_count ?? null;
        formInfo.value.plentiful = props.upgrade?.plentiful ?? null;
        formInfo.value.limitations = props.upgrade?.limitations ?? null;

        props.upgrade?.markers.forEach((marker) => {
            formInfo.value.markers.push(marker.name);
        });

        props.upgrade?.tokens.forEach((token) => {
            formInfo.value.tokens.push(token.name);
        });

        props.upgrade?.triggers.forEach((trigger) => {
            formInfo.value.triggers.push(trigger.name);
        });

        props.upgrade?.actions.forEach((action) => {
            if (action.pivot.is_signature_action) {
                formInfo.value.signature_actions.push(action.id + ' ' + action.name + ' ' + action.internal_notes);
            } else {
                formInfo.value.actions.push(action.id + ' ' + action.name + ' ' + action.internal_notes);
            }
        });

        props.upgrade?.abilities.forEach((ability) => {
            formInfo.value.abilities.push(ability.name);
        });
    });
</script>

<template>
    <div class="container mx-auto mt-6">
        <Card>
            <CardHeader>
                <CardTitle>Upgrade</CardTitle>
                <CardDescription>Create and Edit Upgrade Information</CardDescription>
            </CardHeader>
            <CardContent>
                <form>
                    <div class="grid items-center w-full gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="formInfo.name" autofocus placeholder="Upgrade Name" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="type">Type</Label>
                            <Select id="type" v-model="formInfo.type">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select Upgrade Type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="type in props.upgrade_types" :value="type.value" :key="type.value">
                                        {{ type.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="character">Master (Only If Crew Upgrade)</Label>
                                    <Select id="character" v-model="formInfo.master_id">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Character" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="character in props.characters" :value="character.value" :key="character.value">
                                                {{ character.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="power_bar_count">Power Bar Count</Label>
                                    <Input id="power_bar_count" v-model="formInfo.power_bar_count" type="number" placeholder="Power Bar Count (Optional)" />
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="plentiful">Plentiful Count</Label>
                                    <Input id="plentiful" v-model="formInfo.plentiful" type="number" placeholder="Plentiful Count (Optional)" />
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="limitations">Limitations</Label>
                                    <Input id="limitations" v-model="formInfo.limitations" placeholder="Limitations (Optional)" />
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="flex flex-col space-y-1.5">
                                <Label for="description">Upgrade Text</Label>
                                <Textarea id="description" v-model="formInfo.description" placeholder="Type the upgrade text here." />
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="flex flex-col w-full max-w-sm items-center gap-1.5 space-y-1.5">
                                    <Label for="front_image">Front of Card Image</Label>
                                    <Input id="front_image" type="file" accept=".heic, .jpeg, .jpg, .png, .webp" @input="formInfo.front_image = $event.target.files[0]" />
                                </div>
                                <div class="flex flex-col w-full max-w-sm items-center gap-1.5 space-y-1.5">
                                    <Label for="back_image">Back of Card Image</Label>
                                    <Input id="back_image" type="file" accept=".heic, .jpeg, .jpg, .png, .webp" @input="formInfo.back_image = $event.target.files[0]" />
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="flex flex-col space-y-1.5">
                                    <CustomMultiselect v-model="formInfo.markers" comboTitle="Select Markers" :choiceOptions="props.markers" />
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <CustomMultiselect v-model="formInfo.tokens" comboTitle="Select Tokens" :choiceOptions="props.tokens" />
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="flex flex-col space-y-1.5">
                                    <CustomMultiselect v-model="formInfo.abilities" comboTitle="Select Abilities" :choiceOptions="props.abilities" />
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="flex flex-col space-y-1.5">
                                    <CustomMultiselect v-model="formInfo.actions" comboTitle="Select Actions" :choiceOptions="props.actions" />
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <CustomMultiselect v-model="formInfo.signature_actions" comboTitle="Select Signature Actions" :choiceOptions="props.actions" />
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <CustomMultiselect v-model=formInfo.triggers comboTitle="Select Triggers" :choice-options="props.triggers" />
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button @click="router.get(route('admin.upgrades.index'))" variant="outline">
                    Cancel
                </Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
