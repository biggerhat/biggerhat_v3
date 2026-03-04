import EntityReferenceNodeView from '@/components/blog/EntityReferenceNodeView.vue';
import { mergeAttributes, Node } from '@tiptap/core';
import { VueNodeViewRenderer } from '@tiptap/vue-3';

export interface EntityReferenceOptions {
    HTMLAttributes: Record<string, string>;
}

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        entityReference: {
            insertEntityReference: (attrs: { entityType: string; entityId: string | number; entitySlug: string; displayName: string }) => ReturnType;
        };
    }
}

export const EntityReference = Node.create<EntityReferenceOptions>({
    name: 'entityReference',

    group: 'inline',

    inline: true,

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
        return [{ tag: 'entity-reference' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['entity-reference', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes)];
    },

    addNodeView() {
        return VueNodeViewRenderer(EntityReferenceNodeView);
    },

    addCommands() {
        return {
            insertEntityReference:
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

export default EntityReference;
