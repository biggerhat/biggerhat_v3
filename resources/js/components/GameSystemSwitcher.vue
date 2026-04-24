<script setup lang="ts">
import { csrfToken } from '@/lib/utils';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage<SharedData>();

/**
 * UI-only gate while TOS is pre-release — hide the switcher from anyone who
 * isn't a super_admin. The server endpoint is intentionally left open; remove
 * the `v-if="isSuperAdmin"` below when TOS goes public and the switcher
 * becomes visible for everyone without any other code changes.
 */
const isSuperAdmin = computed(() => page.props.auth?.is_super_admin === true);
const current = computed(() => page.props.currentGameSystem.slug);

function submit(target: 'malifaux' | 'tos') {
    if (current.value === target) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = route('system.switch');
    form.style.display = 'none';

    const csrf = document.createElement('input');
    csrf.name = '_token';
    csrf.value = csrfToken();
    form.appendChild(csrf);

    const sys = document.createElement('input');
    sys.name = 'system';
    sys.value = target;
    form.appendChild(sys);

    document.body.appendChild(form);
    form.submit();
}
</script>

<template>
    <div
        v-if="isSuperAdmin"
        class="inline-flex h-8 items-center rounded-md border border-input bg-background/60 p-0.5 text-[11px] font-medium"
        role="group"
        aria-label="Game system"
    >
        <button
            type="button"
            class="inline-flex h-7 items-center rounded px-2 transition-colors"
            :class="
                current === 'malifaux'
                    ? 'bg-primary text-primary-foreground shadow-sm'
                    : 'text-muted-foreground hover:text-foreground'
            "
            :aria-pressed="current === 'malifaux'"
            @click="submit('malifaux')"
        >
            Malifaux
        </button>
        <button
            type="button"
            class="inline-flex h-7 items-center rounded px-2 transition-colors"
            :class="current === 'tos' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
            :aria-pressed="current === 'tos'"
            @click="submit('tos')"
        >
            The Other Side
        </button>
    </div>
</template>
