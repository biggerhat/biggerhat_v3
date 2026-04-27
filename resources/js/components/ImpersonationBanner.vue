<script setup lang="ts">
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { UserCog } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage<SharedData>();
const impersonating = computed(() => page.props.auth?.impersonating ?? null);
</script>

<template>
    <div
        v-if="impersonating"
        class="sticky top-0 z-50 flex items-center justify-center gap-3 border-b border-amber-500/40 bg-amber-500/15 px-4 py-1.5 text-xs text-amber-900 dark:text-amber-200"
    >
        <UserCog class="size-3.5" />
        <span>
            Impersonating <strong>{{ impersonating.as.name }}</strong>
        </span>
        <a :href="impersonating.leave_url" class="font-semibold underline underline-offset-2 hover:opacity-80">Leave impersonation</a>
    </div>
</template>
