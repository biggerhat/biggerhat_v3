<script setup lang="ts">
import EditorToolbar from '@/components/blog/EditorToolbar.vue';
import EntitySearchDialog from '@/components/blog/EntitySearchDialog.vue';
import EntityEmbed from '@/extensions/EntityEmbed';
import EntityReference from '@/extensions/EntityReference';
import GameIconNode from '@/extensions/GameIconNode';
import Highlight from '@tiptap/extension-highlight';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import { Table, TableCell, TableHeader, TableRow } from '@tiptap/extension-table';
import TextAlign from '@tiptap/extension-text-align';
import Underline from '@tiptap/extension-underline';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    modelValue?: Record<string, unknown> | null;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: Record<string, unknown>): void;
}>();

const entitySearchOpen = ref(false);
const entityEmbedSearchOpen = ref(false);

const editor = useEditor({
    extensions: [
        StarterKit,
        EntityEmbed,
        EntityReference,
        GameIconNode,
        Highlight,
        Image,
        Link.configure({ openOnClick: false }),
        Placeholder.configure({ placeholder: 'Start writing your blog post...' }),
        Table.configure({ resizable: false }),
        TableRow,
        TableHeader,
        TableCell,
        TextAlign.configure({ types: ['heading', 'paragraph'], alignments: ['left', 'center', 'right', 'justify'] }),
        Underline,
    ],
    content: props.modelValue ?? { type: 'doc', content: [] },
    onUpdate({ editor }) {
        emit('update:modelValue', editor.getJSON());
    },
});

watch(
    () => props.modelValue,
    (val) => {
        if (!editor.value) return;
        const currentJSON = JSON.stringify(editor.value.getJSON());
        const newJSON = JSON.stringify(val);
        if (currentJSON !== newJSON) {
            editor.value.commands.setContent(val ?? { type: 'doc', content: [] });
        }
    },
);

const handleEntitySelect = (entity: { entityType: string; entityId: string | number; entitySlug: string; displayName: string }) => {
    editor.value?.chain().focus().insertEntityReference(entity).run();
    entitySearchOpen.value = false;
};

const handleEntityEmbedSelect = (entity: { entityType: string; entityId: string | number; entitySlug: string; displayName: string }) => {
    editor.value?.chain().focus().insertEntityEmbed(entity).run();
    entityEmbedSearchOpen.value = false;
};

const imageInputRef = ref<HTMLInputElement | null>(null);

const handleImageUpload = () => {
    imageInputRef.value?.click();
};

const onImageFileSelected = async (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file || !editor.value) return;

    const formData = new FormData();
    formData.append('image', file);

    try {
        const res = await fetch(route('admin.blog.posts.upload-image'), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' },
            body: formData,
        });
        const data = await res.json();
        if (data.url) {
            editor.value.chain().focus().setImage({ src: data.url }).run();
        }
    } catch (err) {
        console.error('Image upload failed:', err);
    }

    // Reset input so the same file can be selected again
    if (imageInputRef.value) imageInputRef.value.value = '';
};

onBeforeUnmount(() => {
    editor.value?.destroy();
});
</script>

<template>
    <div v-if="editor" class="rounded-md border">
        <EditorToolbar :editor="editor" @open-entity-search="entitySearchOpen = true" @open-entity-embed-search="entityEmbedSearchOpen = true" @upload-image="handleImageUpload" />
        <input ref="imageInputRef" type="file" accept="image/*" class="hidden" @change="onImageFileSelected" />
        <EditorContent
            :editor="editor"
            class="prose prose-sm dark:prose-invert max-w-none p-4 focus:outline-none [&_.tiptap]:min-h-[200px] [&_.tiptap]:outline-none"
        />
    </div>
    <EntitySearchDialog v-model:open="entitySearchOpen" @select="handleEntitySelect" />
    <EntitySearchDialog
        v-model:open="entityEmbedSearchOpen"
        title="Embed Entity"
        description="Search for an entity to embed as a block preview with card images or info boxes."
        @select="handleEntityEmbedSelect"
    />
</template>
