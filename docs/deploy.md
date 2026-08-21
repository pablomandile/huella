# Deploy

Huella corre en el hosting compartido de Hostinger, en
`https://huella.pablomandile.com.ar`.

> Los datos de acceso (host, usuario y puerto SSH) **no están en este repo**: viven
> en la skill `deploy-hostinger` a nivel usuario (`~/.claude/skills/`). Este
> documento usa `$R` para la raíz del proyecto en el server y `$PHP` para el binario
> de PHP 8.4.
>
> ```bash
> R=~/domains/pablomandile.com.ar/huella
> PHP=/opt/alt/php84/usr/bin/php
> ```

## Lo que hay que saber antes de tocar nada

**El server no tiene Node.** El bundle se compila **siempre en local** con
`npm run build` y se copia `public/build/`. Nunca intentes `npm` allá.

**`public/build/` está en `.gitignore`**, así que no viaja por git: se sube por
`scp`. La consecuencia es la trampa más cara de este flujo — si alguien compila y
sube sin commitear el fuente, producción queda **adelante del repo**, y el próximo
deploy pisa ese trabajo en silencio. Antes de desplegar, confirmá que no haya nada
sin commitear.

**El `php` del CLI es 8.1**, aunque la web corra 8.4. Todo `artisan` y todo
`composer` van con el binario explícito, o el `composer install` corta con
`Composer detected issues in your platform`:

```bash
$PHP /usr/local/bin/composer install --no-dev --optimize-autoloader
```

**El docroot del subdominio no es `huella/public`.** hPanel lo creó dentro del
public del sitio principal, así que hay un **symlink relativo** que lo redirige:

```
pablomandile/public/huella -> ../../huella/public
```

Si el sitio empieza a mostrar la "Página por defecto" de Hostinger, lo primero que
hay que mirar es ese symlink: es probable que hPanel lo haya reemplazado por una
carpeta con su `default.php` (el original quedó guardado en
`pablomandile/public/huella_default_bak/`).

**Nunca se sobreescriben** en el server: `.env`, `storage/` ni la base de datos.

**El CDN de Hostinger reescribe el `Vary`, y cuando comprime con brotli lo borra.** No es
un detalle de trivia: `Vary` es lo único que distingue el HTML de una URL de su JSON de
Inertia, y sin él el navegador confunde uno con otro (ver *Caché de las respuestas de
Inertia* en `CLAUDE.md`). Se comprueba en tres líneas:

```bash
for AE in "gzip, deflate, br, zstd" "gzip" "identity"; do
  printf "%-26s -> " "$AE"
  curl -sI https://huella.pablomandile.com.ar/login -H "Accept-Encoding: $AE" \
    | tr -d '\r' | grep -iE "^(vary|content-encoding):" | paste -sd' · '
done
# br    -> content-encoding: br    ·  (sin Vary)   <- lo que manda un navegador real
# gzip  -> content-encoding: gzip  ·  vary: Accept-Encoding
```

Moraleja general: **no confíes en que un header que setea la app llegue al navegador.**
Si algo depende de un header, verificalo con `curl` contra producción, no contra local.

## Deploy de un cambio

Según lo que tocaste:

| Cambiaste… | Subís |
|---|---|
| `resources/js` o `resources/css` | `public/build/` compilado |
| Vistas Blade | ese archivo (se renderiza en el server) |
| Estáticos de `public/` (íconos, `sw.js`, manifest) | esos archivos |
| Backend (`app/`, `routes/`, `config/`) | esas carpetas, y después `config:cache` |
| Migraciones nuevas | los archivos **y** `artisan migrate --force` |

El bundle se sube a `build_new` y se hace swap, para no dejar la app a medias
mientras copia:

```bash
npm run build

# backup del build en vivo, por si hay que volver
ssh <alias> "TS=\$(date +%s); mkdir -p ~/huella_bak_\$TS && cp -r \$R/public/build ~/huella_bak_\$TS/build"

ssh <alias> "rm -rf \$R/public/build_new"
scp -r public/build <alias>:\$R/public/build_new
ssh <alias> "cd \$R/public && rm -rf _old && mv build _old && mv build_new build && rm -rf _old"
```

Si tocaste config o vistas, además:

```bash
ssh <alias> "cd \$R && \$PHP artisan config:cache && \$PHP artisan route:cache && \$PHP artisan view:cache"
```

### Verificar que quedó bien

```bash
# el hash del bundle en vivo tiene que ser el mismo que el local
curl -sL https://huella.pablomandile.com.ar/login | grep -oE 'app-[A-Za-z0-9_-]+\.js'
ls public/build/assets/ | grep -E '^app-.*\.js$'
```

El `-L` no es opcional: varias rutas redirigen y sin seguir el redirect el grep sale
vacío, lo que parece un deploy fallido y no lo es.

Y la PWA, con el script de la skill `adaptar-a-pwa`:

```bash
node ~/.claude/skills/adaptar-a-pwa/scripts/check-pwa.mjs https://huella.pablomandile.com.ar/login
```

Tiene que devolver `installabilityErrors: []`, el manifest sin errores y el service
worker activo.

