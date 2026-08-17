<script setup lang="ts">
import { Download, X } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import InstalarAppInstructivo from '@/components/InstalarAppInstructivo.vue';
import { Button } from '@/components/ui/button';
import { usePwaInstall } from '@/composables/usePwaInstall';

/*
 * Banner de instalación.
 *
 * En el celular el menú de usuario queda detrás del sheet de la sidebar: son
 * tres toques hasta "Instalar app". Este banner es el acceso directo, y por eso
 * aparece solo en pantallas chicas.
 *
 * No se muestra en la primera visita: alguien que todavía no sabe qué es Huella
 * no la va a instalar. Y si lo descarta, no vuelve a aparecer por 30 días.
 */

const CLAVE_VISITAS = 'huella:visitas';
const CLAVE_DESCARTE = 'huella:banner-instalar-descartado';
const DIAS_DE_SILENCIO = 30;
const VISITAS_MINIMAS = 2;

const { sePuedeInstalar, esIos, instalar } = usePwaInstall();

const habilitado = ref(false);
const mostrarInstructivo = ref(false);

const visible = computed(() => habilitado.value && sePuedeInstalar.value);

function fueDescartadoHacePoco(): boolean {
    const marca = Number(localStorage.getItem(CLAVE_DESCARTE) ?? 0);

    if (!marca) {
        return false;
    }

    return (Date.now() - marca) / 86_400_000 < DIAS_DE_SILENCIO;
}

onMounted(() => {
    try {
        const visitas = Number(localStorage.getItem(CLAVE_VISITAS) ?? 0) + 1;

        localStorage.setItem(CLAVE_VISITAS, String(visitas));
        habilitado.value =
            visitas >= VISITAS_MINIMAS && !fueDescartadoHacePoco();
    } catch {
        // Modo privado sin localStorage: mejor no mostrar nada que romper.
        habilitado.value = false;
    }
});

function descartar() {
    habilitado.value = false;

    try {
        localStorage.setItem(CLAVE_DESCARTE, String(Date.now()));
    } catch {
        // Sin localStorage el descarte dura lo que dure la sesión.
    }
}

async function alInstalar() {
    if ((await instalar()) === 'manual') {
        mostrarInstructivo.value = true;

        return;
    }

    descartar();
}
</script>

<template>
    <div
        v-if="visible"
        role="region"
        aria-label="Instalar Huella"
        class="border-t border-border bg-card px-4 shadow-lg"
    >
        <div class="flex items-center gap-3 py-3">
            <div
                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground"
            >
                <Download class="size-5" aria-hidden="true" />
            </div>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium">Instalá Huella</p>
                <p class="truncate text-xs text-muted-foreground">
                    Se abre más rápido y queda en tu pantalla de inicio.
                </p>
            </div>

            <!-- size="sm" da 32px de alto: el banner es de celular y necesita 44. -->
            <Button size="sm" class="touch-target" @click="alInstalar">
                Instalar
            </Button>

            <button
                type="button"
                class="-mr-2 flex touch-target items-center justify-center rounded-md text-muted-foreground"
                aria-label="Ahora no"
                @click="descartar"
            >
                <X class="size-4" aria-hidden="true" />
            </button>
        </div>
    </div>

    <InstalarAppInstructivo
        v-model:abierto="mostrarInstructivo"
        :es-ios="esIos"
    />
</template>
