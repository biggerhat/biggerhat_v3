<script setup lang="ts">
import EditorToolbar from '@/components/blog/EditorToolbar.vue';
import EntitySearchDialog from '@/components/blog/EntitySearchDialog.vue';
import EntityEmbed from '@/extensions/EntityEmbed';
import EntityReference from '@/extensions/EntityReference';
import GameIconNode from '@/extensions/GameIconNode';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
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
        Image,
        Link.configure({ openOnClick: false }),
        Placeholder.configure({ placeholder: 'Start writing your blog post...' }),
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

onBeforeUnmount(() => {
    editor.value?.destroy();
});
</script>

<template>
    <div v-if="editor" class="rounded-md border">
        <EditorToolbar :editor="editor" @open-entity-search="entitySearchOpen = true" @open-entity-embed-search="entityEmbedSearchOpen = true" />
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
