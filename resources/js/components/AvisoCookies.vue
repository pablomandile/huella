<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Cookie } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useAvisoCookies } from '@/composables/useAvisoCookies';
import { cookies } from '@/routes';

/*
 * Aviso de cookies: informativo, con un solo botón.
 *
 * Todo lo que Huella guarda en el navegador es necesario para que funcione
 * —sesión, protección de formularios, preferencias, modo sin conexión—, así
 * que no hay nada que rechazar. Un «Rechazar» sería de adorno: lo único que
 * podría apagar es el login. El detalle está en la página de cookies.
 *
 * `apilado` es para la zona inferior del celular adentro de la app: va arriba
 * del banner de instalación y de la barra, en vez de pelearles el bottom-0.
 * `flotante` es para todo lo demás: fijo abajo en el celular, y desde `md`
 * una tarjeta angosta en la esquina inferior izquierda (la derecha es de los
 * toasts).
 */

const { variante = 'flotante' } = defineProps<{
    variante?: 'flotante' | 'apilado';
}>();

const { visible, cerrar } = useAvisoCookies();
</script>

<template>
    <div
        v-if="visible"
        role="region"
        aria-label="Aviso de cookies"
        :class="
            variante === 'apilado'
                ? 'border-t border-border bg-card px-4 shadow-lg'
                : 'fixed inset-x-0 bottom-0 z-50 border-t border-border bg-card px-4 pb-[env(safe-area-inset-bottom)] shadow-lg md:inset-x-auto md:bottom-4 md:left-4 md:w-sm md:rounded-xl md:border md:pb-0'
        "
    >
        <div class="flex flex-col gap-3 py-3">
            <div class="flex gap-3">
                <Cookie
                    class="mt-0.5 size-5 shrink-0 text-primary"
                    aria-hidden="true"
                />
                <p class="text-sm text-pretty wrap-anywhere">
                    Huella usa cookies y guarda datos en tu dispositivo solo
                    para funcionar: mantener tu sesión, recordar tus
                    preferencias y dejarte usarla sin conexión. Nada de
                    publicidad ni seguimiento.
                </p>
            </div>

            <div class="flex items-center justify-end gap-2">
                <Link
                    :href="cookies()"
                    class="inline-flex touch-target items-center px-2 text-sm text-muted-foreground underline-offset-4 transition-colors hover:text-foreground hover:underline"
                >
                    Más información
                </Link>

                <!-- size="sm" da 32px de alto: en el celular hacen falta 44. -->
                <Button size="sm" class="touch-target" @click="cerrar">
                    Entendido
                </Button>
            </div>
        </div>
    </div>
</template>
