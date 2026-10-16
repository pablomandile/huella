<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { destinosBarraInferior } from '@/lib/navegacion';
import { toUrl } from '@/lib/utils';

const { isCurrentOrParentUrl } = useCurrentUrl();
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
                    class="flex touch-target flex-col items-center justify-center gap-1 px-0.5 py-2 text-[calc(0.625rem*var(--font-scale))] transition-colors min-[360px]:text-[calc(0.6875rem*var(--font-scale))] min-[390px]:text-xs"
                    :class="
                        isCurrentOrParentUrl(destino.href)
                            ? 'text-sidebar-primary'
                            : 'text-sidebar-foreground/70'
                    "
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
                        horizontal queda mínimo. Antes empujaba a «Perfil» fuera
                        de la pantalla en 320 y se pisaba con sus vecinos hasta
                        en 390. `min-w-0` deja que el ítem se achique.
                    -->
                    <span class="max-w-full">{{ destino.title }}</span>
                </Link>
            </li>
        </ul>
    </nav>
</template>