## Cron jobs

**Se crean a mano desde hPanel → Avanzado → Cron Jobs.** No hay `crontab` por SSH en
este plan.

⚠️ **No uses `cd ... && comando`.** El ejecutor de hPanel no pasa el comando por un
shell: el `cd` muere, el `&&` corta y el `php` no llega a ejecutarse nunca. El
síntoma es silencioso — el cron figura creado y los recordatorios simplemente no
salen. `artisan` no necesita cwd, resuelve desde su propia ubicación: **ruta
absoluta y sin `cd`**.

**Hace falta uno solo: el scheduler.**

**Scheduler, cada minuto.** No hace el trabajo: chequea si algo vence en este
momento. Dispara `huella:procesar-recordatorios` (cada hora, porque la hora de
notificación es local de cada usuario) y `huella:cerrar-tratamientos` (todos los
días a las 03:00 UTC, medianoche en Buenos Aires).

```
/opt/alt/php84/usr/bin/php <HOME>/domains/pablomandile.com.ar/huella/artisan schedule:run
```

### No hace falta un worker de cola

Esta guía pedía un segundo cron con `queue:work` diciendo que `RecordatoriosDelDia`
implementaba `ShouldQueue`. **Era falso**, y verificarlo cuesta dos comandos:

```bash
grep -n "class RecordatoriosDelDia" app/Mail/RecordatoriosDelDia.php   # extends Mailable, sin implements
grep -rn "dispatch(\|ShouldQueue\|->queue(" app/                       # solo el comentario del mailable
```

El mailable **no** se encola a propósito, y está escrito en su propio encabezado:
lo dispara un comando del scheduler, que ya es asíncrono, y encolarlo obligaría a
tener un worker corriendo — que en hosting compartido se cae en silencio y deja al
usuario sin avisos sin que nadie se entere. El comando manda con
`Mail::to()->send()`, sincrónico.

En la app no hay `app/Jobs`, ni `dispatch()`, ni notificaciones encoladas, ni
listeners. `QUEUE_CONNECTION=database` está puesto pero nada lo usa: la tabla
`jobs` de producción nunca tuvo una fila.

Si algún día se encola algo de verdad, el cron sería este —y recién entonces:

```
/usr/bin/flock -n /tmp/huella-queue.lock /opt/alt/php84/usr/bin/php <HOME>/domains/pablomandile.com.ar/huella/artisan queue:work --stop-when-empty --max-time=55 --tries=3
```

### Comprobar que el scheduler dispara de verdad

El cron es silencioso: si no funciona, no pasa nada y nadie se entera. La forma
directa es mirar que el comando horario haya corrido:

```bash
ssh <alias> "/opt/alt/php84/usr/bin/php \$R/artisan schedule:list"
tail -50 storage/logs/laravel.log        # los errores de envío salen acá
```

## Mail

Anda por SMTP con una casilla del propio dominio. En el `.env` del server:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=huella@pablomandile.com.ar
MAIL_PASSWORD="<la de la casilla, entre comillas>"
MAIL_FROM_ADDRESS="huella@pablomandile.com.ar"
MAIL_FROM_NAME="Huella"
```

Cuatro cosas que costaron encontrar:

- **`MAIL_MAILER` estaba dos veces en el `.env`.** El bloque nuevo arriba y el viejo
  del starter kit más abajo. **Gana la última definición**, así que quedaba
  `MAIL_MAILER=log` con el host de Hostinger: el envío "funcionaba" y no llegaba nada.
  Al agregar cualquier clave al `.env`, `grep -c "^LA_CLAVE=" .env` antes de dar por
  hecho que quedó puesta.
- **`config:cache` después de tocar el `.env`**, con el binario de 8.4. Sin eso se
  sigue leyendo la config vieja y el síntoma es idéntico.
- **`MAIL_FROM_ADDRESS` tiene que ser la misma casilla que `MAIL_USERNAME`**, o
  Hostinger rechaza el envío. Y **465 va con `smtps`**, 587 con `tls`: cruzarlos cuelga
  hasta el timeout.
- **Con `MAIL_MAILER=log` los mails no dejan rastro si `LOG_LEVEL=error`**, que es lo
  que tiene producción. El driver `log` escribe con `logger->debug()` y Monolog lo
  descarta: no hay archivo, no hay nada que recuperar. Esa guía de "buscá los mails en
  el log" es falsa acá.

Verificar sin mandar nada:

```bash
cd $R && $PHP artisan config:show mail | head -6        # default .. smtp
```

Y probar la autenticación de verdad, sin enviar, con `EsmtpTransport::start()`.

## Pendientes de configuración

- **El registro está abierto.** Cualquiera con la URL puede crear una cuenta. Los
  datos de cada usuario están aislados por Policy, así que nadie ve las mascotas de
  otro, pero conviene cerrar el registro cuando estén creadas las cuentas que van a
  usar la app.

## Rollback

El backup del deploy anterior quedó en el home del server:

```bash
ssh <alias> "ls -1dt ~/huella_bak_*"
ssh <alias> "cd \$R/public && rm -rf build && cp -r ~/huella_bak_<TS>/build build"
```
