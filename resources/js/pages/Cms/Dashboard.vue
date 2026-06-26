<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Users, Package, ClipboardList, ShoppingCart, TrendingUp, Loader2 } from '@lucide/vue';
import { ref, onMounted } from 'vue';
import { api } from '@/lib/api';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

interface Stats {
    totalUsers: number;
    totalProducts: number;
    totalOrders: number;
    pendingOrders: number;
    recentOrders: Array<{
        id: number;
        total: string;
        status: string;
        created_at: string;
        user?: { full_name: string; email: string };
    }>;
}

const stats = ref<Stats | null>(null);
const loading = ref(true);

const statusConfig: Record<string, string> = {
    Pending:        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    'For Delivery': 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    Delivered:      'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    Canceled:       'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
};

function formatPrice(val: string | number): string {
    return `₱${parseFloat(String(val)).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

async function loadDashboard() {
    loading.value = true;
    try {
        const [usersRes, productsRes, ordersRes] = await Promise.all([
            api.get<{total: number; data: unknown[]}>('/api/admin/users'),
            api.get<{total: number; data: unknown[]}>('/api/admin/products'),
            api.get<{total: number; data: Array<{status: string}>}>('/api/admin/orders'),
        ]);

        const allOrders = ordersRes.data ?? [];
        const pendingOrders = allOrders.filter((o) => o.status === 'Pending').length;

        stats.value = {
            totalUsers:    usersRes.total ?? 0,
            totalProducts: productsRes.total ?? 0,
            totalOrders:   ordersRes.total ?? 0,
            pendingOrders,
            recentOrders:  allOrders.slice(0, 5) as Stats['recentOrders'],
        };
    } catch {
        // silent
    } finally {
        loading.value = false;
    }
}

onMounted(loadDashboard);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-6 p-6">
        <!-- Page title -->
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Dashboard</h1>
            <p class="text-sm text-muted-foreground">Welcome back, Admin. Here's what's happening.</p>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-24">
            <Loader2 class="size-8 animate-spin text-muted-foreground" />
        </div>

        <template v-else-if="stats">
            <!-- Stats cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card class="border-border/60">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Total Users</CardTitle>
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                            <Users class="size-4 text-blue-600 dark:text-blue-400" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold text-foreground">{{ stats.totalUsers }}</p>
                        <p class="text-xs text-muted-foreground mt-1">Registered accounts</p>
                    </CardContent>
                </Card>

                <Card class="border-border/60">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Total Products</CardTitle>
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/30">
                            <Package class="size-4 text-purple-600 dark:text-purple-400" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold text-foreground">{{ stats.totalProducts }}</p>
                        <p class="text-xs text-muted-foreground mt-1">Listed products</p>
                    </CardContent>
                </Card>

                <Card class="border-border/60">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Total Orders</CardTitle>
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
                            <ClipboardList class="size-4 text-green-600 dark:text-green-400" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold text-foreground">{{ stats.totalOrders }}</p>
                        <p class="text-xs text-muted-foreground mt-1">All time orders</p>
                    </CardContent>
                </Card>

                <Card class="border-border/60">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Pending Orders</CardTitle>
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-yellow-100 dark:bg-yellow-900/30">
                            <ShoppingCart class="size-4 text-yellow-600 dark:text-yellow-400" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold text-foreground">{{ stats.pendingOrders }}</p>
                        <p class="text-xs text-muted-foreground mt-1">Awaiting action</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Recent Orders -->
            <Card class="border-border/60">
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <TrendingUp class="size-5 text-muted-foreground" />
                        <CardTitle class="text-base">Recent Orders</CardTitle>
                    </div>
                    <CardDescription>The 5 most recent orders placed.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="stats.recentOrders.length === 0" class="text-sm text-muted-foreground text-center py-8">
                        No orders placed yet.
                    </div>
                    <div v-else class="space-y-1">
                        <!-- Header row -->
                        <div class="grid grid-cols-4 text-xs font-semibold uppercase text-muted-foreground tracking-wider pb-2 border-b border-border">
                            <span>Order #</span>
                            <span>Customer</span>
                            <span>Total</span>
                            <span>Status</span>
                        </div>
                        <!-- Data rows -->
                        <div
                            v-for="order in stats.recentOrders"
                            :key="order.id"
                            class="grid grid-cols-4 items-center py-3 border-b border-border/40 last:border-0 text-sm"
                        >
                            <span class="font-medium text-foreground">#{{ order.id }}</span>
                            <div>
                                <p class="font-medium text-foreground">{{ order.user?.full_name ?? 'Unknown' }}</p>
                                <p class="text-xs text-muted-foreground">{{ formatDate(order.created_at) }}</p>
                            </div>
                            <span class="font-semibold">{{ formatPrice(order.total) }}</span>
                            <Badge class="text-xs border-0 w-fit" :class="statusConfig[order.status] ?? ''">{{ order.status }}</Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </template>
    </div>
</template>