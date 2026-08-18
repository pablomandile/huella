<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { onUnmounted } from 'vue';
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
    useSidebar,
} from '@/components/ui/sidebar';
import { destinosPrincipales } from '@/lib/navegacion';
import { dashboard } from '@/routes';

const { isMobile, setOpenMobile } = useSidebar();

/*
 * En el celular el menú es un sheet encima de la pantalla, y sus enlaces son
 * `<Link>` de Inertia: navegan por XHR, así que nadie recarga la página y el
 * sheet se queda abierto tapando lo que acabás de pedir.
 *
 * En escritorio no se toca: ahí la sidebar es fija y cerrarla sola sería
 * quedarse sin menú a cada paso.
 *
 * Va acá, escuchando al router, y no en cada `<Link>`, para que valga también
 * para los que se agreguen después —el del logo, los de NavMain, los del menú
 * de usuario— sin que haya que acordarse de nada.
 */
onUnmounted(
    router.on('navigate', () => {
        if (isMobile.value) {
            setOpenMobile(false);
        }
    }),
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="destinosPrincipales" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
