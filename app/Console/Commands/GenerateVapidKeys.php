<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'push:generate-vapid';

    protected $description = 'Genera un par de claves VAPID para Web Push y muestra cómo configurarlas';

    public function handle(): int
    {
        $this->info('Generando claves VAPID...');

        $keys = VAPID::createVapidKeys();

        $this->newLine();
        $this->line('<fg=green;options=bold>✓ Claves VAPID generadas correctamente.</>');
        $this->newLine();
        $this->line('Copia estas variables a tu archivo .env y al panel de Railway:');
        $this->newLine();

        $this->line('VAPID_PUBLIC_KEY=' . $keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY=' . $keys['privateKey']);
        $this->line('VAPID_SUBJECT=mailto:admin@igomanager.com');

        $this->newLine();
        $this->warn('IMPORTANTE:');
        $this->line('  • La clave PÚBLICA se comparte con el frontend (es segura de exponer).');
        $this->line('  • La clave PRIVADA es secreta: nunca la subas a git ni la expongas.');
        $this->line('  • Si regeneras las claves, todas las suscripciones existentes dejarán de funcionar.');

        return self::SUCCESS;
    }
}
