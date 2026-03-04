<script setup lang="ts">
import GameIcon from '@/components/GameIcon.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { Editor } from '@tiptap/vue-3';
import {
    AtSign,
    Bold,
    Code,
    Heading1,
    Heading2,
    Heading3,
    Image,
    Italic,
    Link,
    List,
    ListOrdered,
    Minus,
    Quote,
    Redo2,
    Smile,
    SquareCode,
    Strikethrough,
    Underline,
    Undo2,
} from 'lucide-vue-next';

const props = defineProps<{
    editor: Editor;
}>();

const emit = defineEmits<{
    (e: 'openEntitySearch'): void;
    (e: 'openEntityEmbedSearch'): void;
}>();

const gameIconGroups = [
    {
        label: 'Suits',
        icons: [
            { type: 'crow', label: 'Crow' },
            { type: 'mask', label: 'Mask' },
            { type: 'ram', label: 'Ram' },
            { type: 'tome', label: 'Tome' },
            { type: 'soulstone', label: 'Soulstone' },
        ],
    },
    {
        label: 'Range Types',
        icons: [
            { type: 'melee', label: 'Melee' },
            { type: 'missile', label: 'Missile' },
            { type: 'magic', label: 'Magic' },
            { type: 'pulse', label: 'Pulse' },
        ],
    },
    {
        label: 'Modifiers',
        icons: [
            { type: 'positive', label: 'Positive' },
            { type: 'negative', label: 'Negative' },
        ],
    },
    {
        label: 'Defense',
        icons: [
            { type: 'physical_defense', label: 'Physical' },
            { type: 'magical_defense', label: 'Magical' },
            { type: 'unusual_defense', label: 'Unusual' },
        ],
    },
    {
        label: 'Other',
        icons: [{ type: 'signature_action', label: 'Signature' }],
    },
];

const insertGameIcon = (iconType: string) => {
    props.editor.chain().focus().insertGameIcon({ iconType }).run();
};

const addImage = () => {
    const url = window.prompt('Image URL');
    if (url) {
        props.editor.chain().focus().setImage({ src: url }).run();
    }
};

const addLink = () => {
    const previousUrl = props.editor.getAttributes('link').href;
    const url = window.prompt('Link URL', previousUrl);
    if (url === null) return;
    if (url === '') {
        props.editor.chain().focus().unsetLink().run();
        return;
    }
    props.editor.chain().focus().setLink({ href: url }).run();
};
</script>

