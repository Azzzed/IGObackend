<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EmpresaController;
use App\Http\Controllers\Api\V1\IniciativaController;
use App\Http\Controllers\Api\V1\InformeController;
use App\Http\Controllers\Api\V1\PlanAccionController;
use App\Http\Controllers\Api\V1\PushController;
use App\Http\Controllers\Api\V1\CronController;
use App\Http\Controllers\Api\V1\Admin\MetricasController;
use App\Http\Controllers\Api\V1\Admin\RegistrosController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ─── Rutas públicas ──────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('registro',        [AuthController::class, 'registro']);
        Route::post('login',           [AuthController::class, 'login']);
        Route::post('invitado',        [AuthController::class, 'invitado']);
        Route::post('invitado/migrar', [AuthController::class, 'migrarInvitado']);
    });

    // Clave pública VAPID — segura de exponer, el frontend la necesita para suscribir
    Route::get('push/vapid-public-key', [PushController::class, 'vapidPublicKey']);

    // ─── Cron protegido por X-Cron-Secret (sin auth:sanctum) ──────────────────
    Route::middleware('cron.secret')->prefix('cron')->group(function () {
        Route::post('recordatorios-diarios', [CronController::class, 'recordatoriosDiarios']);
        Route::post('resumen-semanal',       [CronController::class, 'resumenSemanal']);
    });

    // ─── Rutas autenticadas ───────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me',      [AuthController::class, 'me']);
        });

        // Empresas
        Route::apiResource('empresas', EmpresaController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        // Iniciativas (anidadas bajo empresa y como recurso independiente)
        Route::get('empresas/{empresa}/iniciativas',  [IniciativaController::class, 'index']);
        Route::post('empresas/{empresa}/iniciativas', [IniciativaController::class, 'store']);
        Route::get('empresas/{empresa}/matriz',       [IniciativaController::class, 'matriz']);

        Route::get('iniciativas/{iniciativa}',    [IniciativaController::class, 'show']);
        Route::put('iniciativas/{iniciativa}',    [IniciativaController::class, 'update']);
        Route::delete('iniciativas/{iniciativa}', [IniciativaController::class, 'destroy']);

        // Informe IA
        Route::post('empresas/{empresa}/informe',       [InformeController::class, 'generar']);
        Route::get('empresas/{empresa}/informe/ultimo', [InformeController::class, 'ultimo']);

        // Planes de acción
        Route::post('iniciativas/{iniciativa}/plan',   [PlanAccionController::class, 'store']);
        Route::get('iniciativas/{iniciativa}/plan',    [PlanAccionController::class, 'show']);
        Route::put('planes/{plan}',                    [PlanAccionController::class, 'update']);
        Route::delete('planes/{plan}',                 [PlanAccionController::class, 'destroy']);

        // Notificaciones push — gestión de suscripciones del dispositivo
        Route::post('push/suscribir',    [PushController::class, 'suscribir']);
        Route::delete('push/desuscribir', [PushController::class, 'desuscribir']);
        Route::post('push/probar',        [PushController::class, 'probar']);
    });

    // ─── Rutas de admin ───────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', \App\Http\Middleware\AdminMiddleware::class])
        ->prefix('admin')
        ->group(function () {
            // KPIs — nuevo
            Route::get('metricas/kpis',            [MetricasController::class, 'kpis']);
            // Crecimiento temporal
            Route::get('metricas/usuarios',        [MetricasController::class, 'usuarios']);
            // Demográficos (con filtros)
            Route::get('metricas/demograficos',    [MetricasController::class, 'demograficos']);
            // Palabras clave (con filtros, cuadrante dinámico)
            Route::get('metricas/palabras-clave',  [MetricasController::class, 'palabrasClave']);
            // Matriz IGO agregada (con filtros)
            Route::get('metricas/matriz-agregada', [MetricasController::class, 'matrizAgregada']);
            // Tabla paginada de iniciativas — nuevo
            Route::get('metricas/iniciativas',     [MetricasController::class, 'iniciativas']);
            // Exportar CSV / Excel (con filtros)
            Route::get('exportar',                 [MetricasController::class, 'exportar']);
            // Registros históricos unificados
            Route::get('registros',                [RegistrosController::class, 'index']);
            Route::get('registros/{id}',           [RegistrosController::class, 'show']);
        });
});
