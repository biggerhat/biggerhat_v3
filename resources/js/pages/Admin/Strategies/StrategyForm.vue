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

const props = defineProps({
    strategy: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        }
    },
    seasons: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        }
    },
    suits: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        }
    }
});

const formInfo = ref({
    name: null,
    season: null,
    suit: null,
    setup: null,
    rules: null,
    scoring: null,
});

const submit = () => {
    router.post(props.strategy ? route("admin.strategies.update", props.strategy.slug) : route("admin.strategies.store"),
        formInfo.value
    );
};

onMounted(() => {
    formInfo.value.name = props.strategy?.name ?? null;
    formInfo.value.season = props.strategy?.season ?? null;
    formInfo.value.suit = props.strategy?.suit ?? null;
    formInfo.value.setup = props.strategy?.setup ?? null;
    formInfo.value.rules = props.strategy?.rules ?? null;
    formInfo.value.scoring = props.strategy?.scoring ?? null;
});
</script>

<template>
    <div class="container mx-auto mt-6">
        <Card>
            <CardHeader>
                <CardTitle>Strategy</CardTitle>
                <CardDescription>Create and Edit Strategy Information</CardDescription>
            </CardHeader>
            <CardContent>
                <form>
                    <div class="grid items-center w-full gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="formInfo.name" placeholder="Strategy Name" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="season">Season</Label>
                            <Select id="season" v-model="formInfo.season">
                                <SelectTrigger>
                                    <SelectValue placeholder="Gameplay Season" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="season in props.seasons" :value="season.value" :key="season.value">
                                        {{ season.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="suit">Suit</Label>
                            <Select id="suit" v-model="formInfo.suit">
                                <SelectTrigger>
                                    <SelectValue placeholder="Suit" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="suit in props.suits" :value="suit.value" :key="suit.value">
                                        {{ suit.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="setup">Setup</Label>
                            <Textarea id="setup" v-model="formInfo.setup" placeholder="Type the setup info here." />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="rules">Rules</Label>
                            <Textarea id="rules" v-model="formInfo.rules" placeholder="Type the rules info here." />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="scoring">Scoring</Label>
                            <Textarea id="scoring" v-model="formInfo.scoring" placeholder="Type the scoring info here." />
                        </div>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button @click="router.get(route('admin.strategies.index'))" variant="outline">
                    Cancel
                </Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
