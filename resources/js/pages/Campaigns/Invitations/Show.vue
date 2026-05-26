<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, Link, router } from '@inertiajs/vue3';

interface InvitationData {
    token: string;
    campaign: {
        id: number;
        name: string;
        length_weeks: number;
        status: string;
    };
    inviter: { id: number; name: string } | null;
    expires_at: string | null;
}

const props = defineProps<{ invitation: InvitationData }>();

const accept = () => router.post(route('campaigns.invitations.accept', props.invitation.token));
</script>

<template>
    <Head :title="`Join ${invitation.campaign.name}`" />
    <div class="container mx-auto mt-6 max-w-xl px-4 pb-12">
        <Card>
            <CardHeader>
                <CardTitle>You're invited to a Campaign</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3 text-sm">
                <div>
                    <p class="text-xs uppercase text-muted-foreground">Campaign</p>
                    <p class="text-lg font-semibold">{{ invitation.campaign.name }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ invitation.campaign.length_weeks }} week{{ invitation.campaign.length_weeks === 1 ? '' : 's' }} • Status:
                        {{ invitation.campaign.status }}
                    </p>
                </div>
                <div v-if="invitation.inviter">
                    <p class="text-xs uppercase text-muted-foreground">Invited by</p>
                    <p>{{ invitation.inviter.name }}</p>
                </div>
                <div v-if="invitation.expires_at" class="text-xs text-muted-foreground">
                    Expires {{ new Date(invitation.expires_at).toLocaleString() }}
                </div>
                <p class="mt-3 rounded-md bg-muted p-3 text-xs text-muted-foreground">
                    Accepting creates a crew stub for you in this campaign. You'll build your custom Leader + starting arsenal afterwards.
                </p>
            </CardContent>
            <CardFooter class="justify-end gap-2">
                <Link :href="route('campaigns.index')">
                    <Button variant="outline">Not now</Button>
                </Link>
                <Button @click="accept">Accept Invitation</Button>
            </CardFooter>
        </Card>
    </div>
</template>
