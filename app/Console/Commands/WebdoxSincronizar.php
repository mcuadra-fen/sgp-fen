<?php

namespace App\Console\Commands;

use App\Support\SincronizarPagos;
use Illuminate\Console\Command;
use Throwable;

class WebdoxSincronizar extends Command
{
    protected $signature = 'webdox:sincronizar {--todo : Recorre todas las solicitudes, no solo las recientes}';

    protected $description = 'Trae desde Webdox las solicitudes «Pago a proveedores» de la UDT al módulo de pagos';

    public function handle(): int
    {
        $this->line('Sincronizando con Webdox' . ($this->option('todo') ? ' (todas las solicitudes; puede tardar unos minutos)…' : '…'));
        try {
            $r = (new SincronizarPagos())->ejecutar((bool) $this->option('todo'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Listo: {$r['nuevas']} nuevas, {$r['actualizadas']} actualizadas ({$r['revisadas']} solicitudes revisadas en Webdox).");

        return self::SUCCESS;
    }
}
