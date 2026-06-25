<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ClipboardList, LayoutGrid, Package, ShoppingCart, Users } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { index as storefrontIndex } from '@/routes/storefront';
import cms from '@/routes/cms';
import type { NavItem } from '@/types';

const page = usePage();
const user = computed(() => page.props.auth?.user as { role?: string } | undefined);
const isAdmin = computed(() => user.value?.role === 'admin');

const adminNavItems: NavItem[] = [
    { title: 'Dashboard', href: cms.dashboard(), icon: LayoutGrid },
    { title: 'Users', href: cms.users.index(), icon: Users },
    { title: 'Products', href: cms.products.index(), icon: Package },
    { title: 'Orders', href: cms.orders.index(), icon: ClipboardList },
];

const guestNavItems: NavItem[] = [
    { title: 'Shop', href: storefrontIndex(), icon: ShoppingCart },
];

const mainNavItems = computed(() => (isAdmin.value ? adminNavItems : guestNavItems));
const homeHref = computed(() => (isAdmin.value ? cms.dashboard() : storefrontIndex()));
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="homeHref">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>
        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>
        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>