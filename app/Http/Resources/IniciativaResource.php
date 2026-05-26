<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IniciativaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'empresa_id'     => $this->empresa_id,
            'titulo'         => $this->titulo,
            'categoria'      => $this->categoria,
            'importancia'    => $this->importancia,
            'gobernabilidad' => $this->gobernabilidad,
            'cuadrante'      => $this->cuadrante,
            'plan_accion'    => $this->whenLoaded('planAccion', fn () => $this->planAccion
                ? new PlanAccionResource($this->planAccion)
                : null
            ),
            'created_at'     => $this->created_at->toIso8601String(),
        ];
    }
}
