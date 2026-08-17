<!DOCTYPE html>
{{--
    Página de respaldo cuando no hay conexión. El service worker la guarda en
    caché durante su instalación, así que NO puede depender de @vite: los assets
    compilados podrían no estar cacheados todavía. Todo va inline a propósito.
--}}
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title>Sin conexión — Huella</title>
        <link rel="icon" href="/icons/icon-192.png?v=2" type="image/png">
        <style>
            :root { color-scheme: light dark; }
            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1.5rem;
                background: #ffffff;
                color: #0a0a0a;
                font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
                line-height: 1.6;
            }
            @media (prefers-color-scheme: dark) {
                body { background: #0a0a0a; color: #ededec; }
            }
            main { max-width: 26rem; text-align: center; }
            .icono {
                width: 4rem; height: 4rem; margin: 0 auto 1.5rem;
                display: flex; align-items: center; justify-content: center;
                border-radius: 1rem; background: #0f766e;
            }
            h1 { margin: 0 0 .75rem; font-size: 1.5rem; letter-spacing: -.01em; }
            p { margin: 0 0 1.75rem; opacity: .7; }
            button {
                appearance: none; border: 0; cursor: pointer;
                min-height: 44px; padding: 0 1.5rem;
                border-radius: .5rem; background: #0f766e; color: #fff;
                font: inherit; font-weight: 500;
            }
            button:hover { background: #115e56; }
        </style>
    </head>
    <body>
        <main>
            <div class="icono">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="#fff" aria-hidden="true">
                    <ellipse cx="5.6" cy="11" rx="2.3" ry="2.9"/>
                    <ellipse cx="10" cy="7.4" rx="2.4" ry="3.1"/>
                    <ellipse cx="15" cy="7.4" rx="2.4" ry="3.1"/>
                    <ellipse cx="19.4" cy="11" rx="2.3" ry="2.9"/>
                    <path d="M12.5 13.2c-2.9 0-5.6 2.1-6.6 4.6-.8 2 .5 3.9 2.6 3.9 1.2 0 2.5-.5 4-.5s2.8.5 4 .5c2.1 0 3.4-1.9 2.6-3.9-1-2.5-3.7-4.6-6.6-4.6Z"/>
                </svg>
            </div>

            <h1>Te quedaste sin conexión</h1>
            <p>
                Huella necesita internet para mostrarte el historial al día.
                Preferimos avisarte antes que mostrarte datos viejos.
            </p>

            <button type="button" onclick="window.location.reload()">
                Reintentar
            </button>
        </main>
    </body>
</html>
