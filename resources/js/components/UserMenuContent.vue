<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Cookie, FileText, Info, LogOut, Settings } from '@lucide/vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import InstalarApp from '@/components/InstalarApp.vue';
import UserInfo from '@/components/UserInfo.vue';
import { acerca, cookies, logout, terminos } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

const handleLogout = () => {
    router.flushAll();
};

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                Configuración
            </Link>
        </DropdownMenuItem>
        <!--
            Acerca de: qué es la app y cómo escribirle a quien la hizo. Va en el
            menú y no en la navegación principal porque se visita una vez.
        -->
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="acerca()" prefetch>
                <Info class="mr-2 h-4 w-4" />
                Acerca de
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="cookies()"
                prefetch
            >
                <Cookie class="mr-2 h-4 w-4" />
                Cookies y datos guardados
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="terminos()"
                prefetch
            >
                <FileText class="mr-2 h-4 w-4" />
                Términos y privacidad
            </Link>
        </DropdownMenuItem>
        <!--
            Entrada fija de instalación: se muestra sola mientras la app no esté
            instalada, y desaparece en vivo al instalarse. @select.prevent evita
            que el menú se cierre antes de que se dispare el prompt nativo.
        -->
        <DropdownMenuItem :as-child="true" @select.prevent>
            <InstalarApp variante="menu" />
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Cerrar sesión
        </Link>
    </DropdownMenuItem>
</template>
