<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\MetricasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class MetricasController extends Controller
{
    private const TTL_ADMIN = 3600; // 1 hora — métricas cambian poco

    public function __construct(private readonly MetricasService $metricasService) {}

    public function usuarios(Request $request): JsonResponse
    {
        $periodo = $request->query('periodo', 'mensual');

        if (! in_array($periodo, ['diario', 'semanal', 'mensual'])) {
            return response()->json([
                'success' => false,
                'message' => 'Período inválido. Usa: diario, semanal o mensual.',
                'errors'  => [],
            ], 422);
        }

        // MetricasService devuelve arrays planos — son serializables directamente
        $data = Cache::remember("admin:metricas:usuarios:{$periodo}", self::TTL_ADMIN,
            fn () => $this->metricasService->totalUsuarios($periodo)
        );

        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => 'Métricas de usuarios obtenidas correctamente.',
        ]);
    }

    public function demograficos(): JsonResponse
    {
        $data = Cache::remember('admin:metricas:demograficos', self::TTL_ADMIN,
            fn () => $this->metricasService->demograficos()
        );

        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => 'Datos demográficos obtenidos correctamente.',
        ]);
    }

    public function palabrasClave(): JsonResponse
    {
        $frecuencias = Cache::remember('admin:metricas:palabras_clave', self::TTL_ADMIN,
            fn () => $this->metricasService->palabrasClave()
        );

        return response()->json([
            'success' => true,
            'data'    => ['frecuencias' => $frecuencias],
            'message' => 'Palabras clave del cuadrante 1 obtenidas correctamente.',
        ]);
    }

    public function matrizAgregada(): JsonResponse
    {
        $data = Cache::remember('admin:metricas:matriz_agregada', self::TTL_ADMIN,
            fn () => $this->metricasService->matrizAgregada()
        );

        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => 'Matriz IGO agregada obtenida correctamente.',
        ]);
    }

    public function exportar(Request $request): Response
    {
        $formato = $request->query('formato', 'csv');
        $datos   = $this->metricasService->exportarCsv();

        $output  = fopen('php://temp', 'r+');
        fputcsv($output, $datos['headers']);

        foreach ($datos['rows'] as $fila) {
            fputcsv($output, array_values((array) $fila));
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $filename = 'igo_manager_export_' . now()->format('Ymd_His') . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
