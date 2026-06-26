<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ShoppingCart, Package, AlertCircle, CheckCircle2, Loader2 } from '@lucide/vue';
import { ref, computed, onMounted } from 'vue';
import { api, getErrorMessage } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardFooter } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';

interface Product {
    id: number;
    name: string;
    price: string;
    stock: number;
    image_path: string | null;
}

interface Pagination<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const page = usePage();
const user = computed(() => page.props.auth?.user as { role?: string } | null);
const isLoggedIn = computed(() => !!user.value);

const products = ref<Product[]>([]);
const pagination = ref<Omit<Pagination<Product>, 'data'> | null>(null);
const loading = ref(true);
const currentPage = ref(1);
const addingToCart = ref<Set<number>>(new Set());
const successMap = ref<Map<number, string>>(new Map());
const errorMap = ref<Map<number, string>>(new Map());

async function fetchProducts(pg = 1) {
    loading.value = true;
    try {
        const res = await api.get<Pagination<Product>>('/api/products', { page: pg });
        products.value = res.data;
        // eslint-disable-next-line @typescript-eslint/no-unused-vars
        const { data: _omit, ...meta } = res;
        pagination.value = meta;
        currentPage.value = meta.current_page;
    } catch {
        // silent
    } finally {
        loading.value = false;
    }
}

async function addToCart(product: Product) {
    if (!isLoggedIn.value) {
        router.visit('/login');
        return;
    }
    addingToCart.value.add(product.id);
    errorMap.value.delete(product.id);
    successMap.value.delete(product.id);

    try {
        await api.post('/api/cart', { product_id: product.id, quantity: 1 });
        successMap.value.set(product.id, 'Added!');
        setTimeout(() => successMap.value.delete(product.id), 2000);
    } catch (err) {
        const message = getErrorMessage(err, 'Could not add to cart.');
        errorMap.value.set(product.id, message);
        setTimeout(() => errorMap.value.delete(product.id), 3000);
    } finally {
        addingToCart.value.delete(product.id);
    }
}

function getImageUrl(path: string | null): string | null {
    if (!path) return null;
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    return `/storage/${path}`;
}

function formatPrice(price: string | number): string {
    return `₱${parseFloat(String(price)).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

onMounted(() => fetchProducts());
</script>

<template>
    <Head title="Shop" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold tracking-tight text-foreground">Our Products</h1>
            <p class="mt-1 text-muted-foreground">Browse and add items to your cart.</p>
        </div>

        <!-- Loading state -->
        <div v-if="loading" class="flex items-center justify-center py-24">
            <Loader2 class="size-8 animate-spin text-muted-foreground" />
        </div>

        <!-- Empty state -->
        <div v-else-if="products.length === 0" class="flex flex-col items-center justify-center py-24 text-center">
            <Package class="size-12 text-muted-foreground mb-4" />
            <p class="text-lg font-medium text-foreground">No products available</p>
            <p class="text-sm text-muted-foreground mt-1">Check back later for new items.</p>
        </div>

        <!-- Product Grid -->
        <div v-else class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <Card
                v-for="product in products"
                :key="product.id"
                class="group overflow-hidden border-border/60 transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5"
            >
                <!-- Product Image -->
                <div class="aspect-square overflow-hidden bg-muted relative">
                    <img
                        v-if="getImageUrl(product.image_path)"
                        :src="getImageUrl(product.image_path)!"
                        :alt="product.name"
                        class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                    />
                    <div v-else class="flex h-full w-full items-center justify-center bg-secondary/50">
                        <Package class="size-16 text-muted-foreground/40" />
                    </div>

                    <!-- Stock badge -->
                    <div class="absolute top-2 right-2">
                        <Badge v-if="product.stock === 0" variant="destructive" class="text-xs">Out of Stock</Badge>
                        <Badge v-else-if="product.stock <= 5" variant="secondary" class="text-xs bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">Low Stock</Badge>
                    </div>
                </div>

                <CardContent class="p-4">
                    <h3 class="font-semibold text-foreground line-clamp-2 leading-tight">{{ product.name }}</h3>
                    <p class="mt-1 text-xl font-bold text-primary">{{ formatPrice(product.price) }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">{{ product.stock }} in stock</p>
                </CardContent>

                <CardFooter class="p-4 pt-0 flex flex-col gap-2">
                    <!-- Success feedback -->
                    <div v-if="successMap.has(product.id)" class="flex items-center gap-1.5 text-sm text-green-600 dark:text-green-400">
                        <CheckCircle2 class="size-4" />
                        {{ successMap.get(product.id) }}
                    </div>
                    <!-- Error feedback -->
                    <div v-if="errorMap.has(product.id)" class="flex items-center gap-1.5 text-xs text-red-500">
                        <AlertCircle class="size-3.5" />
                        {{ errorMap.get(product.id) }}
                    </div>

                    <Button
                        class="w-full gap-2"
                        :disabled="product.stock === 0 || addingToCart.has(product.id)"
                        @click="addToCart(product)"
                    >
                        <Spinner v-if="addingToCart.has(product.id)" />
                        <ShoppingCart v-else class="size-4" />
                        {{ product.stock === 0 ? 'Out of Stock' : 'Add to Cart' }}
                    </Button>
                </CardFooter>
            </Card>
        </div>

        <!-- Pagination -->
        <div v-if="pagination && pagination.last_page > 1" class="mt-10 flex items-center justify-center gap-2">
            <Button
                variant="outline"
                size="sm"
                :disabled="currentPage <= 1"
                @click="fetchProducts(currentPage - 1)"
            >
                Previous
            </Button>
            <span class="text-sm text-muted-foreground px-2">
                Page {{ currentPage }} of {{ pagination.last_page }}
            </span>
            <Button
                variant="outline"
                size="sm"
                :disabled="currentPage >= pagination.last_page"
                @click="fetchProducts(currentPage + 1)"
            >
                Next
            </Button>
        </div>
    </div>
</template>