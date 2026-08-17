<?php

namespace App\Mail;

use App\Models\Recordatorio;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

/**
 * Un solo mail con todo lo que le toca al usuario, no uno por recordatorio.
 *
 * Nadie quiere cinco mails un lunes a la mañana: si tiene la antirrábica de
 * Greta y la desparasitación de Simón, van juntas o el usuario deja de leerlos.
 *
 * **Sin `ShouldQueue` a propósito.** Quien lo manda es un comando horario del
 * scheduler, que ya es asíncrono: encolarlo agregaría la necesidad de un worker
 * corriendo, y en hosting compartido eso es justo lo que se cae en silencio y
 * deja al usuario sin avisos sin que nadie se entere.
 */
class RecordatoriosDelDia extends Mailable
{
    /**
     * @param  Collection<int, Recordatorio>  $recordatorios
     */
    public function __construct(
        public readonly User $usuario,
        public readonly Collection $recordatorios,
    ) {}

    public function envelope(): Envelope
    {
        $cantidad = $this->recordatorios->count();

        return new Envelope(
            subject: $cantidad === 1
                ? $this->recordatorios->first()->titulo
                : "Tenés {$cantidad} cosas para agendar",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.recordatorios',
            with: [
                'nombre' => $this->usuario->name,
                // `avisos` y no `recordatorios`: Laravel pasa a la vista las
                // propiedades públicas del Mailable **y** las claves de `with`,
                // y la propiedad `$recordatorios` pisaría esta clave. La vista
                // recibiría los modelos crudos y los campos calculados —fecha
                // legible, cuánto falta— llegarían en null, sin ningún error.
                'avisos' => $this->recordatorios->map(fn (Recordatorio $r) => [
                    'titulo' => $r->titulo,
                    'descripcion' => $r->descripcion,
                    'mascota' => $r->mascota->nombre,
                    // En el reloj del usuario: es la fecha que él ve en la app.
                    'fecha' => $r->fecha_objetivo->translatedFormat('l j \d\e F'),
                    'faltan' => $this->faltan($r),
                ])->all(),
            ],
        );
    }

    private function faltan(Recordatorio $recordatorio): string
    {
        // hoyCalendario y no hoy: fecha_objetivo es una columna `date` y
        // compararla contra un instante con zona corre el resultado un día.
        $dias = (int) $this->usuario->hoyCalendario()
            ->diffInDays($recordatorio->fecha_objetivo, absolute: false);

        return match (true) {
            $dias < 0 => 'ya pasó',
            $dias === 0 => 'es hoy',
            $dias === 1 => 'es mañana',
            default => "faltan {$dias} días",
        };
    }
}
