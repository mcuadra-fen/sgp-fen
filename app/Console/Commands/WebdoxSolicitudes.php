<?php

namespace App\Console\Commands;

use App\Support\Webdox;
use Illuminate\Console\Command;
use Throwable;

/** Explora las solicitudes de Webdox (Pago a proveedores, Traspaso de recursos, etc.). Solo lectura. */
class WebdoxSolicitudes extends Command
{
    protected $signature = 'webdox:solicitudes
        {--unidad= : Texto que debe aparecer en el nombre, p. ej. "Desarrollo Tecnológico"}
        {--formulario= : Formulario, p. ej. "Pago a proveedores"}
        {--estado= : requested, taken, finished, canceled, rejected, in_review, under_modification}
        {--desde= : Creadas desde AAAA-mm-dd}
        {--hasta= : Creadas hasta AAAA-mm-dd}
        {--max=20 : Cuántas mostrar}
        {--ver= : ID de una solicitud para ver sus campos}';

    protected $description = 'Lista las solicitudes de Webdox que puede ver el usuario del .env';

    private const ESTADOS = ['requested' => 'Solicitada', 'taken' => 'En curso', 'finished' => 'Finalizada', 'canceled' => 'Cancelada',
        'rejected' => 'Rechazada', 'in_review' => 'En revisión', 'under_modification' => 'En modificación'];

    public function handle(): int
    {
        $w = new Webdox();
        try {
            return $this->option('ver') ? $this->detalle($w, $this->option('ver')) : $this->lista($w);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $w->revocar();
        }
    }

    private function lista(Webdox $w): int
    {
        $max = (int) $this->option('max');
        $unidad = mb_strtolower((string) $this->option('unidad'));
        $form = mb_strtolower((string) $this->option('formulario'));
        $filtros = array_filter([
            'status' => $this->option('estado'),
            'created_start_date' => $this->option('desde'),
            'created_end_date' => $this->option('hasta'),
        ]);

        // La API no filtra por nombre ni formulario: se recorren las páginas (25 por página) y se filtra aquí
        $filas = [];
        for ($p = 1; $p <= 200 && count($filas) < $max; $p++) {
            $l = $w->solicitudes($filtros, $p);
            if (! $l) {
                break;
            }
            foreach ($l as $s) {
                if (($unidad && ! str_contains(mb_strtolower($s['title'] ?? ''), $unidad))
                    || ($form && ! str_contains(mb_strtolower($s['request_form_name'] ?? ''), $form))) {
                    continue;
                }
                $filas[] = [$s['id'], self::ESTADOS[$s['status']] ?? $s['status'], substr((string) $s['created_at'], 0, 10),
                    $s['request_form_name'] ?? '', mb_strimwidth((string) ($s['title'] ?? ''), 0, 70, '…')];
                if (count($filas) >= $max) {
                    break;
                }
            }
        }
        $this->table(['ID', 'Estado', 'Creada', 'Formulario', 'Nombre'], $filas);
        $this->line(count($filas) . ' solicitudes. Detalle: php artisan webdox:solicitudes --ver=ID');

        return self::SUCCESS;
    }

    private function detalle(Webdox $w, string $id): int
    {
        $r = $w->get("workflow_requests/$id");
        if (! $r->successful()) {
            $this->error("HTTP {$r->status()}: " . mb_strimwidth($r->body(), 0, 400, '…'));

            return self::FAILURE;
        }
        $d = $r->json();
        $this->info("{$d['id']} · " . (self::ESTADOS[$d['status']] ?? $d['status']) . ' · ' . substr((string) $d['created_at'], 0, 10));
        $this->line((string) $d['title']);
        foreach ($d['request_form_attributes'] ?? [] as $a) {
            $v = $a['attribute_value'];
            $this->line('  - ' . $a['attribute_name'] . ': ' . mb_strimwidth(is_scalar($v) || $v === null ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE), 0, 90, '…'));
        }
        $this->line('  Adjuntos: ' . count($d['attachments'] ?? []));

        return self::SUCCESS;
    }
}
