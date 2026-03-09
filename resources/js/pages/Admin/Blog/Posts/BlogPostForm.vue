<script setup lang="ts">
import TipTapEditor from '@/components/blog/TipTapEditor.vue';
import EntityTagger, { type TaggedEntity } from '@/components/EntityTagger.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

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
    });
};

const availableStatuses = () => {
    if (page.props.auth.can_publish_posts) {
        return props.statuses;
    }
    return props.statuses.filter((s: SelectOption) => s.value !== 'published');
};

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
});
</script>

<template>
    <div class="container mx-auto mb-6 mt-6">
        <Card>
            <CardHeader>
                <CardTitle>Article</CardTitle>
                <CardDescription>Create and Edit Articles</CardDescription>
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
