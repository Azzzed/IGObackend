<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class InvitadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'consentimiento' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'consentimiento.accepted' => 'Debes aceptar la política de tratamiento de datos.',
        ];
    }
}
