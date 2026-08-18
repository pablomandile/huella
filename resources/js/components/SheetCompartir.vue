<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { Check, Copy, Link2, Trash2, UserPlus } from '@lucide/vue';
import { ref } from 'vue';
import CampoCheck from '@/components/CampoCheck.vue';
import InputError from '@/components/InputError.vue';
import SelectNativo from '@/components/SelectNativo.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import {
    destroy as quitarAcceso,
    update as cambiarAcceso,
} from '@/routes/mascotas/accesos';
import {
    destroy as revocarEnlace,
    store as crearEnlace,
} from '@/routes/mascotas/enlaces';
import { store as invitar } from '@/routes/mascotas/invitaciones';
import type {
    AccesoCompartido,
    EnlaceCompartido,
    OpcionDeRol,
    OpcionEnum,
} from '@/types/huella';

/*
 * Dar y quitar acceso a la ficha. Dos mecanismos, los dos de solo lectura:
 * invitar a una cuenta de Huella, o un enlace que se abre sin cuenta.
 */
const props = defineProps<{
    mascotaId: number;
    mascotaNombre: string;
    accesos: AccesoCompartido[];
    enlaces: EnlaceCompartido[];
    rolesInvitables: OpcionDeRol[];
    vigencias: OpcionEnum[];
    vigenciaPorDefecto: string;
}>();

const abierto = defineModel<boolean>('open', { default: false });

const copiado = ref<number | null>(null);

/*
 * `navigator.clipboard` solo existe en contexto seguro: en producción va, en el
 * http://huella.test de Laragon es `undefined`. El input de al lado queda
 * seleccionable como respaldo, y el botón nunca puede tirar una excepción.
 */
async function copiar(enlace: EnlaceCompartido) {
    try {
        await navigator.clipboard?.writeText(enlace.url);
        copiado.value = enlace.id;
        setTimeout(() => (copiado.value = null), 2000);
    } catch {
        copiado.value = null;
    }
}

function quitar(acceso: AccesoCompartido) {
    if (!confirm(`¿Sacarle el acceso a ${acceso.nombre}?`)) {
        return;
    }

    router.delete(quitarAcceso([props.mascotaId, acceso.id]).url, {
        preserveScroll: true,
    });
}

/*
 * El cambio de permiso se manda solo, sin botón de guardar: es un campo y
 * confirmarlo aparte sería un tap de más en la pantalla donde menos sobra.
 * `preserveScroll` para que el sheet no salte al recargar los props.
 */
function cambiarRol(acceso: AccesoCompartido, evento: Event) {
    const rol = (evento.target as HTMLSelectElement).value;

    if (rol === acceso.rol) {
        return;
    }

    router.patch(
        cambiarAcceso([props.mascotaId, acceso.id]).url,
        { rol },
        { preserveScroll: true },
    );
}

