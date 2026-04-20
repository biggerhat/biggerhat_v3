<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Minus } from 'lucide-vue-next';

interface TokenInfo {
    name: string;
    description: string | null;
}

interface TokenMember {
    display_name: string;
}

defineProps<{
    open: boolean;
    token: TokenInfo | null;
    member: TokenMember | null;
    canRemove: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'remove'): void;
}>();
</script>

<template>
    <Drawer :open="open" @update:open="emit('update:open', $event)">
        <DrawerContent>
            <div v-if="token" class="mx-auto w-full max-w-sm">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ token.name }}</DrawerTitle>
                    <div class="mt-1 text-center text-xs text-muted-foreground">Token</div>
                </DrawerHeader>
                <div class="px-4 pb-4">
                    <p v-if="token.description" class="text-sm leading-relaxed text-muted-foreground">{{ token.description }}</p>
                    <p v-else class="text-center text-sm text-muted-foreground">No description available.</p>
                </div>
                <DrawerFooter class="gap-2 pt-2">
                    <Button v-if="member && canRemove" variant="destructive" size="sm" @click="emit('remove')">
                        <Minus class="mr-1.5 size-3.5" />
                        Remove from {{ member.display_name }}
                    </Button>
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>
</template>
