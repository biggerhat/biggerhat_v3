<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { CheckCircle2, CircleAlert, Info, TriangleAlert } from 'lucide-vue-next';
import { computed, type Component } from 'vue';

const props = defineProps({
    message: {
        type: String,
        required: false,
        default() {
            return '';
        },
    },
    messageTitle: {
        type: String,
        required: false,
        default() {
            return null;
        },
    },
    messageType: {
        type: String,
        required: false,
        default() {
            return null;
        },
    },
});

// Map the server-side MessageTypeEnum (success/info/warn/error/secondary/contrast)
// to an appropriate icon. Default generic alert for unknown/null types.
const iconForType = computed<Component>(() => {
    switch (props.messageType) {
        case 'success':
            return CheckCircle2;
        case 'warn':
            return TriangleAlert;
        case 'info':
            return Info;
        case 'error':
            return CircleAlert;
        default:
            return CircleAlert;
    }
});
</script>

<template>
    <div class="container mx-auto mt-6">
        <Alert :variant="props.messageType ? props.messageType : 'default'">
            <component :is="iconForType" class="h-4 w-4" />
            <AlertTitle v-if="props.messageTitle">{{ messageTitle }}</AlertTitle>
            <AlertDescription>
                {{ message }}
            </AlertDescription>
        </Alert>
    </div>
</template>
