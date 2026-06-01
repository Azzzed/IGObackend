<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\NotificacionService;
use Illuminate\Http\JsonResponse;

class CronController extends Controller
{
    public function __construct(private readonly NotificacionService $notificacionService) {}

    /**
     * POST /api/v1/cron/recordatorios-diarios
     * Protegido por el middleware VerifyCronSecret (header X-Cron-Secret).
     */
    public function recordatoriosDiarios(): JsonResponse
    {
        $notificados = $this->notificacionService->recordatoriosDiarios();

        return response()->json([
            'success' => true,
            'data'    => ['usuarios_notificados' => $notificados],
            'message' => 'Recordatorios diarios procesados.',
        ]);
    }

    /**
     * POST /api/v1/cron/resumen-semanal
     * Protegido por el middleware VerifyCronSecret (header X-Cron-Secret).
     */
    public function resumenSemanal(): JsonResponse
    {
        $notificados = $this->notificacionService->resumenSemanal();

        return response()->json([
            'success' => true,
            'data'    => ['usuarios_notificados' => $notificados],
            'message' => 'Resumen semanal procesado.',
        ]);
    }
}
