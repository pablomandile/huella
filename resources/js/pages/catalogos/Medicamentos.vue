<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { FileText } from '@lucide/vue';
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
} from '@/routes/catalogos/medicamentos';
import type { Medicamento, OpcionEnum } from '@/types/huella';

defineProps<{
    registros: Medicamento[];
    categorias: OpcionEnum[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Catálogos', href: catalogos() },
            { title: 'Medicamentos', href: index() },
        ],
    },
});
</script>

<template>
    <Head title="Medicamentos" />

    <ListaCatalogo
        titulo="Medicamentos"
        singular="medicamento"
        :registros="registros"
        :url-alta="store().url"
        :url-edicion="(r) => update(r.id).url"
        :url-baja="(r) => destroy(r.id).url"
        :url-duplicar="(r) => duplicar(r.id).url"
    >
        <template #item="{ registro }">
            <p class="truncate font-medium">{{ registro.nombre_comercial }}</p>
            <p
                v-if="registro.droga"
                class="truncate text-sm text-muted-foreground"
            >
                {{ registro.droga }}
            </p>

            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                <Badge variant="outline" class="font-normal">
                    {{ registro.categoria_etiqueta }}
                </Badge>
                <span
                    v-if="registro.presentacion"
                    class="text-xs text-muted-foreground"
                >
                    {{ registro.presentacion }}
                </span>
                <span
                    v-if="registro.requiere_receta"
                    class="inline-flex items-center gap-1 text-xs text-muted-foreground"
                >
                    <FileText class="size-3.5" aria-hidden="true" />
                    Con receta
                </span>
            </div>
        </template>

        <template #campos="{ registro, errors }">
            <div class="grid gap-2">
                <Label for="nombre_comercial">Nombre *</Label>
                <Input
                    id="nombre_comercial"
                    name="nombre_comercial"
                    required
                    maxlength="140"
                    autocomplete="off"
                    placeholder="Drontal Plus"
                    :default-value="registro?.nombre_comercial"
                />
                <InputError :message="errors.nombre_comercial" />
            </div>

            <div class="grid gap-2">
                <Label for="droga">Droga o principio activo</Label>
                <Input
                    id="droga"
                    name="droga"
                    maxlength="140"
                    placeholder="Praziquantel + pirantel"
                    :default-value="registro?.droga ?? ''"
                />
                <InputError :message="errors.droga" />
            </div>

            <div class="grid gap-2">
                <Label for="categoria">Categoría *</Label>
                <SelectNativo
                    name="categoria"
                    :opciones="categorias"
                    :default-value="registro?.categoria ?? 'otro'"
                />
                <InputError :message="errors.categoria" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="laboratorio">Laboratorio</Label>
                    <Input
                        id="laboratorio"
                        name="laboratorio"
                        maxlength="120"
                        :default-value="registro?.laboratorio ?? ''"
                    />
                    <InputError :message="errors.laboratorio" />
                </div>

                <div class="grid gap-2">
                    <Label for="presentacion">Presentación</Label>
                    <Input
                        id="presentacion"
                        name="presentacion"
                        maxlength="120"
                        placeholder="Comprimidos"
                        :default-value="registro?.presentacion ?? ''"
                    />
                    <InputError :message="errors.presentacion" />
                </div>
            </div>

            <CampoCheck
                name="requiere_receta"
                label="Se vende bajo receta"
                :default-value="registro?.requiere_receta ?? false"
            />

            <div class="grid gap-2">
                <Label for="notas">Notas</Label>
                <TextareaNativo
                    name="notas"
                    :rows="2"
                    :default-value="registro?.notas"
                />
                <InputError :message="errors.notas" />
            </div>
        </template>
    </ListaCatalogo>
</template>
