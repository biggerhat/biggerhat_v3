<script setup lang="ts">
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage<SharedData>();

/**
 * UI-only gate while TOS is pre-release — hide the switcher from anyone
 * without the `view_tos` permission. Remove the `v-if="canViewTos"` below
 * (or grant the permission to a public role) when TOS goes fully public.
 */
const canViewTos = computed(() => page.props.auth?.can_view_tos === true);
const current = computed(() => page.props.currentGameSystem.slug);

/**
 * Persist the choice client-side so a returning user lands back where they
 * left off when they hit a game-agnostic URL (auth, profile, settings).
 * The cookie is exempted from encryption in `bootstrap/app.php`, so JS can
 * write it directly — no POST + CSRF roundtrip needed (which is what was
 * blowing up with 419 Page Expired errors on idle sessions).
 */
function rememberSystem(system: 'malifaux' | 'tos') {
    const oneYearSeconds = 60 * 60 * 24 * 365;
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `preferred_game_system=${system}; max-age=${oneYearSeconds}; path=/; SameSite=Lax${secure}`;
}

function switchTo(target: 'malifaux' | 'tos') {
    if (current.value === target) return;
    rememberSystem(target);
    const url = target === 'tos' ? route('tos.index') : route('index');
    router.visit(url);
}
</script>

<template>
    <div
        v-if="canViewTos"
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
            @click="switchTo('malifaux')"
        >
            Malifaux
        </button>
        <button
            type="button"
            class="inline-flex h-7 items-center rounded px-2 transition-colors"
            :class="current === 'tos' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
            :aria-pressed="current === 'tos'"
            @click="switchTo('tos')"
        >
            The Other Side
        </button>
    </div>
</template>
