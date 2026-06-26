<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ClipboardList, ChevronDown, ChevronUp, Package, ArrowLeft, Loader2 } from '@lucide/vue';
import { ref, computed, onMounted } from 'vue';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { index as storefrontIndex } from '@/routes/storefront';

interface OrderItem {
    id: number;
    product_name: string;
    price: string;
    quantity: number;
}

interface Order {
    id: number;
    total: string;
    status: 'Pending' | 'For Delivery' | 'Delivered' | 'Canceled';
    created_at: string;
    items: OrderItem[];
}

interface Pagination<T> {
    data: T[];
    current_page: number;
    last_page: number;
}

const orders = ref<Order[]>([]);
const pagination = ref<Omit<Pagination<Order>, 'data'> | null>(null);
const loading = ref(true);
const currentPage = ref(1);
const expandedOrders = ref<Set<number>>(new Set());

const statusConfig: Record<string, { label: string; class: string }> = {
    Pending:      { label: 'Pending',      class: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' },
    'For Delivery': { label: 'For Delivery', class: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' },
    Delivered:    { label: 'Delivered',    class: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' },
    Canceled:     { label: 'Canceled',     class: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' },
};

function formatPrice(val: string | number): string {
    return `₱${parseFloat(String(val)).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function toggleExpand(orderId: number) {
    if (expandedOrders.value.has(orderId)) {
        expandedOrders.value.delete(orderId);
    } else {
        expandedOrders.value.add(orderId);
    }
}

async function fetchOrders(pg = 1) {
    loading.value = true;
    try {
        const res = await api.get<Pagination<Order>>(`/api/orders`, { page: pg });
        orders.value = res.data;
        const { data: _, ...meta } = res;
        pagination.value = meta;
        currentPage.value = meta.current_page;
    } catch { // silent
    } finally { loading.value = false; }
}

onMounted(() => fetchOrders());
</script>

<template>
    <Head title="My Orders" />

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="flex items-center gap-3 mb-8">
            <Button variant="ghost" size="icon-sm" @click="router.visit(storefrontIndex())">
                <ArrowLeft class="size-4" />
            </Button>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">My Orders</h1>
                <p class="text-sm text-muted-foreground">Track all your past and current orders.</p>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-24">
            <Loader2 class="size-8 animate-spin text-muted-foreground" />
        </div>

        <!-- Empty state -->
        <div v-else-if="orders.length === 0" class="flex flex-col items-center justify-center py-24 text-center gap-4">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-secondary">
                <ClipboardList class="size-8 text-muted-foreground" />
            </div>
            <h2 class="text-xl font-semibold text-foreground">No orders yet</h2>
            <p class="text-muted-foreground">You haven't placed any orders.</p>
            <Button @click="router.visit(storefrontIndex())">
                <Package class="mr-2 size-4" />
                Start Shopping
            </Button>
        </div>

        <!-- Orders list -->
        <div v-else class="space-y-4">
            <Card
                v-for="order in orders"
                :key="order.id"
                class="overflow-hidden border-border/60"
            >
                <!-- Order header -->
                <CardHeader class="p-0">
                    <button
                        class="flex w-full items-center justify-between px-5 py-4 text-left hover:bg-muted/40 transition-colors"
                        @click="toggleExpand(order.id)"
                    >
                        <div class="flex items-center gap-4 flex-wrap">
                            <div>
                                <p class="font-semibold text-foreground">Order #{{ order.id }}</p>
                                <p class="text-xs text-muted-foreground mt-0.5">{{ formatDate(order.created_at) }}</p>
                            </div>
                            <Badge
                                class="text-xs border-0"
                                :class="statusConfig[order.status]?.class ?? ''"
                            >
                                {{ statusConfig[order.status]?.label ?? order.status }}
                            </Badge>
                        </div>
                        <div class="flex items-center gap-3 flex-shrink-0">
                            <span class="font-bold text-primary">{{ formatPrice(order.total) }}</span>
                            <ChevronUp v-if="expandedOrders.has(order.id)" class="size-4 text-muted-foreground" />
                            <ChevronDown v-else class="size-4 text-muted-foreground" />
                        </div>
                    </button>
                </CardHeader>

                <!-- Order items (expanded) -->
                <CardContent v-if="expandedOrders.has(order.id)" class="border-t border-border px-5 py-4">
                    <p class="text-xs font-semibold uppercase text-muted-foreground tracking-wider mb-3">Order Items</p>
                    <div class="space-y-2">
                        <div
                            v-for="item in order.items"
                            :key="item.id"
                            class="flex items-center justify-between text-sm py-2 border-b border-border/40 last:border-0"
                        >
                            <div>
                                <span class="font-medium text-foreground">{{ item.product_name }}</span>
                                <span class="text-muted-foreground"> × {{ item.quantity }}</span>
                            </div>
                            <span class="font-semibold">{{ formatPrice(parseFloat(item.price) * item.quantity) }}</span>
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
        <div v-if="pagination && pagination.last_page > 1" class="mt-8 flex items-center justify-center gap-2">
            <Button variant="outline" size="sm" :disabled="currentPage <= 1" @click="fetchOrders(currentPage - 1)">
                Previous
            </Button>
            <span class="text-sm text-muted-foreground px-2">Page {{ currentPage }} of {{ pagination.last_page }}</span>
            <Button variant="outline" size="sm" :disabled="currentPage >= pagination.last_page" @click="fetchOrders(currentPage + 1)">
                Next
            </Button>
        </div>
    </div>
</template>
