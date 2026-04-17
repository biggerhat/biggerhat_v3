<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useCookieConsent } from '@/composables/useCookieConsent';
import { Link } from '@inertiajs/vue3';
import { Cookie } from 'lucide-vue-next';

const { hasDecided, acceptConsent, declineConsent } = useCookieConsent();
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-full opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-full opacity-0"
    >
        <!-- Only renders client-side (hasDecided is null on SSR → v-if false). -->
        <div
            v-if="hasDecided === false"
            role="dialog"
            aria-label="Cookie consent"
            class="fixed inset-x-0 bottom-0 z-50 border-t bg-background/95 shadow-lg backdrop-blur-sm"
        >
            <div class="container mx-auto flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:gap-4">
                <div class="flex items-start gap-3 sm:flex-1">
                    <Cookie class="mt-0.5 size-5 shrink-0 text-primary" aria-hidden="true" />
                    <p class="text-sm text-muted-foreground">
                        We use cookies to keep you signed in and remember your preferences. Google Analytics helps us understand how the site is used —
                        turn it on?
                        <Link :href="route('privacy')" class="ml-1 font-medium text-foreground underline-offset-2 hover:underline">Privacy policy</Link>
                    </p>
                </div>
                <!-- Reject must be as easy as Accept per GDPR — same size, equal prominence. -->
                <div class="flex shrink-0 gap-2 sm:ml-auto">
                    <Button variant="outline" size="sm" class="flex-1 sm:flex-none" @click="declineConsent"> Decline </Button>
                    <Button size="sm" class="flex-1 sm:flex-none" @click="acceptConsent"> Accept </Button>
                </div>
            </div>
        </div>
    </Transition>
</template>
