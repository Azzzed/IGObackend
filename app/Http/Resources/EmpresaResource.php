<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmpresaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'nombre'             => $this->nombre,
            'sector'             => $this->sector,
            'tamano'             => $this->tamano,
            'genero_empresario'  => $this->genero_empresario,
            'rango_edad'         => $this->rango_edad,
            'pais'               => $this->pais,
            'ciudad'             => $this->ciudad,
            'activa'             => $this->activa,
            'total_iniciativas'  => $this->iniciativas_count ?? $this->whenLoaded('iniciativas', fn () => $this->iniciativas->count()),
            'created_at'         => $this->created_at->toIso8601String(),
        ];
    }
}
