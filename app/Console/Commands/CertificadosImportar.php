<?php

namespace App\Console\Commands;

use App\Support\CertificadosPago;
use Illuminate\Console\Command;
use Throwable;

class CertificadosImportar extends Command
{
    protected $signature = 'certificados:importar {carpeta? : Por defecto WEBDOX_CERTIFICADOS_DIR del .env}';

    protected $description = 'Lee los Excel «ID####_Certificado de Pago.xlsx» de la carpeta compartida (solo los nuevos o modificados)';

    public function handle(): int
    {
        $carpeta = $this->argument('carpeta') ?: config('services.webdox.pagos.certificados');
        if (! $carpeta) {
            $this->error('Indique la carpeta o complete WEBDOX_CERTIFICADOS_DIR en el .env');

            return self::FAILURE;
        }
        try {
            $r = (new CertificadosPago())->importarCarpeta($carpeta);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Listo: {$r['leidos']} certificados leídos, {$r['sin_cambios']} sin cambios.");
        foreach ($r['errores'] as $archivo => $error) {
            $this->warn("  $archivo: $error");
        }

        return $r['errores'] ? self::FAILURE : self::SUCCESS;
    }
}
