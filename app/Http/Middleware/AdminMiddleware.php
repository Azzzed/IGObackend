<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->email !== config('app.admin_email')) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado. Se requiere rol de administrador.',
                'errors'  => [],
            ], 403);
        }

        return $next($request);
    }
}
