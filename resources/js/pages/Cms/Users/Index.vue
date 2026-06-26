<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { UserPlus, Pencil, Trash2, Search, RefreshCw, ToggleLeft, ToggleRight, Loader2, ShieldCheck, User } from '@lucide/vue';
import { ref, onMounted, watch } from 'vue';
import { api, extractErrors } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter,
} from '@/components/ui/dialog';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import InputError from '@/components/InputError.vue';

interface UserRecord {
    id: number;
    full_name: string;
    email: string;
    role: 'admin' | 'guest';
    is_active: boolean;
    created_at: string;
}

interface Pagination<T> { data: T[]; current_page: number; last_page: number; total: number; }
type FormErrors = Partial<Record<string, string>>;

// ── State ──────────────────────────────────────────────────────────────────
const users = ref<UserRecord[]>([]);
const pagination = ref<Omit<Pagination<UserRecord>, 'data'> | null>(null);
const currentPage = ref(1);
const search = ref('');
const loading = ref(true);

// Modals
const showCreate = ref(false);
const showEdit = ref(false);
const showDelete = ref(false);
const selectedUser = ref<UserRecord | null>(null);

// Create form (full fields)
const createForm = ref({ full_name: '', email: '', password: '', password_confirmation: '', role: 'guest' as 'admin' | 'guest' });
const createErrors = ref<FormErrors>({});

// Edit form (role + active only)
const editForm = ref({ role: 'guest' as 'admin' | 'guest', is_active: true });
const editErrors = ref<FormErrors>({});

const submitting = ref(false);

// ── Debounced search ───────────────────────────────────────────────────────
let searchTimer: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => fetchUsers(1), 400);
});

// ── Helpers ────────────────────────────────────────────────────────────────
function formatDate(d: string) {
    return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}

function openCreate() {
    createForm.value = { full_name: '', email: '', password: '', password_confirmation: '', role: 'guest' };
    createErrors.value = {};
    showCreate.value = true;
}

function openEdit(user: UserRecord) {
    selectedUser.value = user;
    editForm.value = { role: user.role, is_active: user.is_active };
    editErrors.value = {};
    showEdit.value = true;
}

function openDelete(user: UserRecord) { selectedUser.value = user; showDelete.value = true; }

// ── API calls ──────────────────────────────────────────────────────────────
async function fetchUsers(pg = 1) {
    loading.value = true;
    try {
        const res = await api.get<Pagination<UserRecord>>('/api/admin/users', {
            page: pg, search: search.value || undefined,
        } as Record<string, string | number | undefined>);
        users.value = res.data;
        // eslint-disable-next-line @typescript-eslint/no-unused-vars
        const { data: _omit, ...meta } = res;
        pagination.value = meta;
        currentPage.value = meta.current_page;
    } catch { /* silent */ } finally { loading.value = false; }
}

async function createUser() {
    submitting.value = true; createErrors.value = {};
    try {
        const res = await api.post<UserRecord>('/api/admin/users', createForm.value as unknown as Record<string, unknown>);
        users.value.unshift(res);
        showCreate.value = false;
    } catch (err) { createErrors.value = extractErrors(err); }
    finally { submitting.value = false; }
}

async function updateUser() {
    if (!selectedUser.value) return;
    submitting.value = true; editErrors.value = {};
    try {
        const payload: Record<string, unknown> = {
            role: editForm.value.role,
            is_active: editForm.value.is_active,
        };
        const res = await api.put<UserRecord>(`/api/admin/users/${selectedUser.value.id}`, payload);
        const idx = users.value.findIndex(u => u.id === selectedUser.value!.id);
        if (idx !== -1) users.value[idx] = res;
        showEdit.value = false;
    } catch (err) { editErrors.value = extractErrors(err); }
    finally { submitting.value = false; }
}

async function deleteUser() {
    if (!selectedUser.value) return;
    submitting.value = true;
    try {
        await api.delete(`/api/admin/users/${selectedUser.value.id}`);
        users.value = users.value.filter(u => u.id !== selectedUser.value!.id);
        showDelete.value = false;
    } catch { /* silent */ }
    finally { submitting.value = false; }
}

