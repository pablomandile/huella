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

Los dos son necesarios:

**1. Scheduler, cada minuto.** Es el que dispara
`huella:procesar-recordatorios` (cada hora, evaluando la zona horaria de cada
usuario) y `huella:cerrar-tratamientos` (todos los días a las 03:00 UTC).

```
/opt/alt/php84/usr/bin/php <HOME>/domains/pablomandile.com.ar/huella/artisan schedule:run
```

**2. Worker de cola, cada minuto.** `RecordatoriosDelDia` implementa `ShouldQueue`
y la cola es `database`: **sin este cron los mails quedan para siempre en la tabla
`jobs`** y el aviso nunca llega. El `flock` evita workers solapados y el
`--max-time=55` corta antes del tick siguiente.

```
/usr/bin/flock -n /tmp/huella-queue.lock /opt/alt/php84/usr/bin/php <HOME>/domains/pablomandile.com.ar/huella/artisan queue:work --stop-when-empty --max-time=55 --tries=3
```

Para comprobar que el cron dispara de verdad, encolá algo y mirá que la tabla se
vacíe en el minuto siguiente:

```bash
ssh <alias> "cd \$R && \$PHP artisan tinker --execute='echo DB::table(\"jobs\")->count();'"
```

## Pendientes de configuración

- **El envío de mails no está configurado.** El `.env` de producción tiene
  `MAIL_MAILER=log`: los recordatorios se escriben en `storage/logs/laravel.log` en
  vez de enviarse. Para activarlos hay que crear una cuenta de correo en hPanel y
  poner sus credenciales SMTP en el `.env` del server, y después `config:cache`.
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
