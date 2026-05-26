<?php

namespace App\Http\Requests\Iniciativa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIniciativaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titulo'        => ['required', 'string', 'max:255'],
            'categoria'     => ['required', Rule::in(['gestion_operativa','tecnologia_informacion','gestion_financiera','cadena_suministro','talento_humano','comercial_ventas','legal_cumplimiento','otro'])],
            'importancia'   => ['required', 'integer', 'min:1', 'max:5'],
            'gobernabilidad'=> ['required', 'integer', 'min:1', 'max:5'],
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.required'         => 'El título de la iniciativa es obligatorio.',
            'categoria.required'      => 'La categoría es obligatoria.',
            'categoria.in'            => 'La categoría seleccionada no es válida.',
            'importancia.required'    => 'La calificación de importancia es obligatoria.',
            'importancia.min'         => 'La importancia debe ser entre 1 y 5.',
            'importancia.max'         => 'La importancia debe ser entre 1 y 5.',
            'gobernabilidad.required' => 'La calificación de gobernabilidad es obligatoria.',
            'gobernabilidad.min'      => 'La gobernabilidad debe ser entre 1 y 5.',
            'gobernabilidad.max'      => 'La gobernabilidad debe ser entre 1 y 5.',
        ];
    }
}
