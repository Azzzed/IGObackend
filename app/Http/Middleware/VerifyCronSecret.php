<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCronSecret
{
    /**
     * Protege los endpoints de cron verificando el header X-Cron-Secret
     * contra la variable de entorno CRON_SECRET. Comparación en tiempo
     * constante para evitar ataques de temporización.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.cron.secret');
        $provided = $request->header('X-Cron-Secret');

        if (empty($expected) || empty($provided) || ! hash_equals($expected, $provided)) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado.',
                'errors'  => [],
            ], 403);
        }

        return $next($request);
    }
}
