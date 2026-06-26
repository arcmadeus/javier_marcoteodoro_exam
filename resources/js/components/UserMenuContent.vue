<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { LogOut } from '@lucide/vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { logout } from '@/routes';
import type { User as UserType } from '@/types';

defineProps<{
    user: UserType | null;
}>();

function handleLogout() {
    router.post(logout.url());
}
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem @click="handleLogout" class="cursor-pointer text-red-600 dark:text-red-400 focus:text-red-600 focus:bg-red-50 dark:focus:bg-red-950">
            <LogOut class="mr-2 size-4" />
            Log out
        </DropdownMenuItem>
    </DropdownMenuGroup>
</template>