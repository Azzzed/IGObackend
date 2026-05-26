<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlanAccion\StorePlanAccionRequest;
use App\Http\Requests\PlanAccion\UpdatePlanAccionRequest;
use App\Http\Resources\PlanAccionResource;
use App\Models\Iniciativa;
use App\Models\PlanAccion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanAccionController extends Controller
{
    public function store(StorePlanAccionRequest $request, int $iniciativaId): JsonResponse
    {
        $iniciativa = $this->encontrarIniciativaDelUsuario($request, $iniciativaId);

        if ($iniciativa->planAccion()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Esta iniciativa ya tiene un plan de acción. Usa el endpoint de edición.',
                'errors'  => [],
            ], 409);
        }

        $plan = $iniciativa->planAccion()->create($request->validated());

        return response()->json([
            'success' => true,
            'data'    => ['plan' => new PlanAccionResource($plan)],
            'message' => 'Plan de acción creado correctamente.',
        ], 201);
    }

    public function show(Request $request, int $iniciativaId): JsonResponse
    {
        $iniciativa = $this->encontrarIniciativaDelUsuario($request, $iniciativaId);
        $plan       = $iniciativa->planAccion;

        if (! $plan) {
            return response()->json([
                'success' => false,
                'message' => 'Esta iniciativa no tiene un plan de acción aún.',
                'errors'  => [],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => ['plan' => new PlanAccionResource($plan)],
            'message' => 'Plan de acción obtenido correctamente.',
        ]);
    }

    public function update(UpdatePlanAccionRequest $request, int $planId): JsonResponse
    {
        $plan = $this->encontrarPlanDelUsuario($request, $planId);
        $plan->update($request->validated());

        return response()->json([
            'success' => true,
            'data'    => ['plan' => new PlanAccionResource($plan->fresh())],
            'message' => 'Plan de acción actualizado correctamente.',
        ]);
    }

    public function destroy(Request $request, int $planId): JsonResponse
    {
        $plan = $this->encontrarPlanDelUsuario($request, $planId);
        $plan->delete();

        return response()->json([
            'success' => true,
            'data'    => [],
            'message' => 'Plan de acción eliminado correctamente.',
        ]);
    }

    private function encontrarIniciativaDelUsuario(Request $request, int $iniciativaId): Iniciativa
    {
        $iniciativa = Iniciativa::findOrFail($iniciativaId);
        $request->user()->empresas()->findOrFail($iniciativa->empresa_id);

        return $iniciativa;
    }

    private function encontrarPlanDelUsuario(Request $request, int $planId): PlanAccion
    {
        $plan = PlanAccion::findOrFail($planId);
        $this->encontrarIniciativaDelUsuario($request, $plan->iniciativa_id);

        return $plan;
    }
}
