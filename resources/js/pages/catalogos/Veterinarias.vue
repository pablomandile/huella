<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Clock, Phone } from '@lucide/vue';
import CampoCheck from '@/components/CampoCheck.vue';
import InputError from '@/components/InputError.vue';
import ListaCatalogo from '@/components/ListaCatalogo.vue';
import TextareaNativo from '@/components/TextareaNativo.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as catalogos } from '@/routes/catalogos';
import { destroy, index, store, update } from '@/routes/catalogos/veterinarias';
import type { Veterinaria } from '@/types/huella';

defineProps<{
    registros: Veterinaria[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Catálogos', href: catalogos() },
            { title: 'Veterinarias', href: index() },
        ],
    },
});
</script>

<template>
    <Head title="Veterinarias" />

    <ListaCatalogo
        titulo="Veterinarias"
        singular="veterinaria"
        :registros="registros"
        :url-alta="store().url"
        :url-edicion="(r) => update(r.id).url"
        :url-baja="(r) => destroy(r.id).url"
    >
        <template #item="{ registro }">
            <p class="truncate font-medium">{{ registro.nombre }}</p>
            <p
                v-if="registro.direccion || registro.localidad"
                class="truncate text-sm text-muted-foreground"
            >
                {{
                    [registro.direccion, registro.localidad]
                        .filter(Boolean)
                        .join(', ')
                }}
            </p>

            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                <!-- Un tap y llama: es el dato que se busca con urgencia. -->
                <a
                    v-if="registro.telefono"
                    :href="`tel:${registro.telefono}`"
                    class="inline-flex items-center gap-1 text-sm text-primary hover:underline"
                    @click.stop
                >
                    <Phone class="size-3.5" aria-hidden="true" />
                    {{ registro.telefono }}
                </a>
                <span
                    v-if="registro.horarios"
                    class="inline-flex items-center gap-1 text-xs text-muted-foreground"
                >
                    <Clock class="size-3.5" aria-hidden="true" />
                    {{ registro.horarios }}
                </span>
                <Badge
                    v-if="registro.urgencias_24h"
                    variant="secondary"
                    class="font-normal"
                >
                    Urgencias 24 h
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
                    maxlength="140"
                    autocomplete="off"
                    placeholder="Veterinaria del Parque"
                    :default-value="registro?.nombre"
                />
                <InputError :message="errors.nombre" />
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
                    <Label for="whatsapp">WhatsApp</Label>
                    <Input
                        id="whatsapp"
                        name="whatsapp"
                        type="tel"
                        maxlength="40"
                        :default-value="registro?.whatsapp ?? ''"
                    />
                    <InputError :message="errors.whatsapp" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="direccion">Dirección</Label>
                <Input
                    id="direccion"
                    name="direccion"
                    maxlength="255"
                    :default-value="registro?.direccion ?? ''"
                />
                <InputError :message="errors.direccion" />
            </div>

            <div class="grid gap-2">
                <Label for="localidad">Localidad</Label>
                <Input
                    id="localidad"
                    name="localidad"
                    maxlength="120"
                    :default-value="registro?.localidad ?? ''"
                />
                <InputError :message="errors.localidad" />
            </div>

            <div class="grid gap-2">
                <Label for="horarios">Horarios</Label>
                <Input
                    id="horarios"
                    name="horarios"
                    maxlength="255"
                    placeholder="Lunes a viernes de 9 a 20"
                    :default-value="registro?.horarios ?? ''"
                />
                <InputError :message="errors.horarios" />
            </div>

            <CampoCheck
                name="urgencias_24h"
                label="Atiende urgencias las 24 horas"
                :default-value="registro?.urgencias_24h ?? false"
            />

            <div class="grid gap-4 sm:grid-cols-2">
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

                <div class="grid gap-2">
                    <Label for="sitio_web">Sitio web</Label>
                    <Input
                        id="sitio_web"
                        name="sitio_web"
                        inputmode="url"
                        maxlength="255"
                        placeholder="vetdelparque.com.ar"
                        :default-value="registro?.sitio_web ?? ''"
                    />
                    <InputError :message="errors.sitio_web" />
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

            <input type="hidden" name="activa" value="1" />
        </template>
    </ListaCatalogo>
</template>
