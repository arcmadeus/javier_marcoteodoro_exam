<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ShoppingCart, Package, ClipboardList, LogIn, UserPlus, LogOut, Menu, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Toaster } from '@/components/ui/sonner';
import { logout, login, register } from '@/routes';
import { index as storefrontIndex } from '@/routes/storefront';
import type { User } from '@/types';

const page = usePage();
const user = computed(() => page.props.auth?.user as User | null);
const isLoggedIn = computed(() => !!user.value);
const mobileOpen = ref(false);

// Cart count from shared props (if available) or default to 0
const cartCount = computed(() => (page.props as Record<string, unknown>).cartCount as number ?? 0);

function handleLogout() {
    router.post(logout.url());
}
</script>

<template>
    <div class="min-h-screen bg-background flex flex-col">
        <!-- Top Navigation Bar -->
        <header class="sticky top-0 z-50 w-full border-b border-border/50 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <!-- Logo / Brand -->
                    <Link :href="storefrontIndex()" class="flex items-center gap-2 group">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-sm transition-transform group-hover:scale-105">
                            <Package class="size-5" />
                        </div>
                        <span class="text-lg font-bold tracking-tight text-foreground hidden sm:block">ShopLite</span>
                    </Link>

                    <!-- Desktop Nav Links -->
                    <nav class="hidden md:flex items-center gap-1">
                        <Link
                            :href="storefrontIndex()"
                            class="flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                        >
                            <Package class="size-4" />
                            Shop
                        </Link>
                        <Link
                            v-if="isLoggedIn"
                            href="/my-orders"
                            class="flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                        >
                            <ClipboardList class="size-4" />
                            My Orders
                        </Link>
                    </nav>

                    <!-- Right Actions -->
                    <div class="flex items-center gap-2">
                        <!-- Cart Icon (only for logged-in guests) -->
                        <Link
                            v-if="isLoggedIn"
                            href="/cart"
                            class="relative flex h-9 w-9 items-center justify-center rounded-md border border-input bg-background text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                        >
                            <ShoppingCart class="size-4" />
                            <span
                                v-if="cartCount > 0"
                                class="absolute -right-1.5 -top-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground"
                            >{{ cartCount }}</span>
                        </Link>

                        <!-- Auth Buttons -->
                        <template v-if="!isLoggedIn">
                            <Link :href="login()">
                                <Button variant="ghost" size="sm" class="hidden sm:inline-flex gap-1.5">
                                    <LogIn class="size-4" />
                                    Log in
                                </Button>
                            </Link>
                            <Link :href="register()">
                                <Button size="sm" class="gap-1.5">
                                    <UserPlus class="size-4" />
                                    Register
                                </Button>
                            </Link>
                        </template>

                        <!-- Logged-in user -->
                        <template v-else>
                            <div class="hidden sm:flex items-center gap-2 text-sm text-muted-foreground border-l pl-3 ml-1">
                                <span class="font-medium text-foreground">{{ user?.full_name }}</span>
                            </div>
                            <Button variant="outline" size="sm" class="gap-1.5 text-red-600 border-red-200 hover:bg-red-50 hover:text-red-700 dark:border-red-800 dark:hover:bg-red-950 dark:text-red-400" @click="handleLogout">
                                <LogOut class="size-4" />
                                <span class="hidden sm:inline">Logout</span>
                            </Button>
                        </template>

                        <!-- Mobile Menu Toggle -->
                        <button
                            class="md:hidden flex h-9 w-9 items-center justify-center rounded-md border border-input bg-background text-muted-foreground hover:bg-accent"
                            @click="mobileOpen = !mobileOpen"
                        >
                            <X v-if="mobileOpen" class="size-4" />
                            <Menu v-else class="size-4" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Drawer -->
            <div v-if="mobileOpen" class="md:hidden border-t border-border bg-background px-4 pb-4 pt-2 space-y-1">
                <Link
                    :href="storefrontIndex()"
                    class="flex items-center gap-2 rounded-md px-3 py-2.5 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                    @click="mobileOpen = false"
                >
                    <Package class="size-4" /> Shop
                </Link>
                <Link
                    v-if="isLoggedIn"
                    href="/my-orders"
                    class="flex items-center gap-2 rounded-md px-3 py-2.5 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                    @click="mobileOpen = false"
                >
                    <ClipboardList class="size-4" /> My Orders
                </Link>
                <Link
                    v-if="isLoggedIn"
                    href="/cart"
                    class="flex items-center gap-2 rounded-md px-3 py-2.5 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                    @click="mobileOpen = false"
                >
                    <ShoppingCart class="size-4" /> Cart
                </Link>
                <template v-if="!isLoggedIn">
                    <Link :href="login()" @click="mobileOpen = false"
                        class="flex items-center gap-2 rounded-md px-3 py-2.5 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-accent-foreground">
                        <LogIn class="size-4" /> Log in
                    </Link>
                    <Link :href="register()" @click="mobileOpen = false"
                        class="flex items-center gap-2 rounded-md px-3 py-2.5 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-accent-foreground">
                        <UserPlus class="size-4" /> Register
                    </Link>
                </template>
                <button v-else @click="handleLogout"
                    class="flex w-full items-center gap-2 rounded-md px-3 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-950">
                    <LogOut class="size-4" /> Logout
                </button>
            </div>
        </header>

        <!-- Main content -->
        <main class="flex-1">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="border-t border-border/50 py-6 mt-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-sm text-muted-foreground">
                © {{ new Date().getFullYear() }} ShopLite. All rights reserved.
            </div>
        </footer>

        <Toaster />
    </div>
</template>
