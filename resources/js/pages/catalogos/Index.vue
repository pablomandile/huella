<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Apple,
    Building2,
    ChevronRight,
    Pill,
    Stethoscope,
    Syringe,
} from '@lucide/vue';
import { Card, CardContent } from '@/components/ui/card';
import { index as alimentos } from '@/routes/catalogos/alimentos';
import { index as catalogos } from '@/routes/catalogos';
import { index as medicamentos } from '@/routes/catalogos/medicamentos';
import { index as vacunas } from '@/routes/catalogos/vacunas';
import { index as veterinarias } from '@/routes/catalogos/veterinarias';
import { index as veterinarios } from '@/routes/catalogos/veterinarios';

const props = defineProps<{
    totales: {
        veterinarias: number;
        veterinarios: number;
        medicamentos: number;
        vacunas: number;
        alimentos: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Catálogos', href: catalogos() }],
    },
});

const secciones = [
    {
        icon: Building2,
        titulo: 'Veterinarias',
        texto: 'Dónde la atienden, con teléfono y horarios a mano.',
        href: veterinarias(),
        total: props.totales.veterinarias,
    },
    {
        icon: Stethoscope,
        titulo: 'Veterinarios',
        texto: 'Los profesionales que la atienden y dónde trabajan.',
        href: veterinarios(),
        total: props.totales.veterinarios,
    },
    {
        icon: Pill,
        titulo: 'Medicamentos',
        texto: 'Antiparasitarios, antibióticos y todo lo que se receta.',
        href: medicamentos(),
        total: props.totales.medicamentos,
    },
    {
        icon: Syringe,
        titulo: 'Vacunas',
        texto: 'Planes por especie, con el refuerzo sugerido.',
        href: vacunas(),
        total: props.totales.vacunas,
    },
    {
        icon: Apple,
        titulo: 'Alimentos',
        texto: 'Las marcas que se consiguen acá, y las tuyas.',
        href: alimentos(),
        total: props.totales.alimentos,
    },
];
</script>

<template>
    <Head title="Catálogos" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
        <div>
            <h1 class="text-xl font-semibold">Catálogos</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Lo que se carga una vez y después se elige de una lista. Ya
                vienen precargados los que se consiguen en Argentina; podés
                sumar los tuyos.
            </p>
        </div>

        <div class="flex flex-col gap-2">
            <Link
                v-for="seccion in secciones"
                :key="seccion.titulo"
                :href="seccion.href"
                class="group rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <Card class="py-0 transition-shadow group-hover:shadow-md">
                    <CardContent class="flex items-center gap-4 p-4">
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-accent"
                        >
                            <component
                                :is="seccion.icon"
                                class="size-5 text-accent-foreground"
                                aria-hidden="true"
                            />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="font-medium">
                                {{ seccion.titulo }}
                                <span
                                    class="ml-1 text-sm font-normal text-muted-foreground"
                                >
                                    {{ seccion.total }}
                                </span>
                            </p>
                            <p class="truncate text-sm text-muted-foreground">
                                {{ seccion.texto }}
                            </p>
                        </div>

                        <ChevronRight
                            class="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </CardContent>
                </Card>
            </Link>
        </div>
    </div>
</template>
