<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface Props {
    rows?: number;
    cols?: number;
}

withDefaults(defineProps<Props>(), {
    rows: 5,
    cols: 3,
});
</script>

<template>
    <div class="rounded-md border">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead v-for="c in cols" :key="`skeleton-head-${c}`">
                        <Skeleton class="h-4 w-20" />
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="r in rows" :key="`skeleton-row-${r}`">
                    <TableCell v-for="c in cols" :key="`skeleton-cell-${r}-${c}`">
                        <Skeleton class="h-4 w-full" />
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
