<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarClock, HeartPulse, NotebookPen, Syringe } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { acerca, dashboard, login } from '@/routes';
/* @chisel-registration */
import { register } from '@/routes';
/* @end-chisel-registration */

const funcionalidades = [
    {
        icon: NotebookPen,
        titulo: 'Un diario, no diez planillas',
        texto: 'Visitas, vacunas, pesos, dietas y notas del día a día en una sola línea de tiempo.',
    },
    {
        icon: Syringe,
        titulo: 'Vacunas y desparasitaciones',
        texto: 'Cargás la fecha una vez y la app calcula sola cuándo toca el refuerzo.',
    },
    {
        icon: CalendarClock,
        titulo: 'Te avisa antes',
        texto: 'Todo lo que tiene fecha futura genera un recordatorio. No hay que acordarse de nada.',
    },
    {
        icon: HeartPulse,
        titulo: 'A mano en la urgencia',
        texto: 'Alergias, medicación en curso e historial completo, listos para exportar a PDF.',
    },
];
</script>

<template>
    <Head title="El historial de tus mascotas, siempre a mano" />

    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header class="mx-auto w-full max-w-5xl px-6 py-6">
            <nav class="flex items-center justify-between gap-4">
                <!--
                    El logo viene con fondo blanco opaco: invertirlo en oscuro
                    arruinaría los colores de marca, así que se le da su propia
                    base clara redondeada y queda como una placa intencional.
                -->
                <!--
                    Con `w-auto` y sin dimensiones, el ancho es 0 hasta que la
                    imagen carga y todo el header se corre al aparecer. Las
                    medidas reales del archivo alcanzan para reservar el hueco.
                -->
                <!--
                    `h-9` en el celular: a `h-12` el logo mide 147px de ancho y
                    junto a los dos botones se pasaba de los 342px útiles de una
                    pantalla de 390, lo que le daba scroll horizontal a la
                    portada entera.
                -->
                <img
                    src="/img/huella-logo-horizontal.webp"
                    alt="Huella — cuidá, registrá, acompañá. Siempre."
                    width="681"
                    height="222"
                    class="h-9 w-auto rounded-lg sm:h-12 dark:bg-white dark:px-2.5 dark:py-1.5"
                />

                <div class="flex shrink-0 items-center gap-2">
                    <Button v-if="$page.props.auth.user" as-child>
                        <Link :href="dashboard()">Ir a la app</Link>
                    </Button>
                    <template v-else>
                        <!--
                            En el celular entra un solo botón. El «Empezar» de más
                            abajo ya es el camino para quien no tiene cuenta, así
                            que acá arriba queda el de quien ya la tiene.
                        -->
                        <Button variant="outline" as-child class="sm:hidden">
                            <Link :href="login()">Entrar</Link>
                        </Button>

                        <div class="hidden items-center gap-2 sm:flex">
                            <Button variant="ghost" as-child>
                                <Link :href="login()">Iniciar sesión</Link>
                            </Button>
                            <!-- @chisel-registration -->
                            <Button as-child>
                                <Link :href="register()">Crear cuenta</Link>
                            </Button>
                            <!-- @end-chisel-registration -->
                        </div>
                    </template>
                </div>
            </nav>
        </header>

        <main
            class="mx-auto flex w-full max-w-5xl flex-1 flex-col justify-center px-6 py-12"
        >
            <section class="max-w-2xl">
                <h1
                    class="text-4xl font-semibold tracking-tight text-balance sm:text-5xl"
                >
                    La historia completa de tu mascota siempre a mano.
                </h1>
                <p class="mt-5 text-lg text-pretty text-muted-foreground">
                    La libreta sanitaria se pierde, se moja y no tiene lugar
                    para notas. Huella guarda la vida entera de tus mascotas —la
                    salud y lo cotidiano— en un solo lugar, y te avisa cuándo
                    toca cada cosa.
                </p>

                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <!-- @chisel-registration -->
                    <Button v-if="!$page.props.auth.user" size="lg" as-child>
                        <Link :href="register()">Empezar</Link>
                    </Button>
                    <!-- @end-chisel-registration -->
                    <Button v-else size="lg" as-child>
                        <Link :href="dashboard()">Ir a la app</Link>
                    </Button>
                </div>
            </section>

            <section class="mt-16 grid gap-6 sm:grid-cols-2">
                <article
                    v-for="item in funcionalidades"
                    :key="item.titulo"
                    class="rounded-xl border border-border p-5"
                >
                    <component :is="item.icon" class="size-5 text-primary" />
                    <h2 class="mt-3 font-medium">{{ item.titulo }}</h2>
                    <p class="mt-1 text-sm text-pretty text-muted-foreground">
                        {{ item.texto }}
                    </p>
                </article>
            </section>
        </main>

        <footer
            class="mx-auto flex w-full max-w-5xl flex-col gap-4 px-6 py-8 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
        >
            <p>
                Huella registra información, no da consejos clínicos. Ante
                cualquier duda, consultá a tu veterinario.
            </p>

            <Link
                :href="acerca()"
                class="inline-flex min-h-11 shrink-0 items-center transition-colors hover:text-foreground sm:min-h-0"
            >
                Acerca de
            </Link>
        </footer>
    </div>
</template>
