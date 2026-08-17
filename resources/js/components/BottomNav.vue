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
                class="flex-1"
            >
                <Link
                    :href="destino.href"
                    class="flex touch-target flex-col items-center justify-center gap-1 px-2 py-2 text-xs transition-colors"
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
                    <span>{{ destino.title }}</span>
                </Link>
            </li>
        </ul>
    </nav>
</template>
