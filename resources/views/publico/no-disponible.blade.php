{{--
    Lo que ve quien abre un enlace que no sirve: inexistente, revocado o vencido.

    El vencido se distingue del resto a propósito (410 contra 404). El reflejo
    sería unificar todo para no dar un oráculo, pero acá no hay oráculo que dar:
    para llegar a esta pantalla hay que **tener** el token, así que decir "venció"
    no le revela nada a nadie que no lo tuviera ya. Y le ahorra una llamada a
    quien está parado en un mostrador con el enlace de la semana pasada.
--}}
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Enlace no disponible — Huella</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-background text-foreground flex min-h-full items-center justify-center p-6 antialiased">
    <div class="max-w-sm text-center">
        <h1 class="text-xl font-semibold">{{ $titulo }}</h1>
        <p class="text-muted-foreground mt-2 text-sm">{{ $detalle }}</p>
    </div>
</body>
</html>
