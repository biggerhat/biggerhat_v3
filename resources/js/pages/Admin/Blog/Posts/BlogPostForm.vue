<script setup lang="ts">
import TipTapEditor from '@/components/blog/TipTapEditor.vue';
import EntityTagger, { type TaggedEntity } from '@/components/EntityTagger.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface SelectOption {
    name: string;
    value: string | number;
}

const page = usePage<SharedData>();

const props = defineProps({
    post: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        },
    },
    categories: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
    statuses: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
});

const formInfo = ref({
    title: null as string | null,
    content: null as Record<string, unknown> | null,
    excerpt: null as string | null,
    featured_image: null as File | null,
    status: 'draft',
    blog_category_id: null as string | null,
    entities: [] as TaggedEntity[],
});

const currentFeaturedImage = ref<string | null>(null);

const handleImageChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files?.length) {
        formInfo.value.featured_image = target.files[0];
    }
};

const submit = () => {
    const formData = new FormData();
    formData.append('title', formInfo.value.title ?? '');
    formData.append('content', JSON.stringify(formInfo.value.content));
    formData.append('excerpt', formInfo.value.excerpt ?? '');
    formData.append('status', formInfo.value.status);
    if (formInfo.value.blog_category_id) {
        formData.append('blog_category_id', formInfo.value.blog_category_id);
    }
    if (formInfo.value.featured_image instanceof File) {
        formData.append('featured_image', formInfo.value.featured_image);
    }
    formInfo.value.entities.forEach((e, i) => formData.append(`entities[${i}]`, `${e.entityType}:${e.entitySlug}`));

    const url = props.post ? route('admin.blog.posts.update', props.post.slug) : route('admin.blog.posts.store');
    router.post(url, formData, {
        forceFormData: true,
        onSuccess: () => clearAutosave(),
    });
};

const availableStatuses = () => {
    if (page.props.auth.can_publish_posts) {
        return props.statuses;
    }
    return props.statuses.filter((s: SelectOption) => s.value !== 'published');
};

// --- Autosave ---
const AUTOSAVE_KEY = 'biggerhat_blog_autosave';
const autosaveStatus = ref<'saved' | 'saving' | 'restored' | null>(null);
let autosaveTimer: ReturnType<typeof setTimeout> | null = null;
let statusTimer: ReturnType<typeof setTimeout> | null = null;

const getAutosaveId = () => props.post?.id ?? 'new';

const saveToLocal = () => {
    try {
        const data = {
            id: getAutosaveId(),
            title: formInfo.value.title,
            content: formInfo.value.content,
            excerpt: formInfo.value.excerpt,
            status: formInfo.value.status,
            blog_category_id: formInfo.value.blog_category_id,
            entities: formInfo.value.entities,
            savedAt: Date.now(),
        };
        localStorage.setItem(AUTOSAVE_KEY, JSON.stringify(data));
        autosaveStatus.value = 'saved';
        clearTimeout(statusTimer!);
        statusTimer = setTimeout(() => (autosaveStatus.value = null), 3000);
    } catch {
        /* quota exceeded or private browsing */
    }
};

const restoreFromLocal = (): boolean => {
    try {
        const stored = localStorage.getItem(AUTOSAVE_KEY);
        if (!stored) return false;
        const data = JSON.parse(stored);
        // Only restore if it's for the same post and less than 24h old
        if (data.id !== getAutosaveId()) return false;
        if (Date.now() - data.savedAt > 86400000) {
            localStorage.removeItem(AUTOSAVE_KEY);
            return false;
        }
        // Only restore if there's actual content (not empty draft)
        if (!data.content && !data.title) return false;
        return true;
    } catch {
        return false;
    }
};

const applyLocalRestore = () => {
    try {
        const data = JSON.parse(localStorage.getItem(AUTOSAVE_KEY)!);
        formInfo.value.title = data.title;
        formInfo.value.content = data.content;
        formInfo.value.excerpt = data.excerpt;
        formInfo.value.status = data.status;
        formInfo.value.blog_category_id = data.blog_category_id;
        formInfo.value.entities = data.entities ?? [];
        autosaveStatus.value = 'restored';
        clearTimeout(statusTimer!);
        statusTimer = setTimeout(() => (autosaveStatus.value = null), 3000);
    } catch {
        /* ignore */
    }
};

const clearAutosave = () => {
    try {
        localStorage.removeItem(AUTOSAVE_KEY);
    } catch {
        /* ignore */
    }
};

const showRestorePrompt = ref(false);

const scheduleAutosave = () => {
    if (autosaveTimer) clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(saveToLocal, 5000);
};

