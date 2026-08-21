<?php

namespace App\Mail;

use App\Enums\RolCuidador;
use App\Models\Mascota;
use App\Models\User;
use App\Services\ImagenService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * "Fulano te compartió la ficha de Greta."
 *
 * **Ni un dato clínico en el cuerpo.** El mail viaja sin cifrar y puede caer en
 * la casilla equivocada: lo único que lleva es quién invita, el nombre y la foto
 * de la mascota, el enlace y cuándo vence. La foto es lo único que se sumó a esa
 * lista, y entra por la misma puerta que el nombre: sirve para reconocer de cuál
 * de las mascotas te hablan, y no dice nada de su salud.
 *
 * **Sin `ShouldQueue`**, como `RecordatoriosDelDia`: en hosting compartido el
 * worker se cae en silencio. Acá hay un motivo más para mandarlo sincrónico —lo
 * dispara un request web, así que si el SMTP falla el usuario se entera ahora y
 * no queda creyendo que invitó a alguien—.
 */
class InvitacionAMascota extends Mailable
{
    public function __construct(
        public readonly User $quienInvita,
        public readonly Mascota $mascota,
        public readonly RolCuidador $rol,
        public readonly string $url,
        public readonly string $vencimiento,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->quienInvita->name} te compartió la ficha de {$this->mascota->nombre}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invitacion',
            with: [
                // Claves distintas de las propiedades públicas: Laravel pasa a la
                // vista las dos cosas y una propiedad pisa la clave homónima sin
                // dar ningún error. Es la misma trampa que `avisos` en
                // `RecordatoriosDelDia`.
                'nombreMascota' => $this->mascota->nombre,
                'especie' => $this->mascota->especie->etiqueta(),
                'invita' => $this->quienInvita->name,
                'enlace' => $this->url,
                'vence' => $this->vencimiento,
                // El permiso, dicho en el mail y no recién al aceptar.
                'puedeEditar' => $this->rol->puedeEditar(),
                /*
                 * La foto va incrustada en el mensaje, no por URL: las imágenes
                 * de la app se sirven tras verificar propiedad y quien recibe
                 * esto todavía no tiene acceso a nada.
                 *
                 * Es `null` cuando la mascota no tiene foto o cuando no se pudo
                 * leer, y la vista lo contempla: el mail se manda igual.
                 */
                'fotoMascota' => app(ImagenService::class)
                    ->miniaturaParaMail($this->mascota->foto_perfil),
            ],
        );
    }
}
