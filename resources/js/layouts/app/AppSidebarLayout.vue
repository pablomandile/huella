<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import AvisoSinConexion from '@/components/AvisoSinConexion.vue';
import BannerInstalar from '@/components/BannerInstalar.vue';
import BottomNav from '@/components/BottomNav.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AvisoSinConexion />

    <!--
        Con teclado, llegar al contenido significaba tabular por toda la sidebar
        en cada pantalla. Se ve solo al enfocarlo, y por eso va primero en el DOM.
    -->
    <a
        href="#contenido"
        class="sr-only focus-visible:not-sr-only focus-visible:fixed focus-visible:top-3 focus-visible:left-3 focus-visible:z-100 focus-visible:rounded-md focus-visible:bg-primary focus-visible:px-4 focus-visible:py-2 focus-visible:text-primary-foreground focus-visible:ring-ring focus-visible:ring-2"
    >
        Saltar al contenido
    </a>

    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent id="contenido" variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <!--
                pb-20 deja lugar para la barra inferior del celular, que es fija.
                Desde `md` la barra desaparece y el padding sobra, así que se anula.
            -->
            <div class="pb-20 md:pb-0">
                <slot />
            </div>
        </AppContent>

        <!--
            Zona inferior del celular: el banner de instalación se apila arriba
            de la barra de navegación en vez de pelearle el bottom-0. Desde `md`
            desaparece entera y manda la sidebar.
        -->
        <div class="fixed inset-x-0 bottom-0 z-50 md:hidden">
            <BannerInstalar />
            <BottomNav />
        </div>

        <Toaster />
    </AppShell>
</template>