watch(formInfo, scheduleAutosave, { deep: true });

onMounted(() => {
    formInfo.value.title = props.post?.title ?? null;
    formInfo.value.content = props.post?.content ?? null;
    formInfo.value.excerpt = props.post?.excerpt ?? null;
    formInfo.value.status = props.post?.status ?? 'draft';
    formInfo.value.blog_category_id = props.post?.blog_category_id ? String(props.post.blog_category_id) : null;
    currentFeaturedImage.value = props.post?.featured_image ?? null;

    if (props.post?.entities) {
        formInfo.value.entities = props.post.entities;
    }

    // Check for unsaved local draft
    if (restoreFromLocal()) {
        showRestorePrompt.value = true;
    }
});

onBeforeUnmount(() => {
    if (autosaveTimer) clearTimeout(autosaveTimer);
    if (statusTimer) clearTimeout(statusTimer);
});
</script>

<template>
    <div class="container mx-auto mb-6 mt-6">
        <!-- Restore from autosave prompt -->
        <div
            v-if="showRestorePrompt"
            class="mb-4 flex items-center justify-between rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm"
        >
            <span class="text-amber-700 dark:text-amber-400">An unsaved draft was found. Would you like to restore it?</span>
            <div class="flex gap-2">
                <Button
                    size="sm"
                    variant="outline"
                    @click="
                        showRestorePrompt = false;
                        clearAutosave();
                    "
                    >Discard</Button
                >
                <Button
                    size="sm"
                    @click="
                        applyLocalRestore();
                        showRestorePrompt = false;
                    "
                    >Restore</Button
                >
            </div>
        </div>

        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle>Article</CardTitle>
                        <CardDescription>Create and Edit Articles</CardDescription>
                    </div>
                    <Badge
                        v-if="autosaveStatus === 'saved'"
                        variant="outline"
                        class="border-green-500/50 text-[10px] text-green-600 dark:text-green-400"
                        >Draft saved</Badge
                    >
                    <Badge
                        v-else-if="autosaveStatus === 'restored'"
                        variant="outline"
                        class="border-amber-500/50 text-[10px] text-amber-600 dark:text-amber-400"
                        >Draft restored</Badge
                    >
                </div>
            </CardHeader>
            <CardContent>
                <form @submit.prevent>
                    <div class="grid w-full items-center gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="title">Title</Label>
                            <Input id="title" v-model="formInfo.title" placeholder="Post Title" />
                            <InputError :message="page.props.errors.title" />
                        </div>

                        <div class="flex flex-col space-y-1.5">
                            <Label for="excerpt">Excerpt</Label>
                            <Textarea id="excerpt" v-model="formInfo.excerpt" placeholder="Brief summary of the post" />
                            <InputError :message="page.props.errors.excerpt" />
                        </div>

                        <div class="flex flex-col space-y-1.5">
                            <Label>Content</Label>
                            <TipTapEditor v-model="formInfo.content" />
                            <InputError :message="page.props.errors.content" />
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="flex flex-col space-y-1.5">
                                <Label for="category">Category</Label>
                                <Select id="category" v-model="formInfo.blog_category_id">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select Category" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="cat in props.categories" :value="String(cat.value)" :key="cat.value">
                                            {{ cat.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="page.props.errors.blog_category_id" />
                            </div>

                            <div class="flex flex-col space-y-1.5">
                                <Label for="status">Status</Label>
                                <Select id="status" v-model="formInfo.status">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select Status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="status in availableStatuses()" :value="status.value" :key="status.value">
                                            {{ status.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="page.props.errors.status" />
                            </div>
                        </div>

                        <div class="flex flex-col space-y-1.5">
                            <Label for="featured_image">Featured Image</Label>
                            <div v-if="currentFeaturedImage" class="mb-2">
                                <img :src="`/storage/${currentFeaturedImage}`" class="h-32 rounded-md object-cover" alt="Current featured image" />
                            </div>
                            <Input id="featured_image" type="file" accept="image/*" @change="handleImageChange" />
                            <InputError :message="page.props.errors.featured_image" />
                        </div>

                        <div class="flex flex-col space-y-1.5">
                            <Label>Tagged Entities</Label>
                            <EntityTagger v-model="formInfo.entities" />
                        </div>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button @click="router.get(route('admin.blog.posts.index'))" variant="outline">Cancel</Button>
                <div class="flex gap-2">
                    <Button v-if="props.post" @click="router.get(route('admin.blog.posts.preview', props.post.slug))" variant="secondary"
                        >Preview</Button
                    >
                    <Button @click="submit">Save</Button>
                </div>
            </CardFooter>
        </Card>
    </div>
</template>
