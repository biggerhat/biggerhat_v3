<script setup lang="ts">
import { useToast } from '@/composables/useToast';
import { csrfToken } from '@/lib/utils';
import { router } from '@inertiajs/vue3';
import { Copy, Loader2 } from 'lucide-vue-next';
import { ref } from 'vue';

/** Public share pages: signed-in visitors can copy the card into their own cards and open it in the editor. */
const props = defineProps<{ url: string }>();
const toast = useToast();
const copying = ref(false);

const copy = async () => {
    if (copying.value) return;
    copying.value = true;
    try {
        const res = await fetch(props.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' } });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.redirect) {
            toast.error('Could not copy this card', { description: data.message ?? 'Try again in a moment.' });
            return;
        }
        router.visit(data.redirect);
    } catch {
        toast.error('Could not copy this card', { description: 'Check your connection and try again.' });
    } finally {
        copying.value = false;
    }
};
</script>

<template>
    <button
        type="button"
        :disabled="copying"
        class="mr-2 inline-flex h-9 items-center gap-1.5 rounded-md border bg-background px-3 text-sm font-medium transition-colors hover:bg-accent disabled:opacity-60 [@media(pointer:coarse)]:h-11"
        @click="copy"
    >
        <Loader2 v-if="copying" class="size-4 animate-spin" />
        <Copy v-else class="size-4" />
        Copy to my cards
    </button>
</template>
