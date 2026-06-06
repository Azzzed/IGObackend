<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegistroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre'                => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'max:255', 'unique:users,email'],
            // El frontend valida la coincidencia de contraseñas en la UI; el
            // backend solo exige la contraseña (sin 'confirmed') para no acoplar
            // al nombre del campo de confirmación y evitar 422 espurios.
            'password'              => ['required', 'string', 'min:8'],
            'consentimiento'        => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required'          => 'El nombre es obligatorio.',
            'email.required'           => 'El correo es obligatorio.',
            'email.email'              => 'El correo no tiene un formato válido.',
            'email.unique'             => 'Este correo ya está registrado.',
            'password.required'        => 'La contraseña es obligatoria.',
            'password.min'             => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed'       => 'Las contraseñas no coinciden.',
            'consentimiento.accepted'  => 'Debes aceptar la política de tratamiento de datos.',
        ];
    }
}
