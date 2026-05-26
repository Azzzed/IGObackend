<?php

namespace App\Http\Requests\PlanAccion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanAccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'deadline'    => ['nullable', 'date', 'after_or_equal:today'],
            'presupuesto' => ['nullable', 'numeric', 'min:0'],
            'aliados'     => ['nullable', 'string', 'max:1000'],
            'estado'      => ['sometimes', Rule::in(['pendiente','en_proceso','terminado','abortado'])],
            'notas'       => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'deadline.date'          => 'La fecha límite no es válida.',
            'deadline.after_or_equal'=> 'La fecha límite debe ser hoy o posterior.',
            'presupuesto.numeric'    => 'El presupuesto debe ser un número.',
            'presupuesto.min'        => 'El presupuesto no puede ser negativo.',
            'estado.in'              => 'El estado seleccionado no es válido.',
        ];
    }
}
