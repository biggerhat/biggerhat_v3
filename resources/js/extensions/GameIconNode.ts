import GameIconNodeView from '@/components/blog/GameIconNodeView.vue';
import { mergeAttributes, Node } from '@tiptap/core';
import { VueNodeViewRenderer } from '@tiptap/vue-3';

export interface GameIconNodeOptions {
    HTMLAttributes: Record<string, string>;
}

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        gameIconNode: {
            insertGameIcon: (attrs: { iconType: string }) => ReturnType;
        };
    }
}

export const GameIconNode = Node.create<GameIconNodeOptions>({
    name: 'gameIcon',

    group: 'inline',

    inline: true,

    atom: true,

    addAttributes() {
        return {
            iconType: { default: null },
        };
    },

    parseHTML() {
        return [{ tag: 'game-icon' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['game-icon', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes)];
    },

    addNodeView() {
        return VueNodeViewRenderer(GameIconNodeView);
    },

    addCommands() {
        return {
            insertGameIcon:
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

export default GameIconNode;
