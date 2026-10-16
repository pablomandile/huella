<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import BotonGoogle from '@/components/BotonGoogle.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Creá tu cuenta',
        description: 'Completá tus datos para empezar',
    },
});
</script>

<template>
    <Head title="Crear cuenta" />

    <BotonGoogle modo="registrarse" class="mb-6" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">Nombre</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    name="name"
                    placeholder="Nombre y apellido"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    autocomplete="email"
                    name="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Contraseña</Label>
                <PasswordInput
                    id="password"
                    required
                    autocomplete="new-password"
                    name="password"
                    placeholder="Contraseña"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Repetir contraseña</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Repetir contraseña"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <!--
                Checkbox nativo y no CampoCheck: la etiqueta lleva enlaces, y
                CampoCheck solo acepta texto. Los enlaces abren en otra pestaña
                para no perder lo que ya se cargó.
            -->
            <div class="grid gap-1">
                <label
                    class="flex min-h-11 cursor-pointer items-start gap-3 text-sm text-pretty"
                >
                    <input
                        type="checkbox"
                        name="terminos"
                        value="1"
                        required
                        class="mt-0.5 size-5 shrink-0 accent-primary"
                        data-test="aceptar-terminos"
                    />
                    <span>
                        Leí y acepto los
                        <a
                            href="/terminos"
                            target="_blank"
                            class="underline underline-offset-4 hover:text-primary"
                            >términos y condiciones</a
                        >
                        y la
                        <a
                            href="/privacidad"
                            target="_blank"
                            class="underline underline-offset-4 hover:text-primary"
                            >política de privacidad</a
                        >.
                    </span>
                </label>
                <InputError :message="errors.terminos" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Crear cuenta
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            ¿Ya tenés cuenta?
            <TextLink :href="login()" class="underline underline-offset-4"
                >Iniciá sesión</TextLink
            >
        </div>
    </Form>
</template>
