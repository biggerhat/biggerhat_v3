<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useConfirm } from '@/composables/useConfirm';
import { type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { UserCog } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';

interface UserRow {
    id: number;
    name: string;
    slug: string;
    email: string;
    roles: { id: number; name: string }[];
}

const props = defineProps<{
    users: {
        data: UserRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        first_page_url: string;
        last_page_url: string;
        next_page_url: string | null;
        prev_page_url: string | null;
        path: string;
        from: number | null;
        to: number | null;
    };
    filters: { q: string; role: string };
    roles: string[];
}>();

const page = usePage<SharedData>();
const isSuperAdmin = computed(() => !!page.props.auth.is_super_admin);
const confirm = useConfirm();

const ALL_ROLES = '__all__';
const search = ref(props.filters.q);
const role = ref(props.filters.role || ALL_ROLES);

let debounce: ReturnType<typeof setTimeout> | undefined;
onBeforeUnmount(() => clearTimeout(debounce));

const applyFilters = () => {
    router.get(
        route('admin.users.index'),
        { q: search.value || undefined, role: role.value === ALL_ROLES ? undefined : role.value },
        { only: ['users', 'filters'], replace: true, preserveState: true, preserveScroll: true },
    );
};

const onSearchInput = () => {
    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 300);
};

const startImpersonation = async (user: UserRow) => {
    if (
        !(await confirm({
            title: `Impersonate ${user.name}?`,
            message: `You'll be logged in as ${user.name} until you click "Leave Impersonation" in the banner at the top of the page.`,
            confirmLabel: 'Impersonate',
        }))
    )
        return;
    window.location.href = `/impersonate/take/${user.id}`;
};

const isSuperAdminUser = (user: UserRow) => user.roles.some((r) => r.name === 'super_admin');
</script>

<template>
    <Head title="Users - Admin" />

    <PageBanner title="Users" class="mb-2" />
    <div class="container mx-auto mt-6 h-full">
        <div class="flex flex-wrap items-center justify-between gap-2 py-4">
            <div class="flex flex-wrap items-center gap-2">
                <Input v-model="search" class="w-64" placeholder="Search name or email" @input="onSearchInput" />
                <Select v-model="role" @update:model-value="applyFilters">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="All roles" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL_ROLES">All roles</SelectItem>
                        <SelectItem v-for="r in roles" :key="r" :value="r">{{ r }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div>Total {{ users.total }}</div>
        </div>
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Email</TableHead>
                        <TableHead>Roles</TableHead>
                        <TableHead>Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="users.data.length">
                        <TableRow v-for="user in users.data" :key="user.id">
                            <TableCell>{{ user.name }}</TableCell>
                            <TableCell>{{ user.email }}</TableCell>
                            <TableCell>
                                <div class="flex gap-1">
                                    <Badge v-for="r in user.roles" :key="r.id" variant="secondary">{{ r.name }}</Badge>
                                </div>
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-2">
                                    <!-- Impersonate — super_admin only, can't take other super_admins. -->
                                    <Button
                                        v-if="isSuperAdmin && !isSuperAdminUser(user)"
                                        variant="outline"
                                        size="sm"
                                        :title="`Impersonate ${user.name}`"
                                        @click="startImpersonation(user)"
                                    >
                                        <UserCog class="size-4" />
                                    </Button>
                                    <AdminActions
                                        :name="user.name"
                                        :edit-route="route('admin.users.edit', user.slug)"
                                        :delete-route="route('admin.users.delete', user.slug)"
                                    />
                                </div>
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-else>
                        <TableCell colspan="4" class="h-24 text-center"> No results. </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <div class="py-4">
            <InertiaPagination :paginator="users" :only="['users']" />
        </div>
    </div>
</template>
