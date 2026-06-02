<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class PushService
{
    private WebPush $webPush;

    public function __construct()
    {
        $this->webPush = new WebPush([
            'VAPID' => [
                'subject'    => config('services.webpush.subject'),
                'publicKey'  => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
            ],
        ]);
    }

    /**
     * Envía un payload de notificación a TODAS las suscripciones del usuario.
     *
     * @param array $payload  ['title' => ..., 'body' => ..., 'url' => ..., 'icon' => ...]
     *
     * Las suscripciones expiradas (HTTP 404/410) se eliminan automáticamente.
     * Loggea el conteo de envíos exitosos sin exponer datos sensibles.
     *
     * @return int número de dispositivos a los que se envió con éxito
     */
    public function enviarAUsuario(User $user, array $payload): int
    {
        $subscriptions = $user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $jsonPayload = json_encode([
            'title' => $payload['title'] ?? 'IGO Manager',
            'body'  => $payload['body'] ?? '',
            'url'   => $payload['url'] ?? '/',
            'icon'  => $payload['icon'] ?? '/icon-192.png',
        ]);

        // Encola todas las notificaciones del usuario
        foreach ($subscriptions as $sub) {
            try {
                $this->webPush->queueNotification(
                    $this->construirSubscription($sub),
                    $jsonPayload
                );
            } catch (Throwable $e) {
                Log::warning('Push: no se pudo encolar notificación', [
                    'user_id'        => $user->id,
                    'subscription_id' => $sub->id,
                    'error'          => $e->getMessage(),
                ]);
            }
        }

        // Envía el lote y procesa cada resultado
        $enviadas  = 0;
        $expiradas = 0;

        try {
            foreach ($this->webPush->flush() as $report) {
                $endpoint = $report->getRequest()->getUri()->__toString();

                if ($report->isSuccess()) {
                    $enviadas++;
                    continue;
                }

                // 404 / 410 → suscripción expirada: eliminar
                $statusCode = $report->getResponse()?->getStatusCode();
                if (in_array($statusCode, [404, 410], true)) {
                    PushSubscription::where('endpoint', $endpoint)->delete();
                    $expiradas++;
                } else {
                    Log::warning('Push: envío fallido', [
                        'user_id' => $user->id,
                        'status'  => $statusCode,
                        'reason'  => $report->getReason(),
                    ]);
                }
            }
        } catch (Throwable $e) {
            // Un fallo de red no debe tumbar el proceso completo
            Log::error('Push: error al hacer flush del lote', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
            return $enviadas;
        }

        Log::info('Push enviado', [
            'user_id'   => $user->id,
            'enviadas'  => $enviadas,
            'expiradas' => $expiradas,
        ]);

        return $enviadas;
    }

    /**
     * Envía una notificación de prueba al usuario. Útil para testing manual.
     *
     * @return int número de dispositivos a los que se envió con éxito
     */
    public function enviarPrueba(User $user): int
    {
        return $this->enviarAUsuario($user, [
            'title' => 'IGO Manager ✓',
            'body'  => 'Tus notificaciones funcionan correctamente. Te avisaremos cada mañana lo que toca hacer en tu plan.',
            'url'   => '/matriz',
            'icon'  => '/icons/icon-192.png',
        ]);
    }

    /**
     * Convierte un modelo PushSubscription en el objeto Subscription de la librería.
     */
    private function construirSubscription(PushSubscription $sub): Subscription
    {
        return Subscription::create([
            'endpoint'        => $sub->endpoint,
            'publicKey'       => $sub->public_key,
            'authToken'       => $sub->auth_token,
            'contentEncoding' => $sub->content_encoding ?? 'aesgcm',
        ]);
    }
}
