import EntityEmbedNodeView from '@/components/blog/EntityEmbedNodeView.vue';
import { mergeAttributes, Node } from '@tiptap/core';
import { VueNodeViewRenderer } from '@tiptap/vue-3';

export interface EntityEmbedOptions {
    HTMLAttributes: Record<string, string>;
}

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        entityEmbed: {
            insertEntityEmbed: (attrs: { entityType: string; entityId: string | number; entitySlug: string; displayName: string }) => ReturnType;
        };
    }
}

export const EntityEmbed = Node.create<EntityEmbedOptions>({
    name: 'entityEmbed',

    group: 'block',

    inline: false,

    atom: true,

    addAttributes() {
        return {
            entityType: { default: null },
            entityId: { default: null },
            entitySlug: { default: null },
            displayName: { default: null },
        };
    },

    parseHTML() {
        return [{ tag: 'entity-embed' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['entity-embed', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes)];
    },

    addNodeView() {
        return VueNodeViewRenderer(EntityEmbedNodeView);
    },

    addCommands() {
        return {
            insertEntityEmbed:
                (attrs) =>
                ({ commands }) => {
                    return commands.insertContent({
                        type: this.name,
                        attrs,
                    });
                },
        };
    },
});

export default EntityEmbed;
