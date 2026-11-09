<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import BotonGoogle from '@/components/BotonGoogle.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
/* @chisel-registration */
import { register } from '@/routes';
/* @end-chisel-registration */
import { store } from '@/routes/login';
import { request } from '@/routes/password';
/* @chisel-passkeys */
import PasskeyVerify from '@/components/PasskeyVerify.vue';
/* @end-chisel-passkeys */

defineOptions({
    layout: {
        title: 'Entrá a tu cuenta',
        description: 'Ingresá tu email y contraseña para continuar',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

// En la demo (APP_DEMO=true) se entra con un usuario compartido.
const demo = usePage().props.demo;
</script>

<template>
    <Head title="Iniciar sesión" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <!--
        Los dos ingresos sin contraseña van juntos y el separador lo pone el
        segundo, así queda uno solo antes del formulario.
    -->
    <!--
        La demo: los datos a la vista y un botón que entra con ellos. Es un
        formulario aparte, con los valores en inputs reales, para no tocar el de
        abajo, que sigue sirviendo para tipearlos.
    -->
    <Form
        v-if="demo"
        v-bind="store.form()"
        v-slot="{ processing }"
        class="mb-6 rounded-xl border border-primary/30 bg-primary/5 p-4"
        data-test="recuadro-demo"
    >
        <p class="font-semibold">Probá la demo</p>
        <p class="mt-1 text-sm text-muted-foreground">
            Greta y Simón, con un año de historia: visitas, vacunas, la
            medicación de hoy, la curva de peso y sus papeles. Se reinicia todas
            las noches.
        </p>
        <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
            <dt class="text-muted-foreground">Email</dt>
            <dd class="font-medium wrap-anywhere">{{ demo.email }}</dd>
            <dt class="text-muted-foreground">Contraseña</dt>
            <dd class="font-medium">{{ demo.password }}</dd>
        </dl>
        <input type="hidden" name="email" :value="demo.email" />
        <input type="hidden" name="password" :value="demo.password" />
        <Button
            type="submit"
            class="mt-4 h-11 w-full"
            :disabled="processing"
            data-test="entrar-demo"
        >
            <Spinner v-if="processing" />
            Entrar a la demo
        </Button>
    </Form>

    <template v-else>
        <BotonGoogle :con-separador="false" class="mb-2" />

        <!-- @chisel-passkeys -->
        <PasskeyVerify />
        <!-- @end-chisel-passkeys -->
    </template>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Contraseña</Label>
                    <TextLink
                        v-if="canResetPassword && !demo"
                        :href="request()"
                        class="text-sm"
                    >
                        ¿Olvidaste tu contraseña?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Contraseña"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" />
                    <span>Mantener la sesión iniciada</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Iniciar sesión
            </Button>
        </div>

        <!-- @chisel-registration -->
        <div v-if="!demo" class="text-center text-sm text-muted-foreground">
            ¿Todavía no tenés cuenta?
            <TextLink :href="register()">Creá una</TextLink>
        </div>
        <!-- @end-chisel-registration -->
    </Form>
</template>
