<?php

namespace App\Http\Requests\Empresa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre'            => ['sometimes', 'string', 'max:255'],
            'sector'            => ['sometimes', Rule::in(['agro','calzado_moda','tecnologia','servicios','comercio','salud','turismo','educacion','manufactura','otro'])],
            'tamano'            => ['sometimes', Rule::in(['idea','micro','pequena','mediana','grande'])],
            'genero_empresario' => ['sometimes', Rule::in(['hombre','mujer','otro'])],
            'rango_edad'        => ['sometimes', Rule::in(['18-25','26-35','36-45','46-55','56+'])],
            'pais'              => ['sometimes', 'string', 'max:100'],
            'ciudad'            => ['sometimes', 'string', 'max:100'],
            'activa'            => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'sector.in'                  => 'El sector seleccionado no es válido.',
            'tamano.in'                  => 'El tamaño seleccionado no es válido.',
            'genero_empresario.in'       => 'El género seleccionado no es válido.',
            'rango_edad.in'              => 'El rango de edad seleccionado no es válido.',
        ];
    }
}
