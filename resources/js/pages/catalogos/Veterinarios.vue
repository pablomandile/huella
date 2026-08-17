<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Building2, Phone } from '@lucide/vue';
import ComboboxCatalogo from '@/components/ComboboxCatalogo.vue';
import InputError from '@/components/InputError.vue';
import ListaCatalogo from '@/components/ListaCatalogo.vue';
import TextareaNativo from '@/components/TextareaNativo.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as catalogos } from '@/routes/catalogos';
import { store as storeVeterinaria } from '@/routes/catalogos/veterinarias';
import { destroy, index, store, update } from '@/routes/catalogos/veterinarios';
import type { Veterinaria, Veterinario } from '@/types/huella';

defineProps<{
    registros: Veterinario[];
    veterinarias: Veterinaria[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Catálogos', href: catalogos() },
            { title: 'Veterinarios', href: index() },
        ],
    },
});
</script>

<template>
    <Head title="Veterinarios" />

    <ListaCatalogo
        titulo="Veterinarios"
        singular="veterinario"
        :registros="registros"
        :url-alta="store().url"
        :url-edicion="(r) => update(r.id).url"
        :url-baja="(r) => destroy(r.id).url"
    >
        <template #item="{ registro }">
            <p class="truncate font-medium">{{ registro.nombre }}</p>
            <p
                v-if="registro.especialidad || registro.matricula"
                class="truncate text-sm text-muted-foreground"
            >
                {{
                    [registro.especialidad, registro.matricula]
                        .filter(Boolean)
                        .join(' · ')
                }}
            </p>

            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                <span
                    v-if="registro.veterinaria_nombre"
                    class="inline-flex items-center gap-1 text-xs text-muted-foreground"
                >
                    <Building2 class="size-3.5" aria-hidden="true" />
                    {{ registro.veterinaria_nombre }}
                </span>
                <a
                    v-if="registro.telefono"
                    :href="`tel:${registro.telefono}`"
                    class="inline-flex items-center gap-1 text-sm text-primary hover:underline"
                    @click.stop
                >
                    <Phone class="size-3.5" aria-hidden="true" />
                    {{ registro.telefono }}
                </a>
            </div>
        </template>

        <template #campos="{ registro, errors }">
            <div class="grid gap-2">
                <Label for="nombre">Nombre *</Label>
                <Input
                    id="nombre"
                    name="nombre"
                    required
                    maxlength="140"
                    autocomplete="off"
                    placeholder="Laura Giménez"
                    :default-value="registro?.nombre"
                />
                <InputError :message="errors.nombre" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="matricula">Matrícula</Label>
                    <Input
                        id="matricula"
                        name="matricula"
                        maxlength="60"
                        placeholder="MP 12345"
                        :default-value="registro?.matricula ?? ''"
                    />
                    <InputError :message="errors.matricula" />
                </div>

                <div class="grid gap-2">
                    <Label for="especialidad">Especialidad</Label>
                    <Input
                        id="especialidad"
                        name="especialidad"
                        maxlength="120"
                        placeholder="Clínica general"
                        :default-value="registro?.especialidad ?? ''"
                    />
                    <InputError :message="errors.especialidad" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label>Dónde atiende</Label>
                <!--
                    Acá se resuelve el criterio de la fase: si la veterinaria no
                    está cargada, se crea desde el propio combo sin perder el
                    nombre ni la matrícula que ya se venían escribiendo.

                    Sin v-model a propósito: el combo administra su valor y se
                    reinicializa solo cuando el <Form> se remonta al cambiar de
                    registro.
                -->
                <ComboboxCatalogo
                    name="veterinaria_id"
                    etiqueta="veterinaria"
                    placeholder="Elegí una veterinaria"
                    :opciones="veterinarias"
                    :model-value="registro?.veterinaria_id ?? null"
                    :url-crear="storeVeterinaria().url"
                />
                <InputError :message="errors.veterinaria_id" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="telefono">Teléfono</Label>
                    <Input
                        id="telefono"
                        name="telefono"
                        type="tel"
                        maxlength="40"
                        :default-value="registro?.telefono ?? ''"
                    />
                    <InputError :message="errors.telefono" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        maxlength="180"
                        :default-value="registro?.email ?? ''"
                    />
                    <InputError :message="errors.email" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="notas">Notas</Label>
                <TextareaNativo
                    name="notas"
                    :rows="2"
                    :default-value="registro?.notas"
                />
                <InputError :message="errors.notas" />
            </div>

            <input type="hidden" name="activo" value="1" />
        </template>
    </ListaCatalogo>
</template>
