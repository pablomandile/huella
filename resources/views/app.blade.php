<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        {{-- viewport-fit=cover habilita env(safe-area-inset-*) en el notch de iOS --}}
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        {{-- La app es privada, pero esta descripción es lo que se ve al compartir el enlace --}}
        <meta name="description" content="El historial de salud y la vida cotidiana de tus mascotas, en un solo diario: visitas, vacunas, tratamientos, peso y notas.">
        <meta property="og:title" content="Huella">
        <meta property="og:description" content="La historia clínica que el veterinario no te da.">
        <meta property="og:type" content="website">
        <meta property="og:image" content="{{ asset('img/huella-logo-horizontal.webp') }}">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        {{--
            El ?v= no es decorativo: sin él, la caché HTTP y la base de favicons
            de Chrome mobile siguen sirviendo el ícono viejo para siempre, porque
            la URL no cambia. Al cambiar un ícono hay que subir este número, el
            del manifest y el nombre de CACHE en sw.js, los tres a la vez.
        --}}
        <link rel="icon" href="/icons/icon-192.png?v=2" type="image/png" sizes="192x192">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=2">

        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#0f766e">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Huella">

        {{--
            Chrome dispara `beforeinstallprompt` apenas carga la página, casi
            siempre ANTES de que monte Vue. Si se escuchara desde un componente
            (onMounted) el evento ya pasó y el botón de instalar no aparece nunca,
            de forma intermitente. Por eso se captura acá, antes de los bundles.
        --}}
        <script>
            (function () {
                window.__pwaInstall = { prompt: null, installed: false };

                window.addEventListener('beforeinstallprompt', function (e) {
                    e.preventDefault(); // el prompt lo lanzamos nosotros desde el botón
                    window.__pwaInstall.prompt = e;
                    window.dispatchEvent(new CustomEvent('pwa:installable'));
                });

                window.addEventListener('appinstalled', function () {
                    window.__pwaInstall.prompt = null;
                    window.__pwaInstall.installed = true;
                    window.dispatchEvent(new CustomEvent('pwa:installed'));
                });
            })();
        </script>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
