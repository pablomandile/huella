import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { registrarServiceWorker } from '@/lib/serviceWorker';

const appName = import.meta.env.VITE_APP_NAME || 'Huella';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
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
