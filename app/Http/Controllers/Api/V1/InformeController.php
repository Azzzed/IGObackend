<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InformeResource;
use App\Services\InformeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class InformeController extends Controller
{
    public function __construct(private readonly InformeService $informeService) {}

    public function generar(Request $request, int $empresaId): JsonResponse
    {
        $empresa = $request->user()->empresas()->findOrFail($empresaId);

        if ($empresa->iniciativas()->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'La empresa no tiene iniciativas registradas. Agrega al menos una para generar el informe.',
                'errors'  => [],
            ], 422);
        }

        try {
            $informe = $this->informeService->generarInforme($empresa);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors'  => [],
            ], 503);
        }

        // Invalidar caché del último informe para que el siguiente GET traiga el nuevo
        Cache::forget("informe:{$empresaId}");

        return response()->json([
            'success' => true,
            'data'    => ['informe' => new InformeResource($informe)],
            'message' => 'Informe generado correctamente.',
        ], 201);
    }

    public function ultimo(Request $request, int $empresaId): JsonResponse
    {
        $empresa = $request->user()->empresas()->findOrFail($empresaId);

        $informeData = Cache::remember("informe:{$empresaId}", 300, function () use ($empresa) {
            $informe = $empresa->ultimoInforme;
            return $informe ? (new InformeResource($informe))->resolve() : null;
        });

        if (! $informeData) {
            return response()->json([
                'success' => false,
                'message' => 'No existe ningún informe generado para esta empresa.',
                'errors'  => [],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => ['informe' => $informeData],
            'message' => 'Último informe obtenido correctamente.',
        ]);
    }
}
