/**
 * Thin re-export of vue-sonner's toast API with app-specific defaults and
 * a typed helper for mapping our server-side MessageTypeEnum
 * (success / warn / info / error / default) to the correct toast variant.
 *
 * Usage:
 *   import { useToast } from '@/composables/useToast';
 *   const toast = useToast();
 *   toast.success('Saved');
 *   toast.error('Something broke', { description: 'Try again' });
 *   toast.fromFlash('warn', 'Heads up');   // honors the MessageTypeEnum values
 */
import { toast as sonner } from 'vue-sonner';

type MessageType = 'success' | 'info' | 'warn' | 'error' | 'default' | null | undefined;

function fromFlash(type: MessageType, message: string, options?: Parameters<typeof sonner>[1]) {
    switch (type) {
        case 'success':
            return sonner.success(message, options);
        case 'warn':
            return sonner.warning(message, options);
        case 'info':
            return sonner.info(message, options);
        case 'error':
            return sonner.error(message, options);
        default:
            return sonner(message, options);
    }
}

export function useToast() {
    return {
        success: sonner.success.bind(sonner),
        error: sonner.error.bind(sonner),
        warning: sonner.warning.bind(sonner),
        info: sonner.info.bind(sonner),
        message: sonner,
        promise: sonner.promise.bind(sonner),
        dismiss: sonner.dismiss.bind(sonner),
        fromFlash,
    };
}