<template>
    <TooltipProvider :delay-duration="300">
        <div class="flex flex-wrap items-center gap-0.5 border-b p-1.5">
            <!-- Undo / Redo -->
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button type="button" variant="ghost" size="sm" :disabled="!editor.can().undo()" @click="editor.chain().focus().undo().run()">
                        <Undo2 class="h-4 w-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Undo</TooltipContent>
            </Tooltip>
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button type="button" variant="ghost" size="sm" :disabled="!editor.can().redo()" @click="editor.chain().focus().redo().run()">
                        <Redo2 class="h-4 w-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Redo</TooltipContent>
            </Tooltip>

            <Separator orientation="vertical" class="mx-1 h-6" />

            <!-- Text formatting -->
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        variant="ghost"
                        size="sm"
                        :class="{ 'bg-muted': editor.isActive('bold') }"
                        @click="editor.chain().focus().toggleBold().run()"
                    >
                        <Bold class="h-4 w-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Bold</TooltipContent>
            </Tooltip>
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        variant="ghost"
                        size="sm"
                        :class="{ 'bg-muted': editor.isActive('italic') }"
                        @click="editor.chain().focus().toggleItalic().run()"
                    >
                        <Italic class="h-4 w-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Italic</TooltipContent>
            </Tooltip>
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        variant="ghost"
                        size="sm"
                        :class="{ 'bg-muted': editor.isActive('underline') }"
                        @click="editor.chain().focus().toggleUnderline().run()"
                    >
                        <Underline class="h-4 w-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Underline</TooltipContent>
            </Tooltip>
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        variant="ghost"
                        size="sm"
                        :class="{ 'bg-muted': editor.isActive('strike') }"
                        @click="editor.chain().focus().toggleStrike().run()"
                    >
                        <Strikethrough class="h-4 w-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Strikethrough</TooltipContent>
            </Tooltip>

            <Separator orientation="vertical" class="mx-1 h-6" />

            <!-- Headings -->
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        variant="ghost"
                        size="sm"
                        :class="{ 'bg-muted': editor.isActive('heading', { level: 1 }) }"
                        @click="editor.chain().focus().toggleHeading({ level: 1 }).run()"
                    >
                        <Heading1 class="h-4 w-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Heading 1</TooltipContent>
            </Tooltip>
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        variant="ghost"
                        size="sm"
                        :class="{ 'bg-muted': editor.isActive('heading', { level: 2 }) }"
                        @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
                    >
                        <Heading2 class="h-4 w-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Heading 2</TooltipContent>
            </Tooltip>
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        variant="ghost"
                        size="sm"
                        :class="{ 'bg-muted': editor.isActive('heading', { level: 3 }) }"
                        @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
                    >
                        <Heading3 class="h-4 w-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Heading 3</TooltipContent>
            </Tooltip>

            <Separator orientation="vertical" class="mx-1 h-6" />

            <!-- Lists & blocks -->
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        variant="ghost"
                        size="sm"
                        :class="{ 'bg-muted': editor.isActive('bulletList') }"
                        @click="editor.chain().focus().toggleBulletList().run()"
                    >
                        <List class="h-4 w-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Bullet List</TooltipContent>
            </Tooltip>
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        variant="ghost"
                        size="sm"
                        :class="{ 'bg-muted': editor.isActive('orderedList') }"
                        @click="editor.chain().focus().toggleOrderedList().run()"
                    >
                        <ListOrdered class="h-4 w-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Ordered List</TooltipContent>
            </Tooltip>
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        variant="ghost"
                        size="sm"
                        :class="{ 'bg-muted': editor.isActive('blockquote') }"
                        @click="editor.chain().focus().toggleBlockquote().run()"
                    >
                        <Quote class="h-4 w-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Blockquote</TooltipContent>
            </Tooltip>
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        variant="ghost"
                        size="sm"
                        :class="{ 'bg-muted': editor.isActive('codeBlock') }"
                        @click="editor.chain().focus().toggleCodeBlock().run()"
                    >
                        <Code class="h-4 w-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Code Block</TooltipContent>
            </Tooltip>
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button type="button" variant="ghost" size="sm" @click="editor.chain().focus().setHorizontalRule().run()">
                        <Minus class="h-4 w-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Horizontal Rule</TooltipContent>
            </Tooltip>

            <Separator orientation="vertical" class="mx-1 h-6" />

            <!-- Link & Image -->
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button type="button" variant="ghost" size="sm" :class="{ 'bg-muted': editor.isActive('link') }" @click="addLink">
                        <Link class="h-4 w-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Link</TooltipContent>
            </Tooltip>
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button type="button" variant="ghost" size="sm" @click="addImage">
                        <Image class="h-4 w-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Image</TooltipContent>
            </Tooltip>

            <Separator orientation="vertical" class="mx-1 h-6" />

            <!-- Entity Reference -->
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button type="button" variant="ghost" size="sm" class="gap-1" @click="emit('openEntitySearch')">
                        <AtSign class="h-4 w-4" />
                        <span class="hidden text-xs sm:inline">Link Entity</span>
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Insert entity reference (character, keyword, faction, etc.)</TooltipContent>
            </Tooltip>

            <!-- Entity Embed -->
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button type="button" variant="ghost" size="sm" class="gap-1" @click="emit('openEntityEmbedSearch')">
                        <SquareCode class="h-4 w-4" />
                        <span class="hidden text-xs sm:inline">Embed Entity</span>
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Embed entity as block preview (card images, info boxes)</TooltipContent>
            </Tooltip>

            <!-- Game Icons -->
            <DropdownMenu>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <DropdownMenuTrigger as-child>
                            <Button type="button" variant="ghost" size="sm" class="gap-1">
                                <Smile class="h-4 w-4" />
                                <span class="hidden text-xs sm:inline">Game Icons</span>
                            </Button>
                        </DropdownMenuTrigger>
                    </TooltipTrigger>
                    <TooltipContent>Insert game icon (suits, range, modifiers)</TooltipContent>
                </Tooltip>
                <DropdownMenuContent class="w-56" align="start">
                    <template v-for="(group, gIdx) in gameIconGroups" :key="group.label">
                        <DropdownMenuSeparator v-if="gIdx > 0" />
                        <DropdownMenuLabel>{{ group.label }}</DropdownMenuLabel>
                        <div class="grid grid-cols-3 gap-0.5 px-1 pb-1">
                            <DropdownMenuItem
                                v-for="icon in group.icons"
                                :key="icon.type"
                                class="flex cursor-pointer items-center gap-2 px-2 py-1.5"
                                @click="insertGameIcon(icon.type)"
                            >
                                <GameIcon :type="icon.type" class-name="h-5 w-5" />
                                <span class="text-xs">{{ icon.label }}</span>
                            </DropdownMenuItem>
                        </div>
                    </template>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </TooltipProvider>
</template>
