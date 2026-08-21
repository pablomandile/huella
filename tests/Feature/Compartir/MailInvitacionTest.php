<?php

use App\Enums\RolCuidador;
use App\Mail\InvitacionAMascota;
use App\Models\Mascota;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;

/*
 * Lo que se verifica acá es el MIME real, no `Mailable::render()`: ese método
 * reemplaza a propósito los `cid:` por data URIs para poder previsualizar en un
 * navegador (`Mailer::replaceEmbeddedAttachments`), así que mirándolo no se
 * puede distinguir una foto bien incrustada de una que nunca se adjuntó.
 */

/** Escribe un WebP de verdad en el disco privado y lo cuelga de la mascota. */
function conFotoDePerfil(Mascota $mascota): Mascota
{
    $imagen = imagecreatetruecolor(300, 200);
    imagefill($imagen, 0, 0, imagecolorallocate($imagen, 200, 120, 60));
    ob_start();
    imagewebp($imagen);

    Storage::put('mascotas/perfil.webp', (string) ob_get_clean());

    // `foto_perfil` no es fillable: se asigna directo, igual que el controlador.
    $mascota->foto_perfil = 'mascotas/perfil.webp';
    $mascota->save();

    return $mascota->fresh();
}

function mandarInvitacion(Mascota $mascota, User $dueno): Email
{
    Mail::to('invitada@ejemplo.test')->send(new InvitacionAMascota(
        quienInvita: $dueno,
        mascota: $mascota,
        rol: RolCuidador::Lector,
        url: 'https://huella.test/invitaciones/1',
        vencimiento: '27 de agosto',
    ));

    return Mail::getSymfonyTransport()->messages()[0]->getOriginalMessage();
}

beforeEach(function () {
    Storage::fake('local');
    $this->dueno = User::factory()->create(['name' => 'Pablo']);
    $this->mascota = Mascota::factory()->for($this->dueno, 'propietario')->create(['nombre' => 'Greta']);
});

it('pone el logo en el encabezado, no el nombre en texto', function () {
    $html = (string) mandarInvitacion($this->mascota, $this->dueno)->getHtmlBody();

    expect($html)
        ->toContain('img/huella-logo-email.png')
        // PNG y no el WebP de la app: Outlook de escritorio no lo entiende.
        ->not->toContain('huella-logo-horizontal.webp')
        // Casi todos los clientes bloquean las imágenes remotas de entrada, así
        // que este alt es el encabezado que ve mucha gente.
        ->toContain('alt="Huella"');
});

it('incrusta la foto de la mascota en el mensaje', function () {
    $mensaje = mandarInvitacion(conFotoDePerfil($this->mascota), $this->dueno);

    // Referenciada por cid, nunca por URL: la foto se sirve tras verificar
    // propiedad y quien recibe la invitación todavía no tiene acceso a nada.
    expect((string) $mensaje->getHtmlBody())
        ->toContain('src="cid:')
        ->toContain('alt="Greta"')
        ->not->toContain('mascotas/foto-perfil');

    $inline = collect($mensaje->getAttachments())->first(
        fn ($parte) => $parte->getMediaSubtype() === 'jpeg',
    );

    // JPEG y no WebP, por lo mismo que el logo.
    expect($inline)->not->toBeNull()
        ->and($inline->getBody())->not->toBeEmpty();
});

it('manda la invitación igual cuando la mascota no tiene foto', function () {
    $mensaje = mandarInvitacion($this->mascota, $this->dueno);

    expect((string) $mensaje->getHtmlBody())
        ->toContain('Greta')
        ->not->toContain('src="cid:');
});

it('manda la invitación igual cuando la foto está corrupta', function () {
    // Cabecera válida y píxeles rotos: pasa cualquier validación de tipo y
    // revienta recién en el decodificador. Perder la invitación entera por no
    // poder generar la miniatura sería el peor intercambio posible.
    Storage::put('mascotas/perfil.webp', 'RIFF????WEBPVP8 basura');
    $this->mascota->foto_perfil = 'mascotas/perfil.webp';
    $this->mascota->save();

    $mensaje = mandarInvitacion($this->mascota->fresh(), $this->dueno);

    expect((string) $mensaje->getHtmlBody())
        ->toContain('Greta')
        ->not->toContain('src="cid:');
});
