<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Log de queries lentas — solo en debug mode
        // Threshold: 300ms (≈ mitad del RTT promedio a Neon)
        if (config('app.debug')) {
            DB::listen(function ($query) {
                if ($query->time > 300) {
                    Log::warning('Query lenta detectada', [
                        'tiempo_ms' => round($query->time, 1),
                        'sql'       => $query->sql,
                        'bindings'  => $query->bindings,
                    ]);
                }
            });
        }
    }
}
