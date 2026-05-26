<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanAccionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'iniciativa_id' => $this->iniciativa_id,
            'deadline'      => $this->deadline?->toDateString(),
            'presupuesto'   => $this->presupuesto,
            'aliados'       => $this->aliados,
            'estado'        => $this->estado,
            'notas'         => $this->notas,
            'created_at'    => $this->created_at->toIso8601String(),
            'updated_at'    => $this->updated_at->toIso8601String(),
        ];
    }
}
