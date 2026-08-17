<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { BookMarked, LogOut, Settings } from '@lucide/vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import InstalarApp from '@/components/InstalarApp.vue';
import UserInfo from '@/components/UserInfo.vue';
import { logout } from '@/routes';
import { index as catalogos } from '@/routes/catalogos';
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
            Catálogos no entra en la barra inferior del celular (más de cinco
            destinos cortan las etiquetas), así que su acceso móvil es este:
            son datos de referencia, y acá es donde uno los busca.
        -->
        <DropdownMenuItem :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="catalogos()"
                prefetch
            >
                <BookMarked class="mr-2 h-4 w-4" />
                Catálogos
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
