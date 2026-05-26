<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InformeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'empresa_id'     => $this->empresa_id,
            'contenido'      => $this->contenido_json,
            'asintotas'      => $this->asintotas_json,
            'created_at'     => $this->created_at->toIso8601String(),
        ];
    }
}
