import { Button } from '@/components/ui/button';
import type { Column } from '@tanstack/vue-table';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-vue-next';
import { h } from 'vue';

/** "explorers_society" → "Explorers Society". Falls back to a dash for empty values. */
export function humanize(value: string | null | undefined): string {
    if (!value) return '-';
    return value
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase())
        .trim();
}

/** Column header that toggles ascending/descending sort and shows the current direction. */
export function sortableHeader(label: string) {
    return ({ column }: { column: Column<any, unknown> }) => {
        const sorted = column.getIsSorted();
        const Icon = sorted === 'asc' ? ArrowUp : sorted === 'desc' ? ArrowDown : ArrowUpDown;
        return h(
            Button,
            {
                variant: 'ghost',
                size: 'sm',
                class: '-ml-3 h-8 px-3 font-medium text-muted-foreground hover:text-foreground',
                'aria-label': sorted ? `Sort ${label} (currently ${sorted === 'asc' ? 'ascending' : 'descending'})` : `Sort by ${label}`,
                onClick: () => column.toggleSorting(sorted === 'asc'),
            },
            () => [label, h(Icon, { class: ['ml-1.5 size-3.5', sorted ? 'text-foreground' : 'opacity-50'] })],
        );
    };
}
