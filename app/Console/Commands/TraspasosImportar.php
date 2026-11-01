<?php

namespace App\Console\Commands;

use App\Support\Traspasos;
use Illuminate\Console\Command;
use Throwable;

class TraspasosImportar extends Command
{
    protected $signature = 'traspasos:importar {carpeta? : Por defecto WEBDOX_CERTIFICADOS_DIR del .env}';

    protected $description = 'Lee los comprobantes de traspaso de recursos (T2026…pdf o .txt) de la carpeta compartida (solo los nuevos o modificados)';

    public function handle(): int
    {
        $carpeta = $this->argument('carpeta') ?: config('services.webdox.pagos.certificados');
        if (! $carpeta) {
            $this->error('Indique la carpeta o complete WEBDOX_CERTIFICADOS_DIR en el .env');

            return self::FAILURE;
        }
        try {
            $r = (new Traspasos())->importarCarpeta($carpeta);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Listo: {$r['leidos']} comprobantes leídos, {$r['sin_cambios']} sin cambios.");
        foreach ($r['errores'] as $archivo => $error) {
            $this->warn("  $archivo: $error");
        }

        return $r['errores'] ? self::FAILURE : self::SUCCESS;
    }
}
