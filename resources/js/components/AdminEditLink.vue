<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Pencil } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    href: string;
    permission: string;
    label?: string;
}>();

const page = usePage<SharedData>();
const permissions = computed(() => page.props.auth.permissions ?? []);

const canSee = computed(() => props.permission.split('|').some((p) => permissions.value.includes(p)));
</script>

<template>
    <TooltipProvider v-if="canSee" :delay-duration="200">
        <Tooltip>
            <TooltipTrigger as-child>
                <Button variant="outline" size="icon" as-child>
                    <Link :href="href">
                        <Pencil class="h-4 w-4" />
                        <span class="sr-only">{{ label ?? 'Edit' }}</span>
                    </Link>
                </Button>
            </TooltipTrigger>
            <TooltipContent>{{ label ?? 'Edit in admin' }}</TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
