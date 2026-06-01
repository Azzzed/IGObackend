<?php

namespace App\Console\Commands;

use App\Services\NotificacionService;
use Illuminate\Console\Command;

class PushDiario extends Command
{
    protected $signature = 'push:diario';

    protected $description = 'Envía los recordatorios push diarios (acciones que tocan hoy)';

    public function handle(NotificacionService $service): int
    {
        $this->info('Procesando recordatorios diarios...');

        $notificados = $service->recordatoriosDiarios();

        $this->info("Usuarios notificados: {$notificados}");

        return self::SUCCESS;
    }
}
