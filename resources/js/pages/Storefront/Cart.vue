<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ShoppingCart, Trash2, Package, ArrowLeft, Loader2, CheckCircle2, MinusCircle, PlusCircle } from '@lucide/vue';
import { ref, computed, onMounted } from 'vue';
import { api, getErrorMessage } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { index as storefrontIndex } from '@/routes/storefront';

interface Product {
    id: number;
    name: string;
    price: string;
    image_path: string | null;
    stock: number;
}

interface CartItem {
    id: number;
    quantity: number;
    product: Product;
}

const items = ref<CartItem[]>([]);
const loading = ref(true);
const checkingOut = ref(false);
const updatingItems = ref<Set<number>>(new Set());
const removingItems = ref<Set<number>>(new Set());
const checkoutSuccess = ref(false);
const checkoutError = ref('');

const total = computed(() =>
    items.value.reduce((sum, item) => sum + item.quantity * parseFloat(item.product.price), 0)
);

function formatPrice(val: number | string): string {
    return `₱${parseFloat(String(val)).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function getImageUrl(path: string | null): string | null {
    if (!path) return null;
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    return `/storage/${path}`;
}

async function fetchCart() {
    loading.value = true;
    try {
        const res = await api.get<{ items: CartItem[]; total: string }>('/api/cart');
        items.value = res.items ?? [];
    } catch {
        // silent
    } finally {
        loading.value = false;
    }
}

async function updateQuantity(item: CartItem, delta: number) {
    const newQty = item.quantity + delta;
    if (newQty < 1) return;
    updatingItems.value.add(item.id);
    try {
        const res = await api.patch<CartItem>(`/api/cart/${item.id}`, { quantity: newQty });
        const idx = items.value.findIndex(i => i.id === item.id);
        if (idx !== -1) items.value[idx] = res;
    } catch {
        // silent
    } finally {
        updatingItems.value.delete(item.id);
    }
}

async function removeItem(itemId: number) {
    removingItems.value.add(itemId);
    try {
        await api.delete(`/api/cart/${itemId}`);
        items.value = items.value.filter(i => i.id !== itemId);
    } catch {
        // silent
    } finally {
        removingItems.value.delete(itemId);
    }
}

async function checkout() {
    checkoutError.value = '';
    checkingOut.value = true;
    try {
        await api.post('/api/checkout');
        items.value = [];
        checkoutSuccess.value = true;
    } catch (err) {
        checkoutError.value = getErrorMessage(err, 'Checkout failed. Please try again.');
    } finally {
        checkingOut.value = false;
    }
}

onMounted(fetchCart);
</script>

<template>
    <Head title="Your Cart" />

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page header -->
        <div class="flex items-center gap-3 mb-8">
            <Button variant="ghost" size="icon-sm" @click="router.visit(storefrontIndex())">
                <ArrowLeft class="size-4" />
            </Button>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">Your Cart</h1>
                <p class="text-sm text-muted-foreground">Review your items before checkout.</p>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-24">
            <Loader2 class="size-8 animate-spin text-muted-foreground" />
        </div>

        <!-- Checkout success -->
        <div v-else-if="checkoutSuccess" class="flex flex-col items-center justify-center py-24 text-center gap-4">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                <CheckCircle2 class="size-8 text-green-600 dark:text-green-400" />
            </div>
            <h2 class="text-xl font-semibold text-foreground">Order Placed Successfully!</h2>
            <p class="text-muted-foreground max-w-sm">Your order has been placed. You can track it in My Orders.</p>
            <div class="flex gap-3 mt-2">
                <Button variant="outline" @click="router.visit(storefrontIndex())">Continue Shopping</Button>
                <Button @click="router.visit('/my-orders')">View My Orders</Button>
            </div>
        </div>

        <!-- Empty cart -->
        <div v-else-if="items.length === 0" class="flex flex-col items-center justify-center py-24 text-center gap-4">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-secondary">
                <ShoppingCart class="size-8 text-muted-foreground" />
            </div>
            <h2 class="text-xl font-semibold text-foreground">Your cart is empty</h2>
            <p class="text-muted-foreground">Add some products to get started.</p>
            <Button @click="router.visit(storefrontIndex())">
                <Package class="mr-2 size-4" />
                Browse Products
            </Button>
        </div>

        <!-- Cart content -->
        <div v-else class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Items list -->
            <div class="lg:col-span-2 space-y-3">
                <div
                    v-for="item in items"
                    :key="item.id"
                    class="flex items-center gap-4 rounded-xl border border-border bg-card p-4 transition-all"
                    :class="{ 'opacity-50': removingItems.has(item.id) }"
                >
                    <!-- Product image -->
                    <div class="h-18 w-18 flex-shrink-0 overflow-hidden rounded-lg bg-secondary">
                        <img
                            v-if="getImageUrl(item.product.image_path)"
                            :src="getImageUrl(item.product.image_path)!"
                            :alt="item.product.name"
                            class="h-full w-full object-cover"
                        />
                        <div v-else class="flex h-full w-full items-center justify-center">
                            <Package class="size-6 text-muted-foreground/50" />
                        </div>
                    </div>

                    <!-- Product details -->
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-foreground truncate">{{ item.product.name }}</p>
                        <p class="text-sm text-muted-foreground mt-0.5">{{ formatPrice(item.product.price) }} each</p>
                        <p class="text-sm font-semibold text-primary mt-1">
                            Subtotal: {{ formatPrice(item.quantity * parseFloat(item.product.price)) }}
                        </p>
                    </div>

                    <!-- Quantity controls -->
                    <div class="flex items-center gap-2">
                        <button
                            class="text-muted-foreground hover:text-foreground transition-colors disabled:opacity-40"
                            :disabled="item.quantity <= 1 || updatingItems.has(item.id)"
                            @click="updateQuantity(item, -1)"
                        >
                            <MinusCircle class="size-5" />
                        </button>
                        <span class="w-8 text-center font-semibold text-sm">
                            <Loader2 v-if="updatingItems.has(item.id)" class="size-4 animate-spin mx-auto" />
                            <span v-else>{{ item.quantity }}</span>
                        </span>
                        <button
                            class="text-muted-foreground hover:text-foreground transition-colors disabled:opacity-40"
                            :disabled="item.quantity >= item.product.stock || updatingItems.has(item.id)"
                            @click="updateQuantity(item, 1)"
                        >
                            <PlusCircle class="size-5" />
                        </button>
                    </div>

                    <!-- Remove -->
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        class="text-muted-foreground hover:text-red-500 flex-shrink-0"
                        :disabled="removingItems.has(item.id)"
                        @click="removeItem(item.id)"
                    >
                        <Loader2 v-if="removingItems.has(item.id)" class="size-4 animate-spin" />
                        <Trash2 v-else class="size-4" />
                    </Button>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="lg:col-span-1">
                <Card class="sticky top-24">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base">Order Summary</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <div v-for="item in items" :key="item.id" class="flex justify-between text-sm">
                            <span class="text-muted-foreground truncate max-w-[160px]">{{ item.product.name }} ×{{ item.quantity }}</span>
                            <span>{{ formatPrice(item.quantity * parseFloat(item.product.price)) }}</span>
                        </div>
                        <div class="border-t border-border pt-3 mt-3 flex justify-between font-bold text-base">
                            <span>Total</span>
                            <span class="text-primary">{{ formatPrice(total) }}</span>
                        </div>
                    </CardContent>
                    <CardFooter class="flex-col gap-3 pt-0">
                        <p v-if="checkoutError" class="text-xs text-red-500 text-center">{{ checkoutError }}</p>
                        <Button class="w-full" :disabled="checkingOut || items.length === 0" @click="checkout">
                            <Spinner v-if="checkingOut" />
                            {{ checkingOut ? 'Placing Order...' : 'Checkout' }}
                        </Button>
                        <Button variant="outline" class="w-full" @click="router.visit(storefrontIndex())">
                            Continue Shopping
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </div>
    </div>
</template>
