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
        ];
    }
}
