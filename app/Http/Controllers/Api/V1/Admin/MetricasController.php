<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FiltrosAnalyticsRequest;
use App\Services\MetricasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

class MetricasController extends Controller
{
    /** 5 minutos: KPIs e iniciativas paginadas (cambian con frecuencia) */
    private const TTL = 300;

    /** 1 hora: demográficos, palabras-clave, matriz (datos más estables) */
    private const TTL_ADMIN = 3600;

    public function __construct(private readonly MetricasService $metricasService) {}

    // ─── KPIs ─────────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/metricas/kpis
     *
     * Retorna 4 métricas globales con delta vs mes anterior y sparkline de 7 semanas.
     * Sin filtros — siempre sobre el universo completo.
     */
    public function kpis(): JsonResponse
    {
        $cacheKey = $this->metricasService->cacheKey('kpis');

        $data = Cache::remember($cacheKey, self::TTL,
            fn () => $this->metricasService->kpis()
        );

        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => 'KPIs obtenidos correctamente.',
        ]);
    }

    // ─── Crecimiento temporal ─────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/metricas/usuarios?periodo=mensual|semanal|diario
     *
     * Serie combinada: usuarios + empresas + iniciativas por período.
     * Formato array ordenado apto para gráfico de líneas.
     */
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

        $cacheKey = $this->metricasService->cacheKey("usuarios:{$periodo}");

        $data = Cache::remember($cacheKey, self::TTL_ADMIN,
            fn () => $this->metricasService->totalUsuarios($periodo)
        );

        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => 'Métricas de crecimiento obtenidas correctamente.',
        ]);
    }

    // ─── Demográficos ─────────────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/metricas/demograficos
     *
     * Distribuciones por sector, tamaño, género, edad, país.
     * Acepta todos los filtros de FiltrosAnalyticsRequest.
     */
    public function demograficos(FiltrosAnalyticsRequest $request): JsonResponse
    {
        $filtros  = $request->filtros();
        $cacheKey = $this->metricasService->cacheKey('demograficos', $filtros);

        $data = Cache::remember($cacheKey, self::TTL_ADMIN,
            fn () => $this->metricasService->demograficos($filtros)
        );

        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => 'Datos demográficos obtenidos correctamente.',
        ]);
    }

    // ─── Palabras clave ───────────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/metricas/palabras-clave
     *
     * Frecuencia de palabras en títulos de iniciativas.
     * El cuadrante ya no está hardcodeado a 1 — viene del filtro ?cuadrante=.
     * Si no se pasa cuadrante, analiza todos.
     */
    public function palabrasClave(FiltrosAnalyticsRequest $request): JsonResponse
    {
        $filtros  = $request->filtros();
        $cacheKey = $this->metricasService->cacheKey('palabras_clave', $filtros);

        $frecuencias = Cache::remember($cacheKey, self::TTL_ADMIN,
            fn () => $this->metricasService->palabrasClave($filtros)
        );

        return response()->json([
            'success' => true,
            'data'    => ['frecuencias' => $frecuencias],
            'message' => 'Palabras clave obtenidas correctamente.',
        ]);
    }

    // ─── Matriz IGO agregada ──────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/metricas/matriz-agregada
     *
     * Promedios globales, distribución por cuadrante y por sector.
     * Las asíntotas del segmento filtrado = promedio_global.importancia/gobernabilidad.
     */
    public function matrizAgregada(FiltrosAnalyticsRequest $request): JsonResponse
    {
        $filtros  = $request->filtros();
        $cacheKey = $this->metricasService->cacheKey('matriz_agregada', $filtros);

        $data = Cache::remember($cacheKey, self::TTL_ADMIN,
            fn () => $this->metricasService->matrizAgregada($filtros)
        );

        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => 'Matriz IGO agregada obtenida correctamente.',
        ]);
    }

    // ─── Iniciativas paginadas ────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/metricas/iniciativas
     *
     * Tabla paginada con todos los filtros combinables.
     * Incluye empresa_nombre (no es dato sensible). NUNCA email ni user_id.
     */
    public function iniciativas(FiltrosAnalyticsRequest $request): JsonResponse
    {
        $filtros  = $request->filtros();
        $cacheKey = $this->metricasService->cacheKey('iniciativas', $filtros);

        $resultado = Cache::remember($cacheKey, self::TTL,
            fn () => $this->metricasService->iniciativasPaginadas($filtros)
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'rows' => $resultado['rows'],
                'meta' => $resultado['meta'],
            ],
            'message' => 'Iniciativas obtenidas correctamente.',
        ]);
    }

    // ─── Exportar ─────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/exportar?formato=csv|excel
     *
     * Exporta el segmento filtrado. Acepta todos los filtros de FiltrosAnalyticsRequest.
     * ?formato=csv  → archivo .csv
     * ?formato=excel → archivo .xlsx
     */
    public function exportar(FiltrosAnalyticsRequest $request): Response
    {
        $formato = $request->query('formato', 'csv');
        $filtros = $request->filtros();
        // Quitar parámetros de paginación — exportar no pagina
        unset($filtros['page'], $filtros['per_page'], $filtros['sort'], $filtros['dir']);

        if ($formato === 'excel') {
            $spreadsheet = $this->metricasService->exportarExcel($filtros);
            $writer      = new Xlsx($spreadsheet);
            $filename    = 'igo_manager_export_' . now()->format('Ymd_His') . '.xlsx';

            return response()->streamDownload(function () use ($writer): void {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        // CSV (default)
        $datos  = $this->metricasService->exportarCsv($filtros);
        $output = fopen('php://temp', 'r+');
        fputcsv($output, $datos['headers']);
        foreach ($datos['rows'] as $fila) {
            fputcsv($output, array_values((array) $fila));
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="igo_manager_export_' . now()->format('Ymd_His') . '.csv"',
        ]);
    }
}
