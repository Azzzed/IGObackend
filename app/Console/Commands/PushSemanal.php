<?php

namespace App\Console\Commands;

use App\Services\NotificacionService;
use Illuminate\Console\Command;

class PushSemanal extends Command
{
    protected $signature = 'push:semanal';

    protected $description = 'Envía el resumen push semanal (iniciativas de la semana)';

    public function handle(NotificacionService $service): int
    {
        $this->info('Procesando resumen semanal...');

        $notificados = $service->resumenSemanal();

        $this->info("Usuarios notificados: {$notificados}");

        return self::SUCCESS;
    }
}
