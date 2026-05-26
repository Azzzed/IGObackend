<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Todas las rutas bajo api/* siempre devuelven JSON — nunca HTML
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Los datos enviados no son válidos.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado. Token requerido.',
                    'errors'  => [],
                ], 401);
            }
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recurso no encontrado.',
                    'errors'  => [],
                ], 404);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Error en la solicitud.',
                    'errors'  => [],
                ], $e->getStatusCode());
            }
        });
    })->create();
