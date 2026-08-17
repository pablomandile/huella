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
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
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
