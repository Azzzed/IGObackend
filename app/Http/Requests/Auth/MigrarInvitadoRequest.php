<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class MigrarInvitadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token_invitado'  => ['required', 'string', 'exists:users,token_invitado'],
            'nombre'          => ['required', 'string', 'max:255'],
            'email'           => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'        => ['required', 'string', 'min:8', 'confirmed'],
            'consentimiento'  => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'token_invitado.required' => 'El token de invitado es obligatorio.',
            'token_invitado.exists'   => 'La sesión de invitado no existe o ya fue migrada.',
            'nombre.required'         => 'El nombre es obligatorio.',
            'email.required'          => 'El correo es obligatorio.',
            'email.email'             => 'El correo no tiene un formato válido.',
            'email.unique'            => 'Este correo ya está registrado.',
            'password.required'       => 'La contraseña es obligatoria.',
            'password.min'            => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed'      => 'Las contraseñas no coinciden.',
            'consentimiento.accepted' => 'Debes aceptar la política de tratamiento de datos.',
        ];
    }
}
