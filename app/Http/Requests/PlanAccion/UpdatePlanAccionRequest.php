<?php

namespace App\Http\Requests\PlanAccion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanAccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'deadline'    => ['sometimes', 'nullable', 'date'],
            'presupuesto' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'aliados'     => ['sometimes', 'nullable', 'string', 'max:1000'],
            'estado'      => ['sometimes', Rule::in(['pendiente','en_proceso','terminado','abortado'])],
            'notas'       => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.in'           => 'El estado seleccionado no es válido.',
            'presupuesto.numeric' => 'El presupuesto debe ser un número.',
            'presupuesto.min'     => 'El presupuesto no puede ser negativo.',
        ];
    }
}