function revocar(enlace: EnlaceCompartido) {
    if (!confirm('El enlace va a dejar de funcionar. ¿Seguro?')) {
        return;
    }

    router.delete(revocarEnlace([props.mascotaId, enlace.id]).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Sheet v-model:open="abierto">
        <SheetContent
            side="bottom"
            class="max-h-[90dvh] overflow-y-auto rounded-t-2xl pb-[env(safe-area-inset-bottom)]"
        >
            <SheetHeader>
                <SheetTitle
                    >Compartir la ficha de {{ mascotaNombre }}</SheetTitle
                >
                <SheetDescription>
                    Quien la reciba va a poder ver y descargar todo el
                    historial. Si le das permiso de cuidador, además va a poder
                    cargar.
                </SheetDescription>
            </SheetHeader>

            <div class="space-y-8 px-4 pb-6">
                <!-- Quién tiene acceso -->
                <section class="space-y-3">
                    <h3 class="text-sm font-semibold">Con acceso</h3>

                    <ul class="space-y-2">
                        <li
                            v-for="acceso in accesos"
                            :key="acceso.id"
                            class="space-y-2 rounded-lg border p-3"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">
                                        {{ acceso.nombre }}
                                    </p>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ acceso.email }}
                                    </p>
                                </div>

                                <Badge
                                    v-if="acceso.rol === 'propietario'"
                                    variant="secondary"
                                    >Vos</Badge
                                >
                                <Button
                                    v-else
                                    variant="ghost"
                                    size="sm"
                                    class="touch-target text-destructive"
                                    @click="quitar(acceso)"
                                >
                                    <Trash2 class="size-4" aria-hidden="true" />
                                    <span class="sr-only"
                                        >Sacarle el acceso a
                                        {{ acceso.nombre }}</span
                                    >
                                </Button>
                            </div>

                            <!-- Cambiar el permiso sin tener que sacar a la
                                 persona y volver a invitarla. -->
                            <div
                                v-if="acceso.rol !== 'propietario'"
                                class="flex items-center gap-2"
                            >
                                <Label
                                    :for="`rol-${acceso.id}`"
                                    class="text-xs text-muted-foreground"
                                    >Permiso</Label
                                >
                                <select
                                    :id="`rol-${acceso.id}`"
                                    class="h-9 touch-target flex-1 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs dark:bg-input/30"
                                    :value="acceso.rol ?? ''"
                                    @change="cambiarRol(acceso, $event)"
                                >
                                    <option
                                        v-for="rol in rolesInvitables"
                                        :key="rol.value"
                                        :value="rol.value"
                                    >
                                        {{ rol.label }}
                                    </option>
                                </select>
                            </div>
                        </li>
                    </ul>
                </section>

                <!-- Invitar por email -->
                <section class="space-y-3">
                    <h3 class="text-sm font-semibold">Invitar a alguien</h3>

                    <Form
                        v-bind="invitar.form(mascotaId)"
                        :options="{ preserveScroll: true }"
                        reset-on-success
                        class="space-y-3"
                        v-slot="{ errors, processing }"
                    >
                        <div class="space-y-1">
                            <Label for="compartir-email">Correo</Label>
                            <Input
                                id="compartir-email"
                                name="email"
                                type="email"
                                inputmode="email"
                                autocomplete="off"
                                placeholder="alguien@ejemplo.com"
                                required
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <!-- Radios y no un select: son dos opciones con
                             consecuencias distintas, y una hay que leerla antes
                             de elegirla. Un select las esconde. -->
                        <fieldset class="space-y-2">
                            <legend class="mb-1 text-sm font-medium">
                                Qué va a poder hacer
                            </legend>

                            <Label
                                v-for="(rol, i) in rolesInvitables"
                                :key="rol.value"
                                class="flex touch-target items-start gap-3 rounded-lg border p-3 font-normal"
                            >
                                <input
                                    type="radio"
                                    name="rol"
                                    :value="rol.value"
                                    :checked="i === 0"
                                    class="mt-1 size-4 shrink-0"
                                />
                                <span>
                                    <span class="block text-sm font-medium">{{
                                        rol.label
                                    }}</span>
                                    <span
                                        class="block text-xs text-muted-foreground"
                                        >{{ rol.descripcion }}</span
                                    >
                                </span>
                            </Label>
                            <InputError :message="errors.rol" />
                        </fieldset>

                        <p class="text-xs text-muted-foreground">
                            Le mandamos un enlace por mail. Necesita una cuenta
                            de Huella con esa misma dirección para poder entrar.
                        </p>

                        <Button
                            type="submit"
                            variant="secondary"
                            class="w-full"
                            :disabled="processing"
                        >
                            <Spinner v-if="processing" />
                            <UserPlus
                                v-else
                                class="size-4"
                                aria-hidden="true"
                            />
                            Mandar la invitación
                        </Button>
                    </Form>
                </section>

                <!-- Enlace sin cuenta -->
                <section class="space-y-3">
                    <h3 class="text-sm font-semibold">Enlace para compartir</h3>

                    <ul v-if="enlaces.length" class="space-y-2">
                        <li
                            v-for="enlace in enlaces"
                            :key="enlace.id"
                            class="space-y-2 rounded-lg border p-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">
                                        {{ enlace.nombre ?? 'Sin nombre' }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Vence el {{ enlace.vence }} ·
                                        {{
                                            enlace.visitas === 0
                                                ? 'todavía no lo abrió nadie'
                                                : `se abrió ${enlace.visitas} ${enlace.visitas === 1 ? 'vez' : 'veces'}`
                                        }}
                                    </p>
                                </div>

                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="touch-target text-destructive"
                                    @click="revocar(enlace)"
                                >
                                    <Trash2 class="size-4" aria-hidden="true" />
                                    <span class="sr-only"
                                        >Revocar el enlace</span
                                    >
                                </Button>
                            </div>

                            <div class="flex gap-2">
                                <Input
                                    :model-value="enlace.url"
                                    readonly
                                    class="text-xs"
                                    :aria-label="`Enlace de ${enlace.nombre ?? mascotaNombre}`"
                                    @focus="
                                        (e: FocusEvent) =>
                                            (
                                                e.target as HTMLInputElement
                                            ).select()
                                    "
                                />
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="touch-target shrink-0"
                                    @click="copiar(enlace)"
                                >
                                    <Check
                                        v-if="copiado === enlace.id"
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    <Copy
                                        v-else
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    <span class="sr-only"
                                        >Copiar el enlace</span
                                    >
                                </Button>
                            </div>
                        </li>
                    </ul>

                    <Form
                        v-bind="crearEnlace.form(mascotaId)"
                        :options="{ preserveScroll: true }"
                        reset-on-success
                        class="space-y-3"
                        v-slot="{ errors, processing }"
                    >
                        <div class="space-y-1">
                            <Label for="enlace-nombre"
                                >Para qué es (opcional)</Label
                            >
                            <Input
                                id="enlace-nombre"
                                name="nombre"
                                placeholder="Para la guardería"
                                maxlength="80"
                            />
                            <InputError :message="errors.nombre" />
                        </div>

                        <div class="space-y-1">
                            <Label for="vigencia">Vence en</Label>
                            <SelectNativo
                                name="vigencia"
                                :opciones="vigencias"
                                :default-value="vigenciaPorDefecto"
                            />
                            <InputError :message="errors.vigencia" />
                        </div>

                        <div>
                            <CampoCheck
                                name="incluye_adjuntos"
                                label="Incluir estudios y recetas"
                            />
                            <p class="mt-1 text-xs text-muted-foreground">
                                La libreta sanitaria y el certificado de rabia
                                van siempre. Las radiografías y los análisis,
                                solo si marcás esto.
                            </p>
                        </div>

                        <p class="text-xs text-muted-foreground">
                            Cualquiera que tenga el enlace puede ver la ficha,
                            sin cuenta. Queda en el historial del navegador de
                            quien lo abra, así que conviene revocarlo cuando ya
                            no haga falta. La galería de fotos nunca se incluye.
                        </p>

                        <Button
                            type="submit"
                            variant="secondary"
                            class="w-full"
                            :disabled="processing"
                        >
                            <Spinner v-if="processing" />
                            <Link2 v-else class="size-4" aria-hidden="true" />
                            Crear un enlace
                        </Button>
                    </Form>
                </section>
            </div>
        </SheetContent>
    </Sheet>
</template>
