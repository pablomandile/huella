<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, PawPrint, Plus } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { update as actualizarActiva } from '@/routes/mascota-activa';
import { create } from '@/routes/mascotas';
import type { MascotaLigera } from '@/types/huella';

/*
 * Selector de la mascota activa, persistida en sesión. Con una sola mascota no
 * hay nada que elegir y no se muestra; con varias, define cuál ven el dashboard
 * y las cargas rápidas.
 */

const page = usePage();

const mascotas = computed(() => (page.props.mascotas ?? []) as MascotaLigera[]);
const activaId = computed(() => page.props.mascotaActivaId as number | null);
const activa = computed(
    () =>
        mascotas.value.find((mascota) => mascota.id === activaId.value) ??
        mascotas.value[0],
);

function elegir(mascota: MascotaLigera) {
    if (mascota.id !== activaId.value) {
        router.patch(
            actualizarActiva(mascota.id).url,
            {},
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <DropdownMenu v-if="mascotas.length > 1">
        <DropdownMenuTrigger as-child>
            <!-- En el header del celular se toca con el pulgar: 44px mínimo. -->
            <Button variant="ghost" size="sm" class="touch-target gap-2">
                <span
                    class="flex size-6 items-center justify-center overflow-hidden rounded-full bg-muted"
                >
                    <img
                        v-if="activa?.foto_miniatura_url"
                        :src="activa.foto_miniatura_url"
                        :alt="`Foto de ${activa.nombre}`"
                        class="size-full object-cover"
                    />
                    <PawPrint
                        v-else
                        class="size-3.5 text-muted-foreground"
                        aria-hidden="true"
                    />
                </span>
                <span class="max-w-24 truncate">{{ activa?.nombre }}</span>
                <ChevronsUpDown
                    class="size-3.5 text-muted-foreground"
                    aria-hidden="true"
                />
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-52">
            <DropdownMenuItem
                v-for="mascota in mascotas"
                :key="mascota.id"
                class="cursor-pointer gap-2"
                @select="elegir(mascota)"
            >
                <span
                    class="flex size-6 items-center justify-center overflow-hidden rounded-full bg-muted"
                >
                    <img
                        v-if="mascota.foto_miniatura_url"
                        :src="mascota.foto_miniatura_url"
                        :alt="`Foto de ${mascota.nombre}`"
                        class="size-full object-cover"
                    />
                    <PawPrint
                        v-else
                        class="size-3.5 text-muted-foreground"
                        aria-hidden="true"
                    />
                </span>
                <span class="flex-1 truncate">{{ mascota.nombre }}</span>
                <Check
                    v-if="mascota.id === activaId"
                    class="size-4"
                    aria-hidden="true"
                />
            </DropdownMenuItem>

            <DropdownMenuSeparator />

            <DropdownMenuItem :as-child="true">
                <Link class="w-full cursor-pointer gap-2" :href="create()">
                    <Plus class="size-4" aria-hidden="true" />
                    Nueva mascota
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
