<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { PackagePlus, Pencil, Trash2, Package, RefreshCw, Loader2, ImagePlus, Link as LinkIcon, Upload } from '@lucide/vue';
import { ref, onMounted } from 'vue';
import { api, extractErrors } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import InputError from '@/components/InputError.vue';

interface Product {
    id: number;
    name: string;
    price: string;
    stock: number;
    image_path: string | null;
    created_at: string;
}

interface Pagination<T> { data: T[]; current_page: number; last_page: number; total: number; }
type FormErrors = Partial<Record<string, string>>;

const products = ref<Product[]>([]);
const pagination = ref<Omit<Pagination<Product>, 'data'> | null>(null);
const currentPage = ref(1);
const loading = ref(true);

const showCreate = ref(false);
const showEdit = ref(false);
const showDelete = ref(false);
const selectedProduct = ref<Product | null>(null);

// 'upload' | 'url'
const imageMode = ref<'upload' | 'url'>('upload');
const form = ref({ name: '', price: '', stock: '' });
const imageFile = ref<File | null>(null);
const imageUrlInput = ref('');
const imagePreview = ref<string | null>(null);
const formErrors = ref<FormErrors>({});
const submitting = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

function formatPrice(val: string | number): string {
    return `₱${parseFloat(String(val)).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

/** Returns a displayable src for any image_path (local storage path or full URL) */
function getImageSrc(path: string | null): string | null {
    if (!path) return null;
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    return `/storage/${path}`;
}

function onImageChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    imageFile.value = file;
    const reader = new FileReader();
    reader.onload = ev => { imagePreview.value = ev.target?.result as string; };
    reader.readAsDataURL(file);
}

function onUrlInput() {
    const url = imageUrlInput.value.trim();
    imagePreview.value = url || null;
}

function setImageMode(mode: 'upload' | 'url') {
    imageMode.value = mode;
    imageFile.value = null;
    imageUrlInput.value = '';
    imagePreview.value = null;
}

function resetForm() {
    form.value = { name: '', price: '', stock: '' };
    imageFile.value = null;
    imageUrlInput.value = '';
    imagePreview.value = null;
    imageMode.value = 'upload';
    formErrors.value = {};
}

function openCreate() { resetForm(); showCreate.value = true; }

function openEdit(product: Product) {
    selectedProduct.value = product;
    form.value = { name: product.name, price: product.price, stock: String(product.stock) };
    imageFile.value = null;
    formErrors.value = {};

    const src = getImageSrc(product.image_path);
    imagePreview.value = src;

    // Detect if existing image is a URL or a stored file
    if (product.image_path && (product.image_path.startsWith('http://') || product.image_path.startsWith('https://'))) {
        imageMode.value = 'url';
        imageUrlInput.value = product.image_path;
    } else {
        imageMode.value = 'upload';
        imageUrlInput.value = '';
    }
    showEdit.value = true;
}

function openDelete(product: Product) { selectedProduct.value = product; showDelete.value = true; }

async function fetchProducts(pg = 1) {
    loading.value = true;
    try {
        const res = await api.get<Pagination<Product>>('/api/admin/products', { page: pg });
        products.value = res.data;
        const { data: _, ...meta } = res;
        pagination.value = meta;
        currentPage.value = meta.current_page;
    } catch { /* silent */ } finally { loading.value = false; }
}

async function createProduct() {
    submitting.value = true; formErrors.value = {};
    try {
        const fd = new FormData();
        fd.append('name', form.value.name);
        fd.append('price', form.value.price);
        fd.append('stock', form.value.stock);
        if (imageMode.value === 'upload' && imageFile.value) {
            fd.append('image', imageFile.value);
        } else if (imageMode.value === 'url' && imageUrlInput.value.trim()) {
            fd.append('image_url', imageUrlInput.value.trim());
        }

        const res = await api.post<Product>('/api/admin/products', fd);
        products.value.unshift(res);
        showCreate.value = false;
    } catch (err) { formErrors.value = extractErrors(err); }
    finally { submitting.value = false; }
}

async function updateProduct() {
    if (!selectedProduct.value) return;
    submitting.value = true; formErrors.value = {};
    try {
        const fd = new FormData();
        fd.append('name', form.value.name);
        fd.append('price', form.value.price);
        fd.append('stock', form.value.stock);
        fd.append('_method', 'PUT');
        if (imageMode.value === 'upload' && imageFile.value) {
            fd.append('image', imageFile.value);
        } else if (imageMode.value === 'url' && imageUrlInput.value.trim()) {
            fd.append('image_url', imageUrlInput.value.trim());
        }

        const res = await api.post<Product>(`/api/admin/products/${selectedProduct.value.id}`, fd);
        const idx = products.value.findIndex(p => p.id === selectedProduct.value!.id);
        if (idx !== -1) products.value[idx] = res;
        showEdit.value = false;
    } catch (err) { formErrors.value = extractErrors(err); }
    finally { submitting.value = false; }
}

async function deleteProduct() {
    if (!selectedProduct.value) return;
    submitting.value = true;
    try {
        await api.delete(`/api/admin/products/${selectedProduct.value.id}`);
        products.value = products.value.filter(p => p.id !== selectedProduct.value!.id);
        showDelete.value = false;
    } catch { /* silent */ }
    finally { submitting.value = false; }
}

onMounted(() => fetchProducts());
</script>

<template>
    <Head title="Product Management" />

    <div class="flex flex-col gap-6 p-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">Product Management</h1>
                <p class="text-sm text-muted-foreground">Manage all product listings.</p>
            </div>
            <Button class="gap-2" @click="openCreate">
                <PackagePlus class="size-4" />
                Add Product
            </Button>
        </div>

        <!-- Refresh -->
        <div class="flex justify-end">
            <Button variant="outline" size="icon" @click="fetchProducts(currentPage)">
                <RefreshCw class="size-4" :class="{ 'animate-spin': loading }" />
            </Button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-24">
            <Loader2 class="size-8 animate-spin text-muted-foreground" />
        </div>

        <!-- Empty state -->
        <div v-else-if="products.length === 0" class="flex flex-col items-center justify-center py-24 text-center gap-3">
            <Package class="size-12 text-muted-foreground" />
            <p class="font-medium text-foreground">No products yet</p>
            <p class="text-sm text-muted-foreground">Click "Add Product" to create your first listing.</p>
        </div>

        <!-- Product Grid -->
        <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <Card
                v-for="product in products"
                :key="product.id"
                class="group overflow-hidden border-border/60 hover:shadow-md transition-all"
            >
                <!-- Image -->
                <div class="aspect-[4/3] overflow-hidden bg-muted relative">
                    <img
                        v-if="getImageSrc(product.image_path)"
                        :src="getImageSrc(product.image_path)!"
                        :alt="product.name"
                        class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                    />
                    <div v-else class="flex h-full w-full items-center justify-center">
                        <Package class="size-12 text-muted-foreground/30" />
                    </div>

                    <!-- Stock badge -->
                    <div class="absolute top-2 left-2">
                        <Badge
                            class="text-xs border-0"
                            :class="product.stock === 0
                                ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                                : product.stock <= 5
                                    ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'
                                    : 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'"
                        >
                            {{ product.stock === 0 ? 'Out of Stock' : `${product.stock} in stock` }}
                        </Badge>
                    </div>

                    <!-- Action Overlay -->
                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                        <Button size="sm" variant="secondary" class="gap-1.5 h-8" @click="openEdit(product)">
                            <Pencil class="size-3.5" /> Edit
                        </Button>
                        <Button size="sm" variant="destructive" class="gap-1.5 h-8" @click="openDelete(product)">
                            <Trash2 class="size-3.5" /> Delete
                        </Button>
                    </div>
                </div>

                <CardContent class="p-4">
                    <h3 class="font-semibold text-foreground line-clamp-1">{{ product.name }}</h3>
                    <p class="text-lg font-bold text-primary mt-1">{{ formatPrice(product.price) }}</p>
                </CardContent>
            </Card>
        </div>

        <!-- Pagination -->
        <div v-if="pagination && pagination.last_page > 1" class="flex items-center justify-between text-sm text-muted-foreground">
            <span>{{ pagination.total }} products total</span>
            <div class="flex gap-2">
                <Button variant="outline" size="sm" :disabled="currentPage <= 1" @click="fetchProducts(currentPage - 1)">Previous</Button>
                <span class="flex items-center px-2">{{ currentPage }} / {{ pagination.last_page }}</span>
                <Button variant="outline" size="sm" :disabled="currentPage >= pagination.last_page" @click="fetchProducts(currentPage + 1)">Next</Button>
            </div>
        </div>
    </div>

    <!-- ── CREATE DIALOG ── -->
    <Dialog v-model:open="showCreate">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Add New Product</DialogTitle>
                <DialogDescription>Fill in the details for the new product listing.</DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="createProduct">
                <!-- Image Mode Toggle -->
                <div class="space-y-2">
                    <Label>Product Image</Label>
                    <div class="flex rounded-lg border border-border overflow-hidden">
                        <button
                            type="button"
                            class="flex-1 flex items-center justify-center gap-1.5 py-2 text-sm font-medium transition-colors"
                            :class="imageMode === 'upload' ? 'bg-primary text-primary-foreground' : 'bg-background text-muted-foreground hover:bg-muted'"
                            @click="setImageMode('upload')"
                        >
                            <Upload class="size-3.5" /> Upload File
                        </button>
                        <button
                            type="button"
                            class="flex-1 flex items-center justify-center gap-1.5 py-2 text-sm font-medium transition-colors"
                            :class="imageMode === 'url' ? 'bg-primary text-primary-foreground' : 'bg-background text-muted-foreground hover:bg-muted'"
                            @click="setImageMode('url')"
                        >
                            <LinkIcon class="size-3.5" /> Image URL
                        </button>
                    </div>

                    <!-- File Upload -->
                    <div v-if="imageMode === 'upload'">
                        <div
                            class="relative flex h-36 w-full cursor-pointer items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-border hover:border-primary/50 transition-colors"
                            @click="fileInput?.click()"
                        >
                            <img v-if="imagePreview" :src="imagePreview" class="h-full w-full object-cover" alt="Preview" />
                            <div v-else class="flex flex-col items-center gap-2 text-muted-foreground">
                                <ImagePlus class="size-7" />
                                <span class="text-sm">Click to upload image</span>
                            </div>
                        </div>
                        <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onImageChange" />
                    </div>

                    <!-- URL input -->
                    <div v-else class="space-y-2">
                        <Input v-model="imageUrlInput" placeholder="https://example.com/image.jpg" @input="onUrlInput" />
                        <div v-if="imagePreview" class="h-28 overflow-hidden rounded-lg border border-border">
                            <img :src="imagePreview" class="h-full w-full object-cover" alt="URL Preview" />
                        </div>
                    </div>
                    <InputError :message="formErrors.image ?? formErrors.image_url" />
                </div>

                <div class="space-y-1.5">
                    <Label for="c-name">Product Name</Label>
                    <Input id="c-name" v-model="form.name" placeholder="e.g. Premium Headphones" required />
                    <InputError :message="formErrors.name" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <Label for="c-price">Price (₱)</Label>
                        <Input id="c-price" v-model="form.price" type="number" step="0.01" min="0" placeholder="0.00" required />
                        <InputError :message="formErrors.price" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="c-stock">Stock</Label>
                        <Input id="c-stock" v-model="form.stock" type="number" min="0" placeholder="0" required />
                        <InputError :message="formErrors.stock" />
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showCreate = false">Cancel</Button>
                    <Button type="submit" :disabled="submitting">
                        <Spinner v-if="submitting" />
                        Add Product
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- ── EDIT DIALOG ── -->
    <Dialog v-model:open="showEdit">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Edit Product</DialogTitle>
                <DialogDescription>Update the product's information.</DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="updateProduct">
                <!-- Image Mode Toggle -->
                <div class="space-y-2">
                    <Label>Product Image</Label>
                    <div class="flex rounded-lg border border-border overflow-hidden">
                        <button
                            type="button"
                            class="flex-1 flex items-center justify-center gap-1.5 py-2 text-sm font-medium transition-colors"
                            :class="imageMode === 'upload' ? 'bg-primary text-primary-foreground' : 'bg-background text-muted-foreground hover:bg-muted'"
                            @click="setImageMode('upload')"
                        >
                            <Upload class="size-3.5" /> Upload File
                        </button>
                        <button
                            type="button"
                            class="flex-1 flex items-center justify-center gap-1.5 py-2 text-sm font-medium transition-colors"
                            :class="imageMode === 'url' ? 'bg-primary text-primary-foreground' : 'bg-background text-muted-foreground hover:bg-muted'"
                            @click="setImageMode('url')"
                        >
                            <LinkIcon class="size-3.5" /> Image URL
                        </button>
                    </div>

                    <!-- File upload -->
                    <div v-if="imageMode === 'upload'">
                        <div
                            class="relative flex h-36 w-full cursor-pointer items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-border hover:border-primary/50 transition-colors"
                            @click="fileInput?.click()"
                        >
                            <img v-if="imagePreview" :src="imagePreview" class="h-full w-full object-cover" alt="Preview" />
                            <div v-else class="flex flex-col items-center gap-2 text-muted-foreground">
                                <ImagePlus class="size-7" />
                                <span class="text-sm">Click to replace image</span>
                            </div>
                        </div>
                        <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onImageChange" />
                        <p class="text-xs text-muted-foreground mt-1">Leave empty to keep existing image.</p>
                    </div>

                    <!-- URL input -->
                    <div v-else class="space-y-2">
                        <Input v-model="imageUrlInput" placeholder="https://example.com/image.jpg" @input="onUrlInput" />
                        <div v-if="imagePreview" class="h-28 overflow-hidden rounded-lg border border-border">
                            <img :src="imagePreview" class="h-full w-full object-cover" alt="URL Preview" />
                        </div>
                    </div>
                    <InputError :message="formErrors.image ?? formErrors.image_url" />
                </div>

                <div class="space-y-1.5">
                    <Label for="e-name">Product Name</Label>
                    <Input id="e-name" v-model="form.name" required />
                    <InputError :message="formErrors.name" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <Label for="e-price">Price (₱)</Label>
                        <Input id="e-price" v-model="form.price" type="number" step="0.01" min="0" required />
                        <InputError :message="formErrors.price" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="e-stock">Stock</Label>
                        <Input id="e-stock" v-model="form.stock" type="number" min="0" required />
                        <InputError :message="formErrors.stock" />
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

    <!-- ── DELETE CONFIRM ── -->
    <Dialog v-model:open="showDelete">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Delete Product</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete <strong>{{ selectedProduct?.name }}</strong>?
                    This will permanently remove the product listing.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="showDelete = false">Cancel</Button>
                <Button variant="destructive" :disabled="submitting" @click="deleteProduct">
                    <Spinner v-if="submitting" />
                    Delete Product
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
