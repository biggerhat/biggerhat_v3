import { useToast } from '@/composables/useToast';
import { saveBlob } from '@/lib/native';

const MAX_BYTES = 300_000;

/**
 * Download a card as a small JSON file and load one back into the editor. The file wraps the editor's own draft
 * snapshot in an envelope naming the card kind, so a character file can't be loaded into an upgrade editor. Importing
 * only fills the form — nothing is saved until the player saves, and the server validates everything as usual.
 */
export function useCardJsonTransfer<T extends object>(options: {
    kind: 'custom-character' | 'custom-upgrade';
    snapshot: () => T;
    apply: (data: T) => void;
    /** Drops what shouldn't travel (private notes, links to the owner's other cards). */
    scrub?: (data: T) => T;
    filename: () => string;
}) {
    const toast = useToast();

    const exportJson = () => {
        const data = options.scrub ? options.scrub(options.snapshot()) : options.snapshot();
        const blob = new Blob([JSON.stringify({ app: 'biggerhat', kind: options.kind, version: 1, data }, null, 2)], { type: 'application/json' });
        saveBlob(blob, `${options.filename().replace(/[^\w-]+/g, '_') || 'card'}.json`);
    };

    const importFile = async (event: Event) => {
        const input = event.target as HTMLInputElement;
        const file = input.files?.[0];
        input.value = '';
        if (!file) return;
        try {
            if (file.size > MAX_BYTES) throw new Error('That file is too large to be a card.');
            const parsed = JSON.parse(await file.text());
            if (parsed?.app !== 'biggerhat' || parsed?.kind !== options.kind || typeof parsed.data !== 'object' || parsed.data === null) {
                throw new Error('That is not a card file for this editor.');
            }
            options.apply(parsed.data as T);
            toast.success('Card loaded — review it, then save.');
        } catch (e) {
            toast.error('Could not import that file', { description: e instanceof Error ? e.message : 'Check that it is a BiggerHat card file.' });
        }
    };

    return { exportJson, importFile };
}
