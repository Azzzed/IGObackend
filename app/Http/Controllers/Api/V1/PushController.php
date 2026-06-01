<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Push\SuscribirRequest;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushController extends Controller
{
    /**
     * GET /api/v1/push/vapid-public-key
     *
     * Devuelve la clave pública VAPID para que el frontend suscriba el navegador.
     * No expone ningún dato sensible (la clave pública es segura de compartir).
     */
    public function vapidPublicKey(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => ['public_key' => config('services.webpush.public_key')],
            'message' => 'Clave pública VAPID obtenida correctamente.',
        ]);
    }

    /**
     * POST /api/v1/push/suscribir
     *
     * Registra (o actualiza) la suscripción del navegador para el usuario autenticado.
     * updateOrCreate por endpoint evita duplicados del mismo dispositivo.
     */
    public function suscribir(SuscribirRequest $request): JsonResponse
    {
        $data = $request->validated();

        PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id'          => $request->user()->id,
                'public_key'       => $data['keys']['p256dh'],
                'auth_token'       => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aesgcm',
            ]
        );

        return response()->json([
            'success' => true,
            'data'    => [],
            'message' => 'Suscripción registrada correctamente.',
        ], 201);
    }

    /**
     * DELETE /api/v1/push/desuscribir
     *
     * Elimina la suscripción indicada del usuario autenticado.
     */
    public function desuscribir(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string'],
        ]);

        PushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint', $validated['endpoint'])
            ->delete();

        return response()->json([
            'success' => true,
            'data'    => [],
            'message' => 'Suscripción eliminada correctamente.',
        ]);
    }
}
