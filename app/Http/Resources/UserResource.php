<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'tipo'                 => $this->tipo,
            'nombre'               => $this->nombre,
            'email'                => $this->email,
            'consentimiento'       => $this->consentimiento,
            'fecha_consentimiento' => $this->fecha_consentimiento?->toIso8601String(),
            'created_at'           => $this->created_at->toIso8601String(),
            // Solo para invitados: se devuelve su propio token_invitado para que
            // el frontend pueda enlazar la cuenta (migrar) incluso tras recargar,
            // cuando ya no lo tiene en memoria. Es el identificador de su propia
            // sesión, no expone datos de terceros.
            'token_invitado'       => $this->when($this->tipo === 'invitado', $this->token_invitado),
        ];
    }
}
