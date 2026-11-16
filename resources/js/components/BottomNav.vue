<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Menu } from '@lucide/vue';
import { useSidebar } from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { destinosBarraInferior } from '@/lib/navegacion';
import { toUrl } from '@/lib/utils';

const { isCurrentOrParentUrl } = useCurrentUrl();

/*
 * El menú lateral se abre desde acá y no desde un ícono en el encabezado: con
 * el celular en una mano, la esquina de arriba a la izquierda es la más lejana
 * del pulgar. La barra sigue en cinco casilleros: cuatro destinos y el menú,
 * que ocupa el lugar que antes era de Perfil (ahora adentro del menú).
 */
const { openMobile, setOpenMobile } = useSidebar();

const clasesDelCasillero =
    'flex w-full touch-target flex-col items-center justify-center gap-1 px-0.5 py-2 text-[calc(0.625rem*var(--tope-letra,1))] transition-colors min-[360px]:text-[calc(0.6875rem*var(--tope-letra,1))] min-[390px]:text-xs';
</script>

<template>
    <!--
        Barra de navegación del celular. Va dentro de la zona inferior fija del
        layout, que es la que la posiciona y la oculta desde `md`. `pb-safe`
        deja el espacio del notch en iOS.
    -->
    <nav
        aria-label="Navegación principal"
        class="border-t border-sidebar-border/70 bg-sidebar pb-safe"
    >
        <ul class="flex items-stretch justify-around">
            <li
                v-for="destino in destinosBarraInferior"
                :key="toUrl(destino.href)"
                class="min-w-0 flex-1"
            >
                <Link
                    :href="destino.href"
                    :class="[
                        clasesDelCasillero,
                        isCurrentOrParentUrl(destino.href)
                            ? 'text-sidebar-primary'
                            : 'text-sidebar-foreground/70',
                    ]"
                    :aria-current="
                        isCurrentOrParentUrl(destino.href) ? 'page' : undefined
                    "
                >
                    <component
                        v-if="destino.icon"
                        :is="destino.icon"
                        class="size-5"
                        aria-hidden="true"
                    />
                    <!--
                        Cada destino es un quinto del ancho, y «Medicación» es la
                        etiqueta más larga: medida, ocupa 60px a 11.25px, 66 a
                        12.4 y 72 a 13.5. Con 64px de casillero en 320, 72 en 360
                        y 78 en 390, la letra se escalona por ancho y el padding
                        horizontal queda mínimo. Antes empujaba al último casillero fuera
                        de la pantalla en 320 y se pisaba con sus vecinos hasta
                        en 390. `min-w-0` deja que el ítem se achique.
                    -->
                    <span class="max-w-full">{{ destino.title }}</span>
                </Link>
            </li>
            <li class="min-w-0 flex-1">
                <button
                    type="button"
                    :class="[
                        clasesDelCasillero,
                        openMobile
                            ? 'text-sidebar-primary'
                            : 'text-sidebar-foreground/70',
                    ]"
                    :aria-expanded="openMobile"
                    aria-haspopup="dialog"
                    @click="setOpenMobile(true)"
                >
                    <Menu class="size-5" aria-hidden="true" />
                    <span class="max-w-full">Menú</span>
                </button>
            </li>
        </ul>
    </nav>
</template>
