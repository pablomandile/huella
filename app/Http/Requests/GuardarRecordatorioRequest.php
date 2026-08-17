<?php

namespace App\Http\Requests;

use App\Models\Mascota;
use App\Models\Recordatorio;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta y edición de un recordatorio **personalizado**: "cortarle las uñas",
 * "renovar la libreta".
 *
 * Los automáticos no pasan por acá: los genera el observer de su origen, y
 * editarlos a mano los desincronizaría del registro que los explica.
 */
class GuardarRecordatorioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $recordatorio = $this->route('recordatorio');

        if ($recordatorio instanceof Recordatorio) {
            // Un automático no se edita: se cambia su origen.
            return ! $recordatorio->tipo->esAutomatico()
                && $this->user()->can('update', $recordatorio);
        }

        $mascota = $this->route('mascota');

        return $mascota instanceof Mascota
            && $this->user()->can('registrarEventos', $mascota);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:160'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'fecha_objetivo' => ['required', 'date'],
            'dias_anticipacion' => ['nullable', 'integer', 'min:0', 'max:365'],
            'hora_notificacion' => ['nullable', 'date_format:H:i'],
            'recurrente' => ['boolean'],
            'intervalo_dias' => ['nullable', 'integer', 'min:1', 'max:730'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'titulo' => 'título',
            'fecha_objetivo' => 'fecha',
            'dias_anticipacion' => 'días de anticipación',
            'hora_notificacion' => 'hora del aviso',
            'intervalo_dias' => 'cada cuántos días',
        ];
    }
}
