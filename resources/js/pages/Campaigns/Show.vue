<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useConfirm } from '@/composables/useConfirm';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const confirmDialog = useConfirm();
const page = usePage();
const authUserId = computed(() => (page.props.auth as any)?.user?.id ?? null);

interface UserMini {
    id: number;
    name: string;
    email?: string | null;
}

interface CampaignPlayerRow {
    id: number;
    user_id: number;
    role: string;
    user: UserMini | null;
}

interface CampaignCrewRow {
    id: number;
    name: string;
    share_code: string;
    faction: string | null;
    scrip: number;
    user: UserMini | null;
}

interface InvitationRow {
    id: number;
    token: string;
    email: string | null;
    user: UserMini | null;
    expires_at: string | null;
}

interface CampaignData {
    id: number;
    name: string;
    length_weeks: number;
    current_week: number;
    status: string;
    is_solo: boolean;
    organizer: UserMini | null;
    players: CampaignPlayerRow[];
    crews: CampaignCrewRow[];
    invitations: InvitationRow[];
}

const props = defineProps<{ campaign: CampaignData; is_organizer: boolean; all_arsenals_complete: boolean }>();

const statusVariant = computed((): 'default' | 'outline' | 'destructive' | 'secondary' => {
    switch (props.campaign.status) {
        case 'active':
            return 'default';
        case 'planning':
            return 'outline';
        case 'ended':
            return 'secondary';
        default:
            return 'outline';
    }
});

const inviteForm = ref({ email: '', user_id: null as number | null, expires_in_days: 14 });

const sendInvite = (campaignId: number) => {
    router.post(
        route('campaigns.invitations.store', campaignId),
        {
            user_id: inviteForm.value.user_id || null,
            email: inviteForm.value.email || null,
            expires_in_days: inviteForm.value.expires_in_days,
        },
        {
            onSuccess: () => {
                inviteForm.value.email = '';
                inviteForm.value.user_id = null;
            },
        },
    );
};

const revokeInvite = async (campaignId: number, inviteId: number) => {
    if (!(await confirmDialog({ title: 'Revoke Invitation', message: 'Revoke this invitation?', destructive: true }))) return;
    router.post(route('campaigns.invitations.revoke', [campaignId, inviteId]));
};

const startCampaign = (id: number) => router.post(route('campaigns.start', id));
const endCampaign = async (id: number) => {
    if (!(await confirmDialog({ title: 'End Campaign', message: 'End the campaign? This freezes all arsenals.', destructive: true }))) return;
    router.post(route('campaigns.end', id));
};
const advanceWeek = async (id: number) => {
    if (!(await confirmDialog({ title: 'Advance Week', message: 'Advance to the next week? This will roll the weekly event if enabled.' }))) return;
    router.post(route('campaigns.weeks.advance', id));
};
const deleteCampaign = async (id: number) => {
    if (!(await confirmDialog({ title: 'Delete Campaign', message: 'Delete this campaign permanently?', destructive: true }))) return;
    router.post(route('campaigns.destroy', id));
};
</script>

