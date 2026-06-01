<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificacionService
{
    /** Zona horaria de referencia para "hoy" y "esta semana" */
    private const TZ = 'America/Bogota';

    public function __construct(private readonly PushService $pushService) {}

    /* ─────────────────────────────────────────────
     |  Recordatorio diario
     ───────────────────────────────────────────── */

    /**
     * Recorre los usuarios con suscripción push y envía las acciones que
     * caen HOY según la distribución uniforme de cada iniciativa con plazo.
     *
     * @return int número de usuarios que recibieron notificación
     */
    public function recordatoriosDiarios(): int
    {
        $hoy                 = Carbon::now(self::TZ)->format('Y-m-d');
        $usuariosNotificados = 0;

        foreach ($this->usuariosConSuscripcion() as $usuario) {
            $accionesHoy = [];

            foreach ($this->iniciativasConPlazoDelUsuario($usuario) as $ini) {
                $plazo    = $ini['plazo'];
                $acciones = is_array($ini['acciones'] ?? null) ? $ini['acciones'] : [];

                if (count($acciones) === 0) {
                    continue;
                }

                // ¿La iniciativa está activa hoy? (hoy dentro de la ventana)
                if ($hoy < $plazo['fecha_inicio'] || $hoy > $plazo['fecha_fin']) {
                    continue;
                }

                $fechas = $this->distribuirAcciones(
                    $plazo['fecha_inicio'],
                    $plazo['fecha_fin'],
                    count($acciones)
                );

                foreach ($fechas as $idx => $fecha) {
                    if ($fecha === $hoy) {
                        $accionesHoy[] = $acciones[$idx]['titulo'] ?? 'Acción programada';
                    }
                }
            }

            if (count($accionesHoy) === 0) {
                continue;
            }

            $body = count($accionesHoy) === 1
                ? $accionesHoy[0]
                : 'Tienes ' . count($accionesHoy) . ' acciones programadas para hoy';

            $this->pushService->enviarAUsuario($usuario, [
                'title' => 'Tu plan de hoy en IGO Manager',
                'body'  => $body,
                'url'   => '/matriz',
            ]);

            $usuariosNotificados++;
        }

        Log::info('Recordatorios diarios procesados', ['usuarios_notificados' => $usuariosNotificados]);

        return $usuariosNotificados;
    }

    /* ─────────────────────────────────────────────
     |  Resumen semanal
     ───────────────────────────────────────────── */

    /**
     * Cada lunes: resumen de las iniciativas cuya ventana toca la semana actual.
     *
     * @return int número de usuarios que recibieron notificación
     */
    public function resumenSemanal(): int
    {
        $inicioSemana = Carbon::now(self::TZ)->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
        $finSemana    = Carbon::now(self::TZ)->endOfWeek(Carbon::SUNDAY)->format('Y-m-d');
        $usuariosNotificados = 0;

        foreach ($this->usuariosConSuscripcion() as $usuario) {
            $iniciativasSemana = [];

            foreach ($this->iniciativasConPlazoDelUsuario($usuario) as $ini) {
                $plazo = $ini['plazo'];

                // La ventana toca la semana si: inicio <= finSemana && fin >= inicioSemana
                if ($plazo['fecha_inicio'] > $finSemana || $plazo['fecha_fin'] < $inicioSemana) {
                    continue;
                }

                $iniciativasSemana[] = [
                    'titulo'      => $ini['titulo'] ?? 'Iniciativa',
                    'cuadrante'   => (int) ($ini['cuadrante'] ?? 99),
                    'importancia' => (int) ($ini['importancia'] ?? 0),
                ];
            }

            if (count($iniciativasSemana) === 0) {
                continue;
            }

            // Prioriza: cuadrante más bajo primero (1 = hacer ya), luego mayor importancia
            usort($iniciativasSemana, function ($a, $b) {
                return $a['cuadrante'] !== $b['cuadrante']
                    ? $a['cuadrante'] <=> $b['cuadrante']
                    : $b['importancia'] <=> $a['importancia'];
            });

            $n           = count($iniciativasSemana);
            $prioritaria = $iniciativasSemana[0]['titulo'];

            $this->pushService->enviarAUsuario($usuario, [
                'title' => 'Tu resumen semanal en IGO Manager',
                'body'  => "Esta semana tienes {$n} iniciativas. Empieza por: {$prioritaria}",
                'url'   => '/matriz',
            ]);

            $usuariosNotificados++;
        }

        Log::info('Resumen semanal procesado', ['usuarios_notificados' => $usuariosNotificados]);

        return $usuariosNotificados;
    }

    /* ─────────────────────────────────────────────
     |  Helpers
     ───────────────────────────────────────────── */

    /**
     * Reparte N acciones uniformemente entre fecha_inicio y fecha_fin (inclusive).
     * Devuelve un array de fechas 'Y-m-d', una por acción, equiespaciadas.
     *
     * Ejemplo: 14 días con 3 acciones → [inicio, inicio+7, fin]
     */
    public function distribuirAcciones(string $fechaInicio, string $fechaFin, int $numAcciones): array
    {
        if ($numAcciones <= 0) {
            return [];
        }

        $inicio = Carbon::parse($fechaInicio);
        $fin    = Carbon::parse($fechaFin);

        if ($numAcciones === 1) {
            return [$inicio->format('Y-m-d')];
        }

        $totalDias = $inicio->diffInDays($fin);
        $fechas    = [];

        for ($i = 0; $i < $numAcciones; $i++) {
            $offset   = (int) round($i * $totalDias / ($numAcciones - 1));
            $fechas[] = $inicio->copy()->addDays($offset)->format('Y-m-d');
        }

        return $fechas;
    }

    /**
     * Usuarios que tienen al menos una suscripción push.
     */
    private function usuariosConSuscripcion()
    {
        return User::whereHas('pushSubscriptions')->get();
    }

    /**
     * Devuelve todas las iniciativas con plazo != null de todos los últimos
     * informes de las empresas del usuario. Salta informes con JSON malformado.
     *
     * @return array<int, array> lista de iniciativas (cada una con su 'plazo')
     */
    private function iniciativasConPlazoDelUsuario(User $usuario): array
    {
        $resultado = [];

        $empresas = $usuario->empresas()->with('ultimoInforme')->get();

        foreach ($empresas as $empresa) {
            $informe = $empresa->ultimoInforme;
            if (! $informe) {
                continue;
            }

            try {
                $contenido = $informe->contenido_json;

                if (! is_array($contenido) || ! isset($contenido['iniciativas']) || ! is_array($contenido['iniciativas'])) {
                    continue; // JSON malformado o sin iniciativas
                }

                foreach ($contenido['iniciativas'] as $ini) {
                    $plazo = $ini['plazo'] ?? null;

                    if (! is_array($plazo) || empty($plazo['fecha_inicio']) || empty($plazo['fecha_fin'])) {
                        continue; // cuadrante 4 (plazo null) o plazo incompleto
                    }

                    $resultado[] = $ini;
                }
            } catch (Throwable $e) {
                Log::warning('Notificaciones: informe con JSON inválido, se omite', [
                    'empresa_id' => $empresa->id,
                    'error'      => $e->getMessage(),
                ]);
                continue;
            }
        }

        return $resultado;
    }
}
