<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { login, logout, register } from '@/routes';
import { notice } from '@/routes/verification';

/*
 * La pantalla que ve quien recibe una invitación.
 *
 * No trae ni un dato clínico: cualquiera con el enlace llega hasta acá, y lo que
 * la ficha tiene adentro recién se muestra después de aceptar con la cuenta
 * correcta. Acá va lo justo para reconocer de qué se trata.
 */
defineOptions({
    layout: {
        title: 'Te compartieron una ficha',
    },
});

defineProps<{
    mascota: {
        nombre: string;
        especie_etiqueta: string;
        foto_url: string | null;
    };
    invitadoPor: string;
    email: string;
    rol: string;
    puedeEditar: boolean;
    urlFirmada: string;
    estado:
        | 'sin_sesion'
        | 'sin_verificar'
        | 'otra_cuenta'
        | 'ya_tiene_acceso'
        | 'listo';
}>();
</script>

<template>
    <Head title="Invitación" />

    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <img
                v-if="mascota.foto_url"
                :src="mascota.foto_url"
                :alt="`Foto de ${mascota.nombre}`"
                class="size-16 shrink-0 rounded-full object-cover"
            />
            <div
                v-else
                aria-hidden="true"
                class="flex size-16 shrink-0 items-center justify-center rounded-full bg-muted text-xl font-semibold text-muted-foreground"
            >
                {{ mascota.nombre.charAt(0) }}
            </div>

            <div class="min-w-0">
                <p class="truncate text-lg font-semibold">
                    {{ mascota.nombre }}
                </p>
                <p class="truncate text-sm text-muted-foreground">
                    {{ mascota.especie_etiqueta }} · te la compartió
                    {{ invitadoPor }}
                </p>
            </div>
        </div>

        <p v-if="puedeEditar" class="text-sm text-muted-foreground">
            Vas a poder ver su historial completo y
            <strong class="text-foreground"
                >registrar tomas, pesos, visitas y notas</strong
            >. La ficha sigue siendo de {{ invitadoPor }}.
        </p>
        <p v-else class="text-sm text-muted-foreground">
            Vas a poder ver su historial completo, pero
            <strong class="text-foreground">no cargar ni modificar nada</strong
            >.
        </p>

        <!-- Listo: entrar. El action es la URL firmada que armó el servidor;
             Wayfinder no puede generar una. -->
        <Form
            v-if="estado === 'listo'"
            method="post"
            :action="urlFirmada"
            v-slot="{ processing }"
        >
            <Button type="submit" class="w-full" :disabled="processing">
                <Spinner v-if="processing" />
                Ver la ficha de {{ mascota.nombre }}
            </Button>
        </Form>

        <div v-else-if="estado === 'ya_tiene_acceso'">
            <Form method="post" :action="urlFirmada" v-slot="{ processing }">
                <Button type="submit" class="w-full" :disabled="processing">
                    <Spinner v-if="processing" />
                    Ya tenés acceso: ver la ficha
                </Button>
            </Form>
        </div>

        <!-- Sin cuenta o sin sesión. El aviso del email va antes del botón a
             propósito: registrarse con otra dirección deja la invitación
             inservible, y enterarse después es peor. -->
        <div v-else-if="estado === 'sin_sesion'" class="space-y-3">
            <p class="rounded-md border p-3 text-sm">
                Tenés que entrar con
                <strong class="break-all">{{ email }}</strong
                >, que es la dirección a la que {{ invitadoPor }} mandó la
                invitación.
            </p>

            <Button as-child class="w-full">
                <Link :href="login()">Ingresar</Link>
            </Button>

            <p class="text-center text-sm text-muted-foreground">
                ¿Todavía no tenés cuenta?
                <TextLink :href="register()">Creá una</TextLink>
            </p>
        </div>

        <div v-else-if="estado === 'sin_verificar'" class="space-y-3">
            <p class="rounded-md border p-3 text-sm">
                Antes de poder ver la ficha tenés que confirmar tu dirección de
                correo.
            </p>
            <Button as-child class="w-full">
                <Link :href="notice()">Confirmar mi email</Link>
            </Button>
            <p class="text-sm text-muted-foreground">
                Cuando la confirmes, volvé a abrir el enlace del mail.
            </p>
        </div>

        <div v-else class="space-y-3">
            <p class="rounded-md border p-3 text-sm">
                Esta invitación es para
                <strong class="break-all">{{ email }}</strong> y entraste con
                otra cuenta.
            </p>
            <Form v-bind="logout.form()" v-slot="{ processing }">
                <Button
                    type="submit"
                    variant="secondary"
                    class="w-full"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" />
                    Cerrar sesión y entrar con la otra
                </Button>
            </Form>
        </div>
    </div>
</template>