<template>
    <Head :title="`${campaign.name} — Campaign`" />

    <!-- Campaign hub hero. Progress bar reflects week-to-week march. -->
    <div class="relative overflow-hidden border-b bg-gradient-to-r from-primary/15 via-primary/5 to-transparent">
        <div class="container mx-auto max-w-5xl px-4 py-5">
            <Link
                :href="route('campaigns.index')"
                class="inline-flex items-center gap-1 text-xs uppercase tracking-wider text-muted-foreground hover:text-foreground"
            >
                ← All Campaigns
            </Link>
            <div class="mt-1 flex flex-wrap items-center gap-3">
                <h1 class="text-3xl font-black">{{ campaign.name }}</h1>
                <Badge :variant="statusVariant" class="text-[10px] uppercase">{{ campaign.status }}</Badge>
                <Badge v-if="campaign.is_solo" variant="outline" class="text-[10px] uppercase">Solo</Badge>
            </div>
            <p class="mt-1 text-sm text-muted-foreground">
                Week {{ campaign.current_week }} of {{ campaign.length_weeks }} • Organizer:
                <span class="font-medium text-foreground">{{ campaign.organizer?.name ?? '—' }}</span>
            </p>
            <!-- Inline progress strip when active. -->
            <div v-if="campaign.status !== 'planning'" class="mt-3 h-1.5 overflow-hidden rounded-full bg-muted">
                <div
                    class="h-full bg-primary transition-all"
                    :style="{ width: Math.min(100, Math.round((campaign.current_week / campaign.length_weeks) * 100)) + '%' }"
                />
            </div>
        </div>
    </div>

    <div class="container mx-auto max-w-5xl px-4 pb-12">
        <div class="mb-6 mt-4 flex flex-wrap items-center justify-end gap-2">
            <Link v-if="is_organizer" :href="route('campaigns.settings', campaign.id)">
                <Button variant="outline">Settings</Button>
            </Link>
            <Button
                v-if="is_organizer && campaign.status === 'planning'"
                @click="startCampaign(campaign.id)"
                :disabled="(!campaign.is_solo && campaign.players.length < 2) || !all_arsenals_complete"
                :title="!all_arsenals_complete ? 'All players must complete Starting Arsenal first' : undefined"
            >
                Start Campaign
            </Button>
            <Link v-if="campaign.status === 'active' && campaign.is_solo" :href="route('campaigns.games.log', campaign.id)">
                <Button>Log Game</Button>
            </Link>
            <Link v-else-if="campaign.status === 'active'" :href="route('campaigns.games.create', campaign.id)">
                <Button>New Game</Button>
            </Link>
            <Button
                v-if="is_organizer && campaign.status === 'active' && campaign.current_week < campaign.length_weeks"
                variant="outline"
                @click="advanceWeek(campaign.id)"
            >
                Advance Week
            </Button>
            <Button v-if="is_organizer && campaign.status === 'active'" variant="destructive" @click="endCampaign(campaign.id)">
                End Campaign
            </Button>
        </div>

        <!-- Solo skips the Players panel and spans Crew full-width since the
             user is the only player. Multiplayer keeps the side-by-side layout. -->
        <div :class="campaign.is_solo ? '' : 'grid gap-4 md:grid-cols-2'">
            <Card v-if="!campaign.is_solo">
                <CardHeader
                    ><CardTitle>Players ({{ campaign.players.length }})</CardTitle></CardHeader
                >
                <CardContent>
                    <ul class="space-y-2">
                        <li v-for="p in campaign.players" :key="p.id" class="flex items-center justify-between text-sm">
                            <span class="font-medium">{{ p.user?.name ?? '—' }}</span>
                            <Badge variant="outline" class="text-[10px] uppercase">{{ p.role }}</Badge>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>
                        <span v-if="campaign.is_solo">Your Crew</span>
                        <span v-else>Crews ({{ campaign.crews.length }})</span>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <ul v-if="campaign.crews.length" class="space-y-2">
                        <li v-for="c in campaign.crews" :key="c.id" class="flex items-center justify-between gap-2 text-sm">
                            <div>
                                <p class="font-medium">{{ c.name }}</p>
                                <p class="text-xs text-muted-foreground">
                                    <template v-if="!campaign.is_solo">{{ c.user?.name }} • </template>{{ c.faction ?? 'Faction TBD' }} •
                                    {{ c.scrip }} scrip
                                </p>
                            </div>
                            <div v-if="c.share_code" class="flex flex-col gap-1">
                                <Link :href="route('campaigns.crews.arsenal.show', [campaign.id, c.share_code])">
                                    <Button size="sm" class="w-full">Arsenal Sheet</Button>
                                </Link>
                                <template v-if="campaign.is_solo || c.user?.id === authUserId">
                                    <Link
                                        v-if="campaign.status === 'planning'"
                                        :href="route('campaigns.crews.leader.edit', [campaign.id, c.share_code])"
                                    >
                                        <Button size="sm" variant="outline" class="w-full">Build Leader</Button>
                                    </Link>
                                    <Link
                                        v-if="campaign.status === 'planning'"
                                        :href="route('campaigns.crews.starting-arsenal.edit', [campaign.id, c.share_code])"
                                    >
                                        <Button size="sm" variant="outline" class="w-full">Starting Arsenal</Button>
                                    </Link>
                                    <Link
                                        v-if="campaign.status === 'active'"
                                        :href="route('campaigns.crews.weekly-hire.edit', [campaign.id, c.share_code])"
                                    >
                                        <Button size="sm" variant="outline" class="w-full">Weekly Hire</Button>
                                    </Link>
                                </template>
                            </div>
                        </li>
                    </ul>
                    <p v-else-if="campaign.is_solo" class="text-sm text-muted-foreground">
                        Your crew is being set up — refresh if you don't see the Arsenal buttons yet.
                    </p>
                    <p v-else class="text-sm text-muted-foreground">No crews yet — players build theirs after accepting invitations.</p>
                </CardContent>
            </Card>
        </div>

        <Card v-if="is_organizer && !campaign.is_solo && campaign.status !== 'ended'" class="mt-6">
            <CardHeader><CardTitle>Invite a Player</CardTitle></CardHeader>
            <CardContent>
                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <Label>Email address</Label>
                        <Input v-model="inviteForm.email" placeholder="player@example.com" />
                    </div>
                    <div>
                        <Label>Expires in (days)</Label>
                        <Input type="number" v-model.number="inviteForm.expires_in_days" />
                    </div>
                    <div class="flex items-end">
                        <Button class="w-full" @click="sendInvite(campaign.id)" :disabled="!inviteForm.email"> Send Invitation </Button>
                    </div>
                </div>

                <div v-if="campaign.invitations.length" class="mt-4">
                    <p class="mb-2 text-xs font-medium uppercase text-muted-foreground">Pending Invitations</p>
                    <ul class="space-y-2">
                        <li v-for="inv in campaign.invitations" :key="inv.id" class="flex items-center justify-between rounded-md border p-2 text-sm">
                            <span>{{ inv.user?.name ?? inv.user?.email ?? inv.email }}</span>
                            <Button variant="ghost" size="sm" @click="revokeInvite(campaign.id, inv.id)">Revoke</Button>
                        </li>
                    </ul>
                </div>
            </CardContent>
        </Card>

        <div v-if="is_organizer && (campaign.status === 'planning' || campaign.status === 'ended')" class="mt-8 flex justify-end">
            <Button variant="destructive" size="sm" @click="deleteCampaign(campaign.id)">Delete Campaign</Button>
        </div>
    </div>
</template>
