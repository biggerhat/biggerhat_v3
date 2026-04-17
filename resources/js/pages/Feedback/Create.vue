<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Loader2, MessageSquareText, Send } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{
    categories: { value: string; label: string }[];
}>();

const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user);

// Authenticated users get their name/email prefilled but can still override.
const form = useForm({
    name: user.value?.name ?? '',
    email: user.value?.email ?? '',
    category: 'general',
    subject: '',
    message: '',
    // Honeypot — real users never see or fill this. Server rejects anything
    // non-empty. Matches a common anti-bot pattern cheap to implement.
    website: '',
});

const submit = () => {
    form.post(route('feedback.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('subject', 'message', 'website'),
    });
};

const charLimit = 5000;
</script>

<template>
    <Head title="Send feedback" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Send feedback" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Bug reports, feature requests, privacy questions — all land in the same inbox.
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto sm:px-4">
            <form class="mx-auto max-w-xl" @submit.prevent="submit">
                <Card>
                    <CardContent class="space-y-6 p-6">
                        <header class="flex items-center gap-3">
                            <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <MessageSquareText class="size-5" />
                            </div>
                            <div>
                                <h2 class="font-semibold">Tell us what's up</h2>
                                <p class="text-sm text-muted-foreground">We'll read every one. Reply only if we need more info.</p>
                            </div>
                        </header>

                        <!-- Honeypot: visually hidden, aria-hidden, tab-inaccessible, inert to real users. -->
                        <div aria-hidden="true" class="sr-only" tabindex="-1">
                            <Label for="fb-website">Website</Label>
                            <Input id="fb-website" v-model="form.website" type="text" tabindex="-1" autocomplete="off" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="fb-name">
                                    Name <span class="text-xs text-muted-foreground">(optional)</span>
                                </Label>
                                <Input id="fb-name" v-model="form.name" placeholder="What should we call you?" />
                                <InputError :message="form.errors.name" />
                            </div>
                            <div class="space-y-2">
                                <Label for="fb-email">
                                    Email <span class="text-xs text-muted-foreground">(optional, for reply)</span>
                                </Label>
                                <Input id="fb-email" v-model="form.email" type="email" placeholder="you@example.com" />
                                <InputError :message="form.errors.email" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label>Category</Label>
                            <Select v-model="form.category">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.category" />
                        </div>

                        <div class="space-y-2">
                            <Label for="fb-subject">
                                Subject <span class="text-xs text-muted-foreground">(optional)</span>
                            </Label>
                            <Input id="fb-subject" v-model="form.subject" placeholder="Short summary" />
                            <InputError :message="form.errors.subject" />
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-baseline justify-between">
                                <Label for="fb-message">Message</Label>
                                <span
                                    :class="[
                                        'text-[11px] tabular-nums',
                                        form.message.trim().length > 0 && form.message.trim().length < 10 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground',
                                    ]"
                                >
                                    {{ form.message.length }} / {{ charLimit }}
                                </span>
                            </div>
                            <Textarea
                                id="fb-message"
                                v-model="form.message"
                                rows="6"
                                :maxlength="charLimit"
                                placeholder="What happened? What did you expect? Any steps to reproduce?"
                            />
                            <p v-if="form.message.trim().length > 0 && form.message.trim().length < 10" class="text-[11px] text-amber-600 dark:text-amber-400">
                                A few more characters — minimum 10.
                            </p>
                            <InputError :message="form.errors.message" />
                        </div>

                        <p v-if="!user" class="text-xs text-muted-foreground">
                            You're not signed in — your submission is anonymous unless you include a name or email above.
                        </p>

                        <div class="flex items-center justify-between gap-3 border-t pt-4">
                            <p class="text-xs text-muted-foreground">Rate-limited to 5 per hour per IP to keep the inbox clean.</p>
                            <!-- Only disable while submitting. The server validates min:10 and will surface a
                                 clear error if the message is too short — letting the user click gives them
                                 an obvious signal instead of a silently-dead button. -->
                            <Button type="submit" :disabled="form.processing">
                                <Loader2 v-if="form.processing" class="mr-2 size-4 animate-spin" />
                                <Send v-else class="mr-2 size-4" />
                                Send feedback
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </div>
    </div>
</template>
