<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Globe, Mail } from '@lucide/vue';
import { computed } from 'vue';
import { home } from '@/routes';

/*
 * Qué es Huella y quién la hizo. Es la única pantalla que se abre igual con
 * sesión y sin ella: desde el menú de usuario cuando estás adentro, y desde el
 * pie de la portada cuando todavía no tenés cuenta.
 *
 * Sin sesión no hay AppLayout —lo decide `app.ts`, porque NavUser rompería con
 * un `auth.user` nulo—, así que en ese caso la página pone su propio encabezado
 * y el camino de vuelta a la portada.
 */

const EMAIL = 'pablo.mandile@gmail.com';
const SITIO = 'https://bioinfo.pablomandile.com.ar/pablo';

const page = usePage();
const conSesion = computed(() => Boolean(page.props.auth.user));
</script>

<template>
    <Head title="Acerca de" />

    <div :class="conSesion ? '' : 'min-h-svh bg-background text-foreground'">
        <!--
            Solo para quien llega sin cuenta: adentro de la app el camino de
            vuelta ya lo dan la sidebar y la barra inferior.
        -->
        <header
            v-if="!conSesion"
            class="mx-auto w-full max-w-2xl px-4 pt-4 sm:px-6 sm:pt-6"
        >
            <Link
                :href="home()"
                class="inline-flex min-h-11 items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
                <ArrowLeft class="size-4 shrink-0" />
                Volver al inicio
            </Link>
        </header>

        <main
            class="mx-auto flex w-full max-w-2xl flex-col gap-5 p-4 sm:p-6"
            :class="conSesion ? '' : 'pb-12'"
        >
            <h1 class="text-xl font-semibold">Acerca de</h1>

            <div class="rounded-xl border border-border p-6 sm:p-8">
                <!--
                    El logo viene con fondo blanco opaco: invertirlo en oscuro
                    arruinaría los colores de marca, así que se le da su propia
                    base clara redondeada. Las medidas reales del archivo van en
                    los atributos para que el hueco esté reservado antes de que
                    la imagen cargue y nada se corra al aparecer.
                -->
                <img
                    src="/img/huella-logo-horizontal.webp"
                    alt="Huella — cuidá, registrá, acompañá. Siempre."
                    width="681"
                    height="222"
                    class="h-10 w-auto rounded-lg sm:h-12 dark:bg-white dark:px-2.5 dark:py-1.5"
                />

                <p class="mt-6 text-lg leading-relaxed text-pretty">
                    El historial de salud y la vida cotidiana de tus mascotas en
                    un solo diario: visitas, vacunas, tratamientos, peso, dietas
                    y las notas de todos los días. Con los avisos de cuándo toca
                    cada cosa, para no tener que acordarse de nada.
                </p>

                <p class="mt-3 text-sm text-pretty text-muted-foreground">
                    Huella registra información, no da consejos clínicos. Ante
                    cualquier duda, consultá a tu veterinario.
                </p>

                <div class="mt-8 border-t border-border pt-6">
                    <p
                        class="text-xs font-bold tracking-[0.075em] text-muted-foreground uppercase"
                    >
                        Creado por
                    </p>
                    <p class="mt-1 text-lg font-semibold">Pablo Mandile</p>

                    <div class="mt-2 flex flex-col gap-1 sm:flex-row sm:gap-6">
                        <a
                            :href="`mailto:${EMAIL}`"
                            class="inline-flex min-h-11 items-center gap-2 text-sm transition-colors hover:text-primary"
                        >
                            <Mail class="size-4 shrink-0" />
                            <span class="break-all">{{ EMAIL }}</span>
                        </a>

                        <a
                            :href="SITIO"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex min-h-11 items-center gap-2 text-sm transition-colors hover:text-primary"
                        >
                            <Globe class="size-4 shrink-0" />
                            <span>Bioinfo</span>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
