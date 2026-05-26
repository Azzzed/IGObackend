<?php

namespace App\Http\Requests\Iniciativa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIniciativaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titulo'         => ['sometimes', 'string', 'max:255'],
            'categoria'      => ['sometimes', Rule::in(['gestion_operativa','tecnologia_informacion','gestion_financiera','cadena_suministro','talento_humano','comercial_ventas','legal_cumplimiento','otro'])],
            'importancia'    => ['sometimes', 'integer', 'min:1', 'max:5'],
            'gobernabilidad' => ['sometimes', 'integer', 'min:1', 'max:5'],
        ];
    }

    public function messages(): array
    {
        return [
            'categoria.in'   => 'La categoría seleccionada no es válida.',
            'importancia.min' => 'La importancia debe ser entre 1 y 5.',
            'importancia.max' => 'La importancia debe ser entre 1 y 5.',
            'gobernabilidad.min' => 'La gobernabilidad debe ser entre 1 y 5.',
            'gobernabilidad.max' => 'La gobernabilidad debe ser entre 1 y 5.',
        ];
    }
}
