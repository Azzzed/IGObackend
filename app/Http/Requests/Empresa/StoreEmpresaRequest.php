<?php

namespace App\Http\Requests\Empresa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre'            => ['required', 'string', 'max:255'],
            'sector'            => ['required', Rule::in(['agro','calzado_moda','tecnologia','servicios','comercio','salud','turismo','educacion','manufactura','otro'])],
            'tamano'            => ['required', Rule::in(['idea','micro','pequena','mediana','grande'])],
            'genero_empresario' => ['required', Rule::in(['hombre','mujer','otro'])],
            'rango_edad'        => ['required', Rule::in(['18-25','26-35','36-45','46-55','56+'])],
            'pais'              => ['required', 'string', 'max:100'],
            'ciudad'            => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required'            => 'El nombre de la empresa es obligatorio.',
            'sector.required'            => 'El sector económico es obligatorio.',
            'sector.in'                  => 'El sector seleccionado no es válido.',
            'tamano.required'            => 'El tamaño de la empresa es obligatorio.',
            'tamano.in'                  => 'El tamaño seleccionado no es válido.',
            'genero_empresario.required' => 'El género del empresario es obligatorio.',
            'genero_empresario.in'       => 'El género seleccionado no es válido.',
            'rango_edad.required'        => 'El rango de edad es obligatorio.',
            'rango_edad.in'              => 'El rango de edad seleccionado no es válido.',
            'pais.required'              => 'El país es obligatorio.',
            'ciudad.required'            => 'La ciudad es obligatoria.',
        ];
    }
}
