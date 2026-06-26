<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ClipboardList, ChevronDown, ChevronUp, RefreshCw, Loader2 } from '@lucide/vue';
import { ref, onMounted } from 'vue';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';

interface OrderItem {
    id: number;
    product_name: string;
    price: string;
    quantity: number;
}

interface Order {
    id: number;
    total: string;
    status: string;
    created_at: string;
    user?: { id: number; full_name: string; email: string };
    items: OrderItem[];
}

interface Pagination<T> { data: T[]; current_page: number; last_page: number; total: number; }

const orders = ref<Order[]>([]);
const pagination = ref<Omit<Pagination<Order>, 'data'> | null>(null);
const currentPage = ref(1);
const filterStatus = ref('');
const loading = ref(true);
const updatingStatus = ref<Set<number>>(new Set());
const expandedOrders = ref<Set<number>>(new Set());

const ORDER_STATUSES = ['Pending', 'For Delivery', 'Delivered', 'Canceled'];

const statusConfig: Record<string, string> = {
    Pending:        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    'For Delivery': 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    Delivered:      'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    Canceled:       'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
};

function formatPrice(val: string | number): string {
    return `₱${parseFloat(String(val)).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function toggleExpand(orderId: number) {
    if (expandedOrders.value.has(orderId)) expandedOrders.value.delete(orderId);
    else expandedOrders.value.add(orderId);
}

async function fetchOrders(pg = 1) {
    loading.value = true;
    try {
        const params: Record<string, string | number | undefined> = { page: pg };
        if (filterStatus.value) params.status = filterStatus.value;
        const res = await api.get<Pagination<Order>>('/api/admin/orders', params);
        orders.value = res.data;
        const { data: _, ...meta } = res;
        pagination.value = meta;
        currentPage.value = meta.current_page;
    } catch { /* silent */ } finally { loading.value = false; }
}

async function updateStatus(order: Order, newStatus: string) {
    if (order.status === newStatus) return;
    updatingStatus.value.add(order.id);
    try {
        const res = await api.patch<Order>(`/api/admin/orders/${order.id}/status`, { status: newStatus });
        const idx = orders.value.findIndex(o => o.id === order.id);
        if (idx !== -1) orders.value[idx] = { ...orders.value[idx], ...res };
    } catch { /* silent */ }
    finally { updatingStatus.value.delete(order.id); }
}

function onFilterChange() {
    fetchOrders(1);
}

onMounted(() => fetchOrders());
</script>

<template>
    <Head title="Order Management" />

    <div class="flex flex-col gap-6 p-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">Order Management</h1>
                <p class="text-sm text-muted-foreground">View and manage all customer orders.</p>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="flex gap-3">
            <Select v-model="filterStatus" @update:model-value="onFilterChange">
                <SelectTrigger class="w-48">
                    <SelectValue placeholder="All Statuses" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">All Statuses</SelectItem>
                    <SelectItem v-for="status in ORDER_STATUSES" :key="status" :value="status">
                        {{ status }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Button variant="outline" size="icon" @click="fetchOrders(currentPage)">
                <RefreshCw class="size-4" :class="{ 'animate-spin': loading }" />
            </Button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-24">
            <Loader2 class="size-8 animate-spin text-muted-foreground" />
        </div>

        <!-- Empty state -->
        <div v-else-if="orders.length === 0" class="flex flex-col items-center justify-center py-24 text-center gap-3">
            <ClipboardList class="size-12 text-muted-foreground" />
            <p class="font-medium text-foreground">No orders found</p>
            <p class="text-sm text-muted-foreground">Orders will appear here once customers place them.</p>
        </div>

        <!-- Orders List -->
        <div v-else class="space-y-3">
            <Card
                v-for="order in orders"
                :key="order.id"
                class="overflow-hidden border-border/60"
            >
                <!-- Order Row -->
                <CardHeader class="p-0">
                    <div class="flex flex-wrap items-center gap-4 px-5 py-4">
                        <!-- Expand toggle -->
                        <button
                            class="flex items-center gap-2 text-left hover:text-foreground text-muted-foreground transition-colors"
                            @click="toggleExpand(order.id)"
                        >
                            <ChevronUp v-if="expandedOrders.has(order.id)" class="size-4" />
                            <ChevronDown v-else class="size-4" />
                        </button>

                        <!-- Order number + date -->
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-foreground">Order #{{ order.id }}</p>
                            <p class="text-xs text-muted-foreground">{{ formatDate(order.created_at) }}</p>
                        </div>

                        <!-- Customer -->
                        <div class="min-w-[150px]">
                            <p class="text-sm font-medium text-foreground">{{ order.user?.full_name ?? '—' }}</p>
                            <p class="text-xs text-muted-foreground">{{ order.user?.email ?? '' }}</p>
                        </div>

                        <!-- Total -->
                        <span class="font-bold text-primary min-w-[100px] text-right">{{ formatPrice(order.total) }}</span>

                        <!-- Status updater -->
                        <div class="flex items-center gap-2 min-w-[160px]">
                            <Badge class="text-xs border-0 mr-2" :class="statusConfig[order.status] ?? ''">
                                {{ order.status }}
                            </Badge>
                            <Select
                                :model-value="order.status"
                                :disabled="updatingStatus.has(order.id)"
                                @update:model-value="(val: string) => updateStatus(order, val)"
                            >
                                <SelectTrigger class="h-8 text-xs w-36">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="status in ORDER_STATUSES" :key="status" :value="status">
                                        {{ status }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Loader2 v-if="updatingStatus.has(order.id)" class="size-4 animate-spin text-muted-foreground" />
                        </div>
                    </div>
                </CardHeader>

                <!-- Expandable items -->
                <CardContent v-if="expandedOrders.has(order.id)" class="border-t border-border px-5 py-4">
                    <p class="text-xs font-semibold uppercase text-muted-foreground tracking-wider mb-3">Order Items</p>
                    <div class="space-y-0">
                        <!-- Items header -->
                        <div class="grid grid-cols-4 text-xs font-semibold uppercase text-muted-foreground tracking-wider pb-2 border-b border-border">
                            <span class="col-span-2">Product</span>
                            <span class="text-right">Unit Price</span>
                            <span class="text-right">Subtotal</span>
                        </div>
                        <div
                            v-for="item in order.items"
                            :key="item.id"
                            class="grid grid-cols-4 items-center py-2.5 border-b border-border/40 last:border-0 text-sm"
                        >
                            <div class="col-span-2">
                                <span class="font-medium text-foreground">{{ item.product_name }}</span>
                                <span class="text-muted-foreground ml-2">× {{ item.quantity }}</span>
                            </div>
                            <span class="text-right text-muted-foreground">{{ formatPrice(item.price) }}</span>
                            <span class="text-right font-semibold">{{ formatPrice(parseFloat(item.price) * item.quantity) }}</span>
                        </div>
                    </div>
                    <div class="flex justify-between items-center mt-4 pt-3 border-t border-border font-bold text-base">
                        <span>Order Total</span>
                        <span class="text-primary">{{ formatPrice(order.total) }}</span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Pagination -->
        <div v-if="pagination && pagination.last_page > 1" class="flex items-center justify-between text-sm text-muted-foreground">
            <span>{{ pagination.total }} orders total</span>
            <div class="flex gap-2">
                <Button variant="outline" size="sm" :disabled="currentPage <= 1" @click="fetchOrders(currentPage - 1)">Previous</Button>
                <span class="flex items-center px-2">{{ currentPage }} / {{ pagination.last_page }}</span>
                <Button variant="outline" size="sm" :disabled="currentPage >= pagination.last_page" @click="fetchOrders(currentPage + 1)">Next</Button>
            </div>
        </div>
    </div>
</template>
