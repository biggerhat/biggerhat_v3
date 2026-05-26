<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{ campaign_name: string }>();
</script>

<template>
    <Head title="Invitation Expired" />
    <div class="container mx-auto mt-6 max-w-xl px-4 pb-12">
        <Card>
            <CardHeader><CardTitle>Invitation Expired</CardTitle></CardHeader>
            <CardContent>
                <p class="text-sm text-muted-foreground">
                    The invitation to <strong>{{ campaign_name }}</strong> has expired. Ask the organizer to send a new one.
                </p>
            </CardContent>
            <CardFooter class="justify-end">
                <Link :href="route('campaigns.index')">
                    <Button>My Campaigns</Button>
                </Link>
            </CardFooter>
        </Card>
    </div>
</template>
