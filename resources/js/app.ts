import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import PublicoLayout from '@/layouts/PublicoLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { registrarServiceWorker } from '@/lib/serviceWorker';
import { iniciarSinConexion } from '@/sinConexion/iniciar';
import type { Auth } from '@/types/auth';

const appName = import.meta.env.VITE_APP_NAME || 'Huella';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name, page) => {
        switch (true) {
            case name === 'Welcome':
                return PublicoLayout;
            /*
             * Acerca de y Cookies se abren con sesión y sin ella. Sin usuario
             * no pueden ir en AppLayout —NavUser recibiría un `auth.user` nulo
             * y rompería la página antes de mostrarse—, así que se dibujan
             * solas: cada página trae su propio encabezado para ese caso.
             */
            case name === 'AcercaDe':
            case name === 'Cookies':
                return (page.props.auth as Auth).user
                    ? AppLayout
                    : PublicoLayout;
            case name.startsWith('auth/'):
            // La invitación se abre sin sesión: con AppLayout, NavUser recibiría
            // un `auth.user` nulo y rompería la página antes de mostrarse.
            case name.startsWith('invitaciones/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#0f766e',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

// Registra el service worker y avisa cuando hay una versión nueva.
registrarServiceWorker();

// Navegar sin conexión con lo último guardado, y la cola de tomas, pesos,
// baños y notas que se sincroniza al volver la señal.
iniciarSinConexion();
