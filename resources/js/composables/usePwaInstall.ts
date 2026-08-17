import { computed, onMounted, onUnmounted, ref } from 'vue';

/**
 * Botón "Instalar app".
 *
 * El evento `beforeinstallprompt` lo captura un script inline del <head>
 * (ver resources/views/app.blade.php) y lo deja en window.__pwaInstall.
 * Escucharlo acá con onMounted llegaría tarde: Chrome lo dispara antes de que
 * monte Vue y el botón no aparecería nunca, de forma intermitente.
 */

const STANDALONE = '(display-mode: standalone)';

type EventoInstalacion = {
    prompt: () => void;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

declare global {
    interface Window {
        __pwaInstall?: {
            prompt: EventoInstalacion | null;
            installed: boolean;
        };
    }
    interface Navigator {
        /** Solo existe en Safari de iOS. */
        standalone?: boolean;
    }
}

/** iOS y iPadOS nunca disparan `beforeinstallprompt`: ahí se instala a mano. */
function esSafariEnIos(): boolean {
    const ua = navigator.userAgent;
    const esDispositivoIos =
        /iPad|iPhone|iPod/.test(ua) ||
        // iPadOS se reporta como Mac; lo delata que tenga pantalla táctil.
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    // Chrome, Firefox y Edge en iOS usan WebKit pero no pueden instalar nada.
    return esDispositivoIos && !/CriOS|FxiOS|EdgiOS|OPiOS/.test(ua);
}

function detectarInstalada(): boolean {
    return (
        window.matchMedia?.(STANDALONE).matches === true ||
        navigator.standalone === true ||
        window.__pwaInstall?.installed === true
    );
}

export function usePwaInstall() {
    const estaInstalada = ref(detectarInstalada());
    const esIos = esSafariEnIos();

    // Queda en true aunque el prompt ya se haya consumido: así el segundo clic
    // muestra el instructivo del navegador en vez de dejar un botón muerto.
    const fueOfrecida = ref(!!window.__pwaInstall?.prompt);

    const sePuedeInstalar = computed(
        () => !estaInstalada.value && (fueOfrecida.value || esIos),
    );

    const alSerInstalable = () => (fueOfrecida.value = true);
    const alInstalarse = () => (estaInstalada.value = true);
    const alCambiarModo = (e: MediaQueryListEvent) =>
        (estaInstalada.value = e.matches);
    const consulta = window.matchMedia?.(STANDALONE);

    onMounted(() => {
        window.addEventListener('pwa:installable', alSerInstalable);
        window.addEventListener('pwa:installed', alInstalarse);
        consulta?.addEventListener?.('change', alCambiarModo);
    });

    onUnmounted(() => {
        window.removeEventListener('pwa:installable', alSerInstalable);
        window.removeEventListener('pwa:installed', alInstalarse);
        consulta?.removeEventListener?.('change', alCambiarModo);
    });

    /**
     * Devuelve 'manual' cuando no hay prompt nativo disponible: en iOS, o
     * cuando el usuario ya lo descartó una vez (el evento sirve una sola vez).
     */
    async function instalar(): Promise<'accepted' | 'dismissed' | 'manual'> {
        const diferido = window.__pwaInstall?.prompt;

        if (!diferido) {
            return 'manual';
        }

        window.__pwaInstall!.prompt = null;
        diferido.prompt();
        const { outcome } = await diferido.userChoice;

        return outcome;
    }

    return { sePuedeInstalar, estaInstalada, esIos, instalar };
}
