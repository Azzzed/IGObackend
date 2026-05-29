<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FiltrosAnalyticsRequest;
use App\Services\MetricasService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class RegistrosController extends Controller
{
    private const TTL = 300; // 5 minutos

    public function __construct(private readonly MetricasService $metricasService) {}

    /**
     * GET /api/v1/admin/registros
     *
     * Tabla unificada paginada: iniciativas + empresa + plan (LEFT JOIN).
     * Todos los filtros de FiltrosAnalyticsRequest son combinables simultáneamente.
     * NUNCA devuelve email ni user_id.
     *
     * Campos devueltos por fila:
     *   id, fecha_creacion, empresa_nombre, sector, tamano, ciudad, pais,
     *   genero_empresario, rango_edad, titulo, categoria,
     *   importancia, gobernabilidad, cuadrante, tiene_plan, estado_plan
     */
    public function index(FiltrosAnalyticsRequest $request): JsonResponse
    {
        $filtros  = $request->filtros();
        $cacheKey = $this->metricasService->cacheKey('registros', $filtros);

        $resultado = Cache::remember($cacheKey, self::TTL,
            fn () => $this->metricasService->registros($filtros)
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'rows' => $resultado['rows'],
                'meta' => $resultado['meta'],
            ],
            'message' => 'Registros obtenidos correctamente.',
        ]);
    }

    /**
     * GET /api/v1/admin/registros/{iniciativa_id}
     *
     * Detalle completo: iniciativa + empresa + plan de acción (si existe).
     * Anónimo — sin email ni user_id.
     */
    public function show(int $id): JsonResponse
    {
        $cacheKey = $this->metricasService->cacheKey("registro:{$id}");

        try {
            $data = Cache::remember($cacheKey, self::TTL,
                fn () => $this->metricasService->registroDetalle($id)
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Iniciativa no encontrada.',
                'errors'  => [],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => 'Detalle de registro obtenido correctamente.',
        ]);
    }
}
