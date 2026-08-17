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
} from '@/routes/catalogos/alimentos';
import type { Alimento, OpcionEnum } from '@/types/huella';

defineProps<{
    registros: Alimento[];
    tipos: OpcionEnum[];
    gamas: OpcionEnum[];
    especies: OpcionEnum[];
    etapas: OpcionEnum[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Catálogos', href: catalogos() },
            { title: 'Alimentos', href: index() },
        ],
    },
});
</script>

<template>
    <Head title="Alimentos" />

    <ListaCatalogo
        titulo="Alimentos"
        singular="alimento"
        :registros="registros"
        :url-alta="store().url"
        :url-edicion="(r) => update(r.id).url"
        :url-baja="(r) => destroy(r.id).url"
        :url-duplicar="(r) => duplicar(r.id).url"
    >
        <template #item="{ registro }">
            <p class="truncate font-medium">{{ registro.etiqueta }}</p>
            <p class="truncate text-sm text-muted-foreground">
                {{ registro.tipo_etiqueta }}
                <template v-if="registro.gama_etiqueta">
                    · {{ registro.gama_etiqueta }}</template
                >
            </p>

            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                <Badge variant="outline" class="font-normal">
                    {{ registro.especie_etiqueta }}
                </Badge>
                <span class="text-xs text-muted-foreground">
                    {{ registro.etapa_etiqueta }}
                </span>
                <Badge
                    v-if="registro.medicado"
                    variant="secondary"
                    class="font-normal"
                >
                    Medicado
                </Badge>
            </div>
        </template>

        <template #campos="{ registro, errors }">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="marca">Marca</Label>
                    <Input
                        id="marca"
                        name="marca"
                        maxlength="120"
                        autocomplete="off"
                        placeholder="Royal Canin"
                        :default-value="registro?.marca ?? ''"
                    />
                    <InputError :message="errors.marca" />
                </div>

                <div class="grid gap-2">
                    <Label for="nombre">Nombre *</Label>
                    <Input
                        id="nombre"
                        name="nombre"
                        required
                        maxlength="140"
                        autocomplete="off"
                        placeholder="Medium Adult"
                        :default-value="registro?.nombre"
                    />
                    <InputError :message="errors.nombre" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="tipo">Tipo *</Label>
                <SelectNativo
                    name="tipo"
                    :opciones="tipos"
                    :default-value="registro?.tipo ?? 'balanceado_seco'"
                />
                <InputError :message="errors.tipo" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
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
                    <Label for="etapa">Etapa de vida *</Label>
                    <SelectNativo
                        name="etapa"
                        :opciones="etapas"
                        :default-value="registro?.etapa ?? 'adulto'"
                    />
                    <InputError :message="errors.etapa" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="gama">Gama</Label>
                    <SelectNativo
                        name="gama"
                        :opciones="gamas"
                        placeholder="Sin especificar"
                        :default-value="registro?.gama ?? ''"
                    />
                    <InputError :message="errors.gama" />
                </div>

                <div class="grid gap-2">
                    <Label for="presentacion">Presentación</Label>
                    <Input
                        id="presentacion"
                        name="presentacion"
                        maxlength="80"
                        placeholder="Bolsa 15 kg"
                        :default-value="registro?.presentacion ?? ''"
                    />
                    <InputError :message="errors.presentacion" />
                </div>
            </div>

            <CampoCheck
                name="medicado"
                label="Es un alimento medicado"
                :default-value="registro?.medicado ?? false"
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
