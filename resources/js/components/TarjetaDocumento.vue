<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { Download, FileText, Trash2, Upload } from '@lucide/vue';
import { ref } from 'vue';
import CampoArchivos from '@/components/CampoArchivos.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { destroy as destroyAdjunto } from '@/routes/adjuntos';
import { store as storeDocumento } from '@/routes/mascotas/documentos';
import type { Adjunto } from '@/types/huella';

/*
 * Una de las dos tarjetas de documentación de la mascota: la libreta sanitaria o
 * el certificado de rabia. Misma mecánica, así que vive en un solo lugar.
 *
 * La subida va en un sheet y no en un dialog: en el celular hay que elegir varios
 * archivos y ver la lista de lo elegido, y un dialog chico no da lugar.
 */

const props = defineProps<{
    titulo: string;
    descripcion: string;
    /** Valor del enum `TipoAdjunto` que se manda al backend. */
    tipo: string;
    mascotaId: number;
    archivos: Adjunto[];
    puedeRegistrar: boolean;
}>();

const sheet = ref(false);

function eliminar(adjunto: Adjunto) {
    if (confirm(`¿Eliminar ${adjunto.nombre_original ?? 'este archivo'}?`)) {
        router.delete(destroyAdjunto(adjunto.id).url, { preserveScroll: true });
    }
}
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-start justify-between gap-4">
            <div>
                <CardTitle>{{ props.titulo }}</CardTitle>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ props.descripcion }}
                </p>
            </div>

            <Sheet v-if="props.puedeRegistrar" v-model:open="sheet">
                <SheetTrigger as-child>
                    <Button
                        variant="outline"
                        size="sm"
                        class="touch-target shrink-0"
                    >
                        <Upload class="size-4" aria-hidden="true" />
                        Subir
                    </Button>
                </SheetTrigger>
                <SheetContent
                    side="bottom"
                    class="max-h-[90dvh] gap-0 overflow-y-auto rounded-t-2xl pb-[env(safe-area-inset-bottom)]"
                >
                    <SheetHeader>
                        <SheetTitle>{{ props.titulo }}</SheetTitle>
                        <SheetDescription>
                            Podés subir varias fotos de una, o un PDF. Hasta 10
                            MB cada archivo.
                        </SheetDescription>
                    </SheetHeader>

                    <Form
                        :action="storeDocumento(props.mascotaId).url"
                        method="post"
                        class="flex flex-col gap-4 p-4"
                        :options="{ preserveScroll: true }"
                        @success="sheet = false"
                        v-slot="{ errors, processing }"
                    >
                        <!-- El tipo no lo elige el usuario: lo define la tarjeta. -->
                        <input type="hidden" name="tipo" :value="props.tipo" />

                        <CampoArchivos name="archivos[]" />
                        <InputError
                            :message="errors.archivos ?? errors['archivos.0']"
                        />

                        <div class="grid gap-2">
                            <Label :for="`descripcion-${props.tipo}`">
                                Nota (opcional)
                            </Label>
                            <Input
                                :id="`descripcion-${props.tipo}`"
                                name="descripcion"
                                maxlength="255"
                                placeholder="Hojas 1 a 4"
                            />
                            <InputError :message="errors.descripcion" />
                        </div>

                        <Button
                            type="submit"
                            class="touch-target w-full"
                            :disabled="processing"
                        >
                            <Spinner v-if="processing" />
                            Guardar
                        </Button>
                    </Form>
                </SheetContent>
            </Sheet>
        </CardHeader>

        <CardContent class="flex flex-col gap-4">
            <!-- Lo propio de cada tarjeta: el vencimiento del certificado. -->
            <slot />

            <p
                v-if="!props.archivos.length"
                class="text-sm text-muted-foreground"
            >
                Todavía no hay nada cargado.
            </p>

            <ul v-else class="flex flex-col gap-2">
                <li
                    v-for="adjunto in props.archivos"
                    :key="adjunto.id"
                    class="flex items-center gap-3 rounded-lg border border-border p-2"
                >
                    <a
                        :href="adjunto.url"
                        target="_blank"
                        rel="noopener"
                        class="flex min-w-0 flex-1 items-center gap-3"
                    >
                        <img
                            v-if="adjunto.miniatura_url"
                            :src="adjunto.miniatura_url"
                            :alt="adjunto.nombre_original ?? 'Documento'"
                            width="48"
                            height="48"
                            loading="lazy"
                            class="size-12 shrink-0 rounded object-cover"
                        />
                        <span
                            v-else
                            class="flex size-12 shrink-0 items-center justify-center rounded bg-muted"
                        >
                            <FileText
                                class="size-5 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm">
                                {{ adjunto.nombre_original }}
                            </span>
                            <span class="block text-xs text-muted-foreground">
                                <template v-if="adjunto.tamanio_legible">
                                    {{ adjunto.tamanio_legible }}
                                </template>
                                <template v-if="adjunto.descripcion">
                                    · {{ adjunto.descripcion }}
                                </template>
                            </span>
                        </span>
                    </a>

                    <Button
                        variant="ghost"
                        size="icon"
                        as-child
                        class="touch-target shrink-0"
                    >
                        <a
                            :href="adjunto.descarga_url"
                            :aria-label="`Descargar ${adjunto.nombre_original ?? 'el archivo'}`"
                        >
                            <Download class="size-4" aria-hidden="true" />
                        </a>
                    </Button>

                    <Button
                        v-if="props.puedeRegistrar"
                        variant="ghost"
                        size="icon"
                        class="touch-target shrink-0 text-destructive"
                        :aria-label="`Eliminar ${adjunto.nombre_original ?? 'el archivo'}`"
                        @click="eliminar(adjunto)"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                    </Button>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
