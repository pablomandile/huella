# Ingreso con Google

El código está completo y desplegado. Falta **solo** dar de alta las credenciales:
mientras `GOOGLE_CLIENT_ID` y `GOOGLE_CLIENT_SECRET` estén vacías, el botón no
aparece y la app funciona exactamente como antes.

## Qué hacer en Google Cloud

1. Entrá a [console.cloud.google.com](https://console.cloud.google.com/) y creá un
   proyecto (nombre sugerido: **Huella**).

2. **APIs y servicios → Pantalla de consentimiento de OAuth**:
   - Tipo de usuario: **Externo**.
   - Nombre de la app: `Huella`. Email de asistencia: el tuyo.
   - Logo (opcional): `public/img/huella-icono-app.webp` convertido a PNG.
   - Dominio autorizado: `pablomandile.com.ar`.
   - Permisos: alcanzan los tres básicos —`userinfo.email`, `userinfo.profile` y
     `openid`—. **No pidas nada más**: cualquier permiso extra dispara una revisión
     de Google que tarda semanas.
   - Mientras la app esté en modo **Prueba**, solo entran los emails que agregues
     como usuarios de prueba. Con los tres permisos básicos podés publicarla sin
     verificación.

3. **APIs y servicios → Credenciales → Crear credenciales → ID de cliente de OAuth**:
   - Tipo: **Aplicación web**.
   - Nombre: `Huella web`.
   - **URI de redireccionamiento autorizados** (agregá los dos):

     ```
     https://huella.pablomandile.com.ar/auth/google/callback
     http://localhost:8000/auth/google/callback
     ```

     Tienen que coincidir **carácter por carácter** con lo que manda la app,
     incluido el esquema y sin barra al final. Un `http` donde va `https` da
     `redirect_uri_mismatch`, que es el error más común de este trámite.

   - Copiá el **ID de cliente** y el **secreto de cliente**.

## Qué poner en el `.env`

En local:

```env
GOOGLE_CLIENT_ID=...apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=...
```

En producción, lo mismo en el `.env` del server y después **`config:cache`**, o la
app sigue leyendo la configuración vieja:

```bash
R=~/domains/pablomandile.com.ar/huella
/opt/alt/php84/usr/bin/php $R/artisan config:cache
```

El redirect se arma solo sobre `APP_URL`. Si hiciera falta otro, está
`GOOGLE_REDIRECT_URI`.

## Cómo verificar que quedó bien

```bash
# el botón aparece cuando el prop viene en true
curl -s https://huella.pablomandile.com.ar/login | grep -o 'googleHabilitado&quot;:[a-z]*'
```

Y entrar a `/login`: tiene que estar el botón «Continuar con Google» arriba del
formulario.

## Decisiones que ya están tomadas en el código

- **Una cuenta por email.** Si Google devuelve un email que ya existe en Huella, se
  vincula a esa cuenta en vez de crear otra. Dos cuentas con el mismo email
  significarían dos historias clínicas de la misma mascota, cada una invisible
  desde la otra.
- **El email tiene que venir verificado por Google.** Si no, se rechaza: con un
  email sin verificar cualquiera podría reclamar la cuenta de otro declarando su
  dirección.
- **Se reconoce por el `sub` de Google, no por el email.** El email de una cuenta de
  Google se puede cambiar; el identificador no. Si cambió, se actualiza.
- **La cuenta queda sin contraseña.** Nunca eligió una, y ponerle una al azar la
  haría figurar como que puede entrar con email y clave cuando no puede. Desde
  *Configuración → Seguridad* puede definirse una cuando quiera, y ahí no se le
  pide la anterior porque no existe.
- **La pantalla de seguridad no le pide confirmar la contraseña.** El
  `RequirePassword` de Laravel sería una puerta sin llave posible para estas
  cuentas, y las dejaría afuera del 2FA y de las llaves de acceso. Para quien sí
  tiene contraseña, se le sigue pidiendo igual.
- **Cancelar en la pantalla de Google no es un error**: vuelve al login sin ningún
  cartel rojo.
- **Los errores de Socialite no se muestran.** Traen partes de la respuesta de
  Google; van al log y el usuario ve un mensaje propio.
