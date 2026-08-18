<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Package } from '@lucide/vue';
import { ref, shallowRef } from 'vue';
import CampoCheck from '@/components/CampoCheck.vue';
import CampoFoto from '@/components/CampoFoto.vue';
import InputError from '@/components/InputError.vue';
import ListaCatalogo from '@/components/ListaCatalogo.vue';
import SelectNativo from '@/components/SelectNativo.vue';
import TextareaNativo from '@/components/TextareaNativo.vue';
import VisorImagen from '@/components/VisorImagen.vue';
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

const visorAbierto = ref(false);
const enElVisor = shallowRef<Alimento | null>(null);

function abrirFoto(alimento: Alimento) {
    enElVisor.value = alimento;
    visorAbierto.value = true;
}

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
            <!--
                La foto del paquete va primero y chica: sirve para reconocer la
                bolsa de un vistazo, que es más rápido que leer marca y nombre.
            -->
            <div class="flex items-start gap-3">
                <button
                    v-if="registro.foto_miniatura_url"
                    type="button"
                    class="shrink-0 rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden"
                    @click="abrirFoto(registro)"
                >
                    <img
                        :src="registro.foto_miniatura_url"
                        :alt="`Ver el paquete de ${registro.etiqueta} en grande`"
                        width="48"
                        height="48"
                        loading="lazy"
                        class="size-12 rounded-md border border-border object-cover"
                    />
                </button>

                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium">{{ registro.etiqueta }}</p>
                    <p class="truncate text-sm text-muted-foreground">
                        {{ registro.tipo_etiqueta }}
                        <template v-if="registro.gama_etiqueta">
                            · {{ registro.gama_etiqueta }}</template
                        >
                    </p>

                    <div
                        class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1"
                    >
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
                </div>
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

            <!--
                Foto del paquete. `CampoFoto` muestra la vista previa de lo que se
                acaba de elegir; en su hueco va la que ya estaba guardada, para
                que se vea que hay una sin tener que abrir nada.
            -->
            <div class="grid gap-2">
                <Label>Foto del paquete</Label>
                <CampoFoto name="foto">
                    <template #placeholder>
                        <img
                            v-if="registro?.foto_miniatura_url"
                            :src="registro.foto_miniatura_url"
                            alt="Paquete actual"
                            class="size-full object-cover"
                        />
                        <Package
                            v-else
                            class="size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </template>
                </CampoFoto>
                <InputError :message="errors.foto" />

                <CampoCheck
                    v-if="registro?.foto_url"
                    name="quitar_foto"
                    label="Quitar la foto actual"
                    :default-value="false"
                />
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

    <!-- Uno solo para la pantalla, no uno por fila. -->
    <VisorImagen
        v-if="enElVisor?.foto_url"
        v-model:abierto="visorAbierto"
        :src="enElVisor.foto_url"
        :alt="`Paquete de ${enElVisor.etiqueta}`"
        :titulo="`Paquete de ${enElVisor.etiqueta}`"
        :descripcion="
            [enElVisor.etiqueta, enElVisor.presentacion]
                .filter(Boolean)
                .join(' · ')
        "
    />
</template>
