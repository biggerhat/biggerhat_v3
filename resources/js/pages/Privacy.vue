<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import { Head, Link } from '@inertiajs/vue3';

// The site owner should review every section marked with [BRACKETS] and
// replace with their entity name, contact email, and confirmed retention
// periods before shipping publicly. The copy below is a reasonable-default
// template, not legal advice.
const lastUpdated = 'April 17, 2026';
</script>

<template>
    <Head title="Privacy Policy" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Privacy Policy" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Last updated {{ lastUpdated }}
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto px-4 sm:px-6">
            <div class="mx-auto max-w-3xl space-y-8 py-6 text-sm leading-relaxed">
                <!-- Owner-visible template warning. Remove once filled in. -->
<!--                <div class="flex items-start gap-3 rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-amber-900 dark:text-amber-200">-->
<!--                    <AlertTriangle class="mt-0.5 size-5 shrink-0" aria-hidden="true" />-->
<!--                    <div class="space-y-1">-->
<!--                        <p class="font-semibold">Template — review before shipping publicly.</p>-->
<!--                        <p class="text-xs opacity-90">-->
<!--                            Replace every bracketed placeholder (e.g. <code>[YOUR NAME]</code>, <code>[CONTACT EMAIL]</code>) with your real info, confirm-->
<!--                            retention periods match your hosting, and delete this banner before launch. This template is a reasonable default, not legal-->
<!--                            advice.-->
<!--                        </p>-->
<!--                    </div>-->
<!--                </div>-->

                <section class="space-y-3">
                    <h2 class="text-lg font-semibold">Who we are</h2>
                    <p>
                        BiggerHat is a fan-built Malifaux reference, crew builder, and game tracker, operated by Mark C &amp; Brandan B. If you have
                        questions about this policy or the data we hold about you,
                        <Link :href="route('feedback.show')" class="text-primary underline underline-offset-2">send us a message</Link>
                        and we'll get back to you.
                    </p>
                </section>

                <section class="space-y-3">
                    <h2 class="text-lg font-semibold">What data we collect</h2>
                    <ul class="ml-5 list-disc space-y-2">
                        <li>
                            <strong>Account data</strong> — email address and display name, when you sign up. Password hashes are stored securely and
                            never readable by us.
                        </li>
                        <li>
                            <strong>Feature data</strong> — your collection, wishlists, saved crew builds, games, and tournament entries. We keep this
                            so the features work across sessions and devices.
                        </li>
                        <li>
                            <strong>Preference cookies</strong> — your appearance (light / dark), accent theme, and sidebar open/closed state. These
                            are strictly necessary for the site to remember how you've configured it, so they're set without a consent prompt.
                        </li>
                        <li>
                            <strong>Session &amp; security cookies</strong> — Laravel session + CSRF tokens. Strictly necessary; also set without a
                            prompt.
                        </li>
                        <li>
                            <strong>Google Analytics</strong> — <em>only if you click Accept on the cookie banner.</em> Tracks page views, approximate
                            location, and device type. Helps us understand which features are used. If you decline or never choose, no analytics
                            requests are made.
                        </li>
                        <li>
                            <strong>Server logs</strong> — our hosting provider keeps short-term access logs (IP, URL, timestamp) for security and
                            debugging. Retained for <strong>[30 DAYS]</strong> then rotated.
                        </li>
                    </ul>
                </section>

                <section class="space-y-3">
                    <h2 class="text-lg font-semibold">Why we collect it</h2>
                    <p>
                        Account and feature data is processed on the basis of <strong>contract</strong> — we can't provide the service you signed up
                        for without it. Analytics is processed on the basis of your <strong>consent</strong> via the banner; you can withdraw consent
                        at any time by clearing our cookies in your browser, which will re-show the banner.
                    </p>
                </section>

                <section class="space-y-3">
                    <h2 class="text-lg font-semibold">Third parties</h2>
                    <ul class="ml-5 list-disc space-y-2">
                        <li>
                            <strong>Google Analytics</strong> (only with your consent) — processes page view data.
                            <a href="https://policies.google.com/privacy" target="_blank" rel="noopener" class="text-primary underline underline-offset-2"
                                >Google's privacy policy</a
                            >.
                        </li>
                        <li>
                            <strong>Laravel Reverb (WebSockets)</strong> — used only when you actively play a game in the Game Tracker or run a
                            tournament. Broadcasts real-time events (scores, pairings) to other players in the same game/tournament. Hosted on our own
                            infrastructure.
                        </li>
                        <li>
                            <strong>Bunny Fonts CDN</strong> — serves our web font. Privacy-friendly (no tracking cookies). See
                            <a href="https://fonts.bunny.net/about" target="_blank" rel="noopener" class="text-primary underline underline-offset-2"
                                >Bunny Fonts about</a
                            >.
                        </li>
                        <li>
                            External links to <strong>Ko-fi</strong> (donations) and <strong>Discord</strong> (community) — clicking leaves this site;
                            we share no data with them from here.
                        </li>
                    </ul>
                    <p class="text-xs text-muted-foreground">
                        Portions of Malifaux materials referenced throughout the site are copyrighted by Wyrd Miniatures, LLC. BiggerHat is not
                        affiliated with or endorsed by Wyrd.
                    </p>
                </section>

                <section class="space-y-3">
                    <h2 class="text-lg font-semibold">Your rights (EU / UK / California)</h2>
                    <p>You have the right to:</p>
                    <ul class="ml-5 list-disc space-y-1">
                        <li>Access the personal data we hold about you</li>
                        <li>Request correction of any inaccurate data</li>
                        <li>Request deletion of your account and associated data</li>
                        <li>Request an export of your data in a portable format</li>
                        <li>Withdraw your analytics consent at any time</li>
                        <li>Lodge a complaint with your local data protection authority</li>
                    </ul>
                    <p>
                        To exercise any of these rights,
                        <Link :href="route('feedback.show')" class="text-primary underline underline-offset-2">send us a request</Link>
                        and choose the <em>Privacy</em> category. We'll respond within 30 days. You can also delete your account at any time from your
                        account settings.
                    </p>
                </section>

                <section class="space-y-3">
                    <h2 class="text-lg font-semibold">Data retention</h2>
                    <ul class="ml-5 list-disc space-y-2">
                        <li><strong>Account &amp; feature data</strong> — kept until you delete your account.</li>
                        <li><strong>Server logs</strong> — [30 days] then rotated.</li>
                        <li>
                            <strong>Analytics</strong> — per Google's default retention settings ([14 months] unless you've configured otherwise in
                            your GA property).
                        </li>
                        <li>
                            <strong>Cookies</strong> — consent cookie lasts 1 year; preference cookies last 1 year; session cookies expire with your
                            browser session.
                        </li>
                    </ul>
                </section>

                <section class="space-y-3">
                    <h2 class="text-lg font-semibold">Changes to this policy</h2>
                    <p>
                        If we materially change how we handle your data, we'll update this page and its "last updated" date. Significant changes (new
                        third-party processors, new data categories) will trigger a re-prompt of the consent banner so you can reconfirm.
                    </p>
                </section>

                <p class="pt-4 text-xs text-muted-foreground">Last updated {{ lastUpdated }}.</p>
            </div>
        </div>
    </div>
</template>
