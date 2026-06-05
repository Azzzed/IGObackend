<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RefreshToken
{
    /** Renueva el token cuando le quedan menos de estos días de vida. */
    private const DIAS_UMBRAL = 7;

    /**
     * Renovación transparente de token Sanctum.
     *
     * Si el token del usuario autenticado está a menos de 7 días de expirar,
     * emite uno nuevo (con ventana completa de 30 días) y lo devuelve en el
     * header X-Refreshed-Token. El frontend debe reemplazar su token guardado
     * por ese valor.
     *
     * No toca las suscripciones push: están ligadas a user_id, no al token,
     * así que sobreviven a la renovación sin cambios.
     *
     * Cualquier error en la renovación se ignora: la request original sigue
     * su curso con el token vigente (nunca rompe la respuesta).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->intentarRenovar($request, $response);
        } catch (Throwable $e) {
            Log::warning('RefreshToken: no se pudo renovar el token', [
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
    }

    private function intentarRenovar(Request $request, Response $response): void
    {
        $user = $request->user();
        if (! $user) {
            return;
        }

        $token = $user->currentAccessToken();

        // Solo aplica a tokens reales de BD (no a TransientToken de sesión).
        if (! $token instanceof PersonalAccessToken) {
            return;
        }

        $expiresAt = $this->calcularExpiracion($token);

        // Sin expiración configurada → no hay nada que renovar.
        if ($expiresAt === null) {
            return;
        }

        // Días restantes (con signo): negativo si ya expiró.
        $diasRestantes = now()->diffInDays($expiresAt, false);

        if ($diasRestantes < 0 || $diasRestantes >= self::DIAS_UMBRAL) {
            return; // ya expirado, o aún lejos del umbral
        }

        // Emite un token nuevo con la ventana completa (config sanctum.expiration).
        $nuevo = $user->createToken($token->name ?? 'auth_token')->plainTextToken;

        $response->headers->set('X-Refreshed-Token', $nuevo);

        // El token actual se deja vivir sus días restantes para no romper
        // requests en vuelo de clientes que aún no adoptaron el nuevo token.
    }

    /**
     * Calcula la fecha de expiración efectiva del token:
     * - Si el token tiene expires_at propio, ese manda.
     * - Si no, se usa created_at + config('sanctum.expiration') minutos.
     * - Si no hay expiración global, devuelve null.
     */
    private function calcularExpiracion(PersonalAccessToken $token): ?\Illuminate\Support\Carbon
    {
        if ($token->expires_at !== null) {
            return $token->expires_at;
        }

        $minutos = config('sanctum.expiration');
        if (empty($minutos)) {
            return null;
        }

        return $token->created_at->copy()->addMinutes($minutos);
    }
}
