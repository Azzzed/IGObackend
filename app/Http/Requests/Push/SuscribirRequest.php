<?php

namespace App\Http\Requests\Push;

use Illuminate\Foundation\Http\FormRequest;

class SuscribirRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth:sanctum ya protege la ruta
    }

    public function rules(): array
    {
        return [
            'endpoint'        => ['required', 'string', 'max:2048'],
            'keys'            => ['required', 'array'],
            'keys.p256dh'     => ['required', 'string'],
            'keys.auth'       => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'endpoint.required'    => 'Falta el endpoint de la suscripción.',
            'keys.required'        => 'Faltan las claves de la suscripción.',
            'keys.p256dh.required' => 'Falta la clave p256dh.',
            'keys.auth.required'   => 'Falta el token auth.',
        ];
    }
}
