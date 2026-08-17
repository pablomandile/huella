<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CampoCheck from '@/components/CampoCheck.vue';
import InputError from '@/components/InputError.vue';
import ListaCatalogo from '@/components/ListaCatalogo.vue';
import SelectNativo from '@/components/SelectNativo.vue';
import TextareaNativo from '@/components/TextareaNativo.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as catalogos } from '@/routes/catalogos';
import {
    destroy,
    duplicar,
    index,
    store,
    update,
} from '@/routes/catalogos/vacunas';
import type { OpcionEnum, Vacuna } from '@/types/huella';

defineProps<{
    registros: Vacuna[];
    especies: OpcionEnum[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Catálogos', href: catalogos() },
            { title: 'Vacunas', href: index() },
        ],
    },
});
</script>

<template>
    <Head title="Vacunas" />

    <ListaCatalogo
        titulo="Vacunas"
        singular="vacuna"
        :registros="registros"
        :url-alta="store().url"
        :url-edicion="(r) => update(r.id).url"
        :url-baja="(r) => destroy(r.id).url"
        :url-duplicar="(r) => duplicar(r.id).url"
    >
        <template #item="{ registro }">
            <p class="truncate font-medium">{{ registro.nombre }}</p>
            <p
                v-if="registro.descripcion"
                class="truncate text-sm text-muted-foreground"
            >
                {{ registro.descripcion }}
            </p>

            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                <Badge variant="outline" class="font-normal">
                    {{ registro.especie_etiqueta }}
                </Badge>
                <span
                    v-if="registro.detalle"
                    class="text-xs text-muted-foreground"
                >
                    {{ registro.detalle }}
                </span>
                <Badge
                    v-if="registro.obligatoria"
                    variant="secondary"
                    class="font-normal"
                >
                    Obligatoria
                </Badge>
            </div>
        </template>

        <template #campos="{ registro, errors }">
            <div class="grid gap-2">
                <Label for="nombre">Nombre *</Label>
                <Input
                    id="nombre"
                    name="nombre"
                    required
                    maxlength="120"
                    autocomplete="off"
                    placeholder="Quíntuple"
                    :default-value="registro?.nombre"
                />
                <InputError :message="errors.nombre" />
            </div>

            <div class="grid gap-2">
                <Label for="especie">Especie *</Label>
                <SelectNativo
                    name="especie"
                    :opciones="especies"
                    :default-value="registro?.especie ?? 'perro'"
                />
                <InputError :message="errors.especie" />
            </div>

            <div class="grid gap-2">
                <Label for="meses_refuerzo">Refuerzo sugerido (meses)</Label>
                <Input
                    id="meses_refuerzo"
                    name="meses_refuerzo"
                    type="number"
                    inputmode="numeric"
                    min="1"
                    max="120"
                    placeholder="12"
                    :default-value="registro?.meses_refuerzo ?? ''"
                />
                <p class="text-xs text-muted-foreground">
                    Se usa solo para precargar la fecha de la próxima dosis al
                    aplicarla. Siempre la vas a poder cambiar.
                </p>
                <InputError :message="errors.meses_refuerzo" />
            </div>

            <CampoCheck
                name="obligatoria"
                label="Obligatoria por ley"
                :default-value="registro?.obligatoria ?? false"
            />

            <div class="grid gap-2">
                <Label for="descripcion">Contra qué protege</Label>
                <TextareaNativo
                    name="descripcion"
                    :rows="2"
                    placeholder="Moquillo, hepatitis, parvovirosis…"
                    :default-value="registro?.descripcion"
                />
                <InputError :message="errors.descripcion" />
            </div>
        </template>
    </ListaCatalogo>
</template>
