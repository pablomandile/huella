<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\TamanoLetra;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TamanoLetraUpdateRequest extends FormRequest
{
    /**
     * Sin sesión también: quien no llega a leer el login es quien más necesita
     * agrandar la letra.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tamano_letra' => ['required', Rule::enum(TamanoLetra::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tamano_letra' => 'tamaño de letra',
        ];
    }

    public function tamano(): TamanoLetra
    {
        return TamanoLetra::from($this->string('tamano_letra')->toString());
    }
}
