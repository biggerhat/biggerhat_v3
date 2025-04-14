<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { CircleAlert } from "lucide-vue-next";


const props = defineProps({
    message: {
        type: String,
        required: false,
        default() {
            return '';
        }
    },
    messageTitle: {
        type: String,
        required: false,
        default() {
            return null;
        }
    },
    messageType: {
        type: String,
        required: false,
        default() {
            return null;
        }
    }
})
</script>

<template>
    <div class="container mx-auto mt-6">
        <Alert :variant="props.messageType ? props.messageType : 'default'">
            <CircleAlert class="h-4 w-4" />
            <AlertTitle v-if="props.messageTitle">{{ messageTitle }}</AlertTitle>
            <AlertDescription>
                {{ message }}
            </AlertDescription>
        </Alert>
    </div>
</template>