async function toggleActive(user: UserRecord) {
    try {
        const res = await api.patch<UserRecord>(`/api/admin/users/${user.id}/toggle-active`);
        const idx = users.value.findIndex(u => u.id === user.id);
        if (idx !== -1) users.value[idx] = res;
    } catch { /* silent */ }
}

onMounted(() => fetchUsers());
</script>

<template>
    <Head title="User Management" />

    <div class="flex flex-col gap-6 p-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">User Management</h1>
                <p class="text-sm text-muted-foreground">Manage all registered users.</p>
            </div>
            <Button class="gap-2" @click="openCreate">
                <UserPlus class="size-4" />
                Add User
            </Button>
        </div>

        <!-- Search + Refresh -->
        <div class="flex gap-3">
            <div class="relative flex-1 max-w-sm">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                <Input v-model="search" placeholder="Search by name or email…" class="pl-9" />
            </div>
            <Button variant="outline" size="icon" @click="fetchUsers(currentPage)">
                <RefreshCw class="size-4" :class="{ 'animate-spin': loading }" />
            </Button>
        </div>

        <!-- Table Card -->
        <Card class="border-border/60">
            <CardContent class="p-0">
                <!-- Loading -->
                <div v-if="loading" class="flex items-center justify-center py-16">
                    <Loader2 class="size-7 animate-spin text-muted-foreground" />
                </div>

                <!-- Empty -->
                <div v-else-if="users.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                    <User class="size-10 text-muted-foreground mb-3" />
                    <p class="font-medium text-foreground">No users found</p>
                    <p class="text-sm text-muted-foreground mt-1">Try adjusting your search.</p>
                </div>

                <!-- Data -->
                <div v-else>
                    <!-- Header Row -->
                    <div class="grid grid-cols-12 items-center px-5 py-3 text-xs font-semibold uppercase text-muted-foreground tracking-wider border-b border-border bg-muted/30">
                        <span class="col-span-4">User</span>
                        <span class="col-span-2">Role</span>
                        <span class="col-span-2">Status</span>
                        <span class="col-span-2">Joined</span>
                        <span class="col-span-2 text-right">Actions</span>
                    </div>

                    <!-- Data Rows -->
                    <div
                        v-for="user in users"
                        :key="user.id"
                        class="grid grid-cols-12 items-center px-5 py-4 border-b border-border/40 last:border-0 hover:bg-muted/20 transition-colors"
                    >
                        <!-- User info -->
                        <div class="col-span-4">
                            <p class="font-medium text-foreground">{{ user.full_name }}</p>
                            <p class="text-xs text-muted-foreground mt-0.5">{{ user.email }}</p>
                        </div>

                        <!-- Role -->
                        <div class="col-span-2">
                            <Badge v-if="user.role === 'admin'" class="gap-1 border-0 bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400 text-xs">
                                <ShieldCheck class="size-3" /> Admin
                            </Badge>
                            <Badge v-else class="gap-1 border-0 bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400 text-xs">
                                <User class="size-3" /> Guest
                            </Badge>
                        </div>

                        <!-- Status -->
                        <div class="col-span-2">
                            <Badge
                                class="border-0 text-xs"
                                :class="user.is_active
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                    : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'"
                            >
                                {{ user.is_active ? 'Active' : 'Inactive' }}
                            </Badge>
                        </div>

                        <!-- Joined -->
                        <span class="col-span-2 text-sm text-muted-foreground">{{ formatDate(user.created_at) }}</span>

                        <!-- Actions -->
                        <div class="col-span-2 flex items-center justify-end gap-1">
                            <!-- Toggle active (guest only) -->
                            <Button
                                v-if="user.role === 'guest'"
                                variant="ghost"
                                size="icon-sm"
                                class="text-muted-foreground hover:text-foreground"
                                :title="user.is_active ? 'Deactivate account' : 'Activate account'"
                                @click="toggleActive(user)"
                            >
                                <ToggleRight v-if="user.is_active" class="size-4 text-green-500" />
                                <ToggleLeft v-else class="size-4 text-muted-foreground" />
                            </Button>

                            <!-- Edit (role only) -->
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                class="text-muted-foreground hover:text-foreground"
                                title="Edit role"
                                @click="openEdit(user)"
                            >
                                <Pencil class="size-4" />
                            </Button>

                            <!-- Delete -->
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                class="text-muted-foreground hover:text-red-500"
                                title="Delete user"
                                @click="openDelete(user)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Pagination -->
        <div v-if="pagination && pagination.last_page > 1" class="flex items-center justify-between text-sm text-muted-foreground">
            <span>{{ pagination.total }} users total</span>
            <div class="flex gap-2">
                <Button variant="outline" size="sm" :disabled="currentPage <= 1" @click="fetchUsers(currentPage - 1)">Previous</Button>
                <span class="flex items-center px-2">{{ currentPage }} / {{ pagination.last_page }}</span>
                <Button variant="outline" size="sm" :disabled="currentPage >= pagination.last_page" @click="fetchUsers(currentPage + 1)">Next</Button>
            </div>
        </div>
    </div>

    <!-- ── CREATE DIALOG (full fields) ────────────────────────────────────── -->
    <Dialog v-model:open="showCreate">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Create New User</DialogTitle>
                <DialogDescription>Fill in the details for the new user account.</DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="createUser">
                <div class="space-y-1.5">
                    <Label for="c-full_name">Full Name</Label>
                    <Input id="c-full_name" v-model="createForm.full_name" placeholder="Juan dela Cruz" required />
                    <InputError :message="createErrors.full_name" />
                </div>
                <div class="space-y-1.5">
                    <Label for="c-email">Email</Label>
                    <Input id="c-email" v-model="createForm.email" type="email" placeholder="user@example.com" required />
                    <InputError :message="createErrors.email" />
                </div>
                <div class="space-y-1.5">
                    <Label for="c-password">Password</Label>
                    <Input id="c-password" v-model="createForm.password" type="password" required />
                    <InputError :message="createErrors.password" />
                </div>
                <div class="space-y-1.5">
                    <Label for="c-pw-confirm">Confirm Password</Label>
                    <Input id="c-pw-confirm" v-model="createForm.password_confirmation" type="password" required />
                </div>
                <div class="space-y-1.5">
                    <Label>Role</Label>
                    <Select v-model="createForm.role">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="guest">Guest</SelectItem>
                            <SelectItem value="admin">Admin</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="createErrors.role" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showCreate = false">Cancel</Button>
                    <Button type="submit" :disabled="submitting">
                        <Spinner v-if="submitting" />
                        Create User
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- ── EDIT DIALOG (role + active only) ──────────────────────────────── -->
    <Dialog v-model:open="showEdit">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Change User Privileges</DialogTitle>
                <DialogDescription>
                    Update the role and active status for
                    <strong>{{ selectedUser?.full_name }}</strong>.
                </DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="updateUser">
                <div class="space-y-1.5">
                    <Label>Role</Label>
                    <Select v-model="editForm.role">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="guest">Guest</SelectItem>
                            <SelectItem value="admin">Admin</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="editErrors.role" />
                </div>

                <div class="flex items-center gap-3 rounded-lg border border-border p-3">
                    <input
                        id="e-is_active"
                        v-model="editForm.is_active"
                        type="checkbox"
                        :disabled="editForm.role === 'admin'"
                        class="h-4 w-4 rounded accent-primary disabled:opacity-50 disabled:cursor-not-allowed"
                    />
                    <div>
                        <Label for="e-is_active" class="cursor-pointer">Account is Active</Label>
                        <p v-if="editForm.role === 'admin'" class="text-xs text-muted-foreground mt-0.5">Admin accounts cannot be deactivated.</p>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="showEdit = false">Cancel</Button>
                    <Button type="submit" :disabled="submitting">
                        <Spinner v-if="submitting" />
                        Save Changes
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- ── DELETE CONFIRM ─────────────────────────────────────────────────── -->
    <Dialog v-model:open="showDelete">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Delete User</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete <strong>{{ selectedUser?.full_name }}</strong>?
                    This action cannot be undone.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="showDelete = false">Cancel</Button>
                <Button variant="destructive" :disabled="submitting" @click="deleteUser">
                    <Spinner v-if="submitting" />
                    Delete User
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
