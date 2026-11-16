<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import SelectorMascota from '@/components/SelectorMascota.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="letra-con-tope flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex min-w-0 items-center gap-2">
            <!--
                En el celular el menú se abre desde la barra de abajo, al
                alcance del pulgar; acá queda el logo, que lleva al inicio.
                Desde `md` vuelve el disparador, que pliega la sidebar.
            -->
            <Link
                :href="dashboard()"
                class="-ml-1 flex touch-target shrink-0 items-center md:hidden"
            >
                <AppLogoIcon class="size-8" />
            </Link>
            <SidebarTrigger class="-ml-1 hidden shrink-0 md:inline-flex" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <SelectorMascota />
    </header>
</template>
