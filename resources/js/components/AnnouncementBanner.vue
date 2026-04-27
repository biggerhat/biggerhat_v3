<script setup lang="ts">
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { Info, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const page = usePage<SharedData>();
const announcements = computed(() => page.props.announcements ?? []);

// Local-only dismiss memory keyed by announcement id. Survives within the
// session via sessionStorage so client-side navigation doesn't re-show a
// banner the user just dismissed.
const dismissed = ref<Set<number>>(loadDismissed());

function loadDismissed(): Set<number> {
    if (typeof window === 'undefined') return new Set();
    try {
        const raw = window.sessionStorage.getItem('dismissed_announcements');
        if (!raw) return new Set();
        const ids = JSON.parse(raw);
        return new Set(Array.isArray(ids) ? ids.map(Number) : []);
    } catch {
        return new Set();
    }
}

const dismiss = (id: number) => {
    dismissed.value.add(id);
    if (typeof window !== 'undefined') {
        window.sessionStorage.setItem('dismissed_announcements', JSON.stringify([...dismissed.value]));
    }
};

const visible = computed(() => announcements.value.filter((a) => !dismissed.value.has(a.id)));

const levelClass = (level: string) => {
    switch (level) {
        case 'warning':
            return 'border-amber-500/40 bg-amber-500/15 text-amber-900 dark:text-amber-200';
        case 'success':
            return 'border-green-500/40 bg-green-500/15 text-green-900 dark:text-green-200';
        default:
            return 'border-blue-500/40 bg-blue-500/15 text-blue-900 dark:text-blue-200';
    }
};
</script>

<template>
    <div v-if="visible.length" class="sticky top-0 z-40">
        <div
            v-for="a in visible"
            :key="a.id"
            class="flex items-center justify-center gap-3 border-b px-4 py-1.5 text-xs"
            :class="levelClass(a.level)"
        >
            <Info class="size-3.5 shrink-0" />
            <span class="text-center">{{ a.message }}</span>
            <a
                v-if="a.link_url"
                :href="a.link_url"
                class="font-semibold underline underline-offset-2 hover:opacity-80"
            >
                {{ a.link_label ?? 'Learn more' }}
            </a>
            <button
                v-if="a.is_dismissable"
                type="button"
                class="ml-2 rounded p-0.5 hover:opacity-70"
                aria-label="Dismiss"
                @click="dismiss(a.id)"
            >
                <X class="size-3.5" />
            </button>
        </div>
    </div>
</template>
