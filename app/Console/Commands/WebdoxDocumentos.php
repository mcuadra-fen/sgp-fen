<?php

namespace App\Console\Commands;

use App\Support\Webdox;
use Illuminate\Console\Command;
use Throwable;

/** Explora qué documentos y atributos de Webdox ve el usuario del .env (solo lectura). */
class WebdoxDocumentos extends Command
{
    protected $signature = 'webdox:documentos
        {--buscar= : Parte del nombre del documento}
        {--tipo= : Tipo de documento (subject_name)}
        {--desde= : Creados desde AAAA-mm-dd}
        {--hasta= : Creados hasta AAAA-mm-dd}
        {--pagina=1}
        {--cantidad=10 : Documentos por página}
        {--ver= : ID de un documento para ver su detalle y atributos}
        {--atributos : Lista los atributos de documento definidos en Webdox}';

    protected $description = 'Lista los documentos de Webdox que puede ver el usuario configurado en el .env';

    public function handle(): int
    {
        $w = new Webdox();
        try {
            if ($this->option('atributos')) {
                return $this->atributos($w);
            }
            if ($id = $this->option('ver')) {
                return $this->detalle($w, $id);
            }

            $r = $w->documentos([
                'name' => $this->option('buscar'),
                'subject_name' => $this->option('tipo'),
                'created_at_after' => $this->option('desde'),
                'created_at_before' => $this->option('hasta'),
            ], (int) $this->option('pagina'), (int) $this->option('cantidad'));

            if (! $r->successful()) {
                $this->error("HTTP {$r->status()}: " . mb_strimwidth($r->body(), 0, 400, '…'));

                return self::FAILURE;
            }
            $docs = $this->lista($r->json());
            $this->info(count($docs) . ' documentos en la página ' . $this->option('pagina'));
            if (! $docs) {
                $this->line('Estructura de la respuesta: ' . mb_strimwidth(json_encode($r->json(), JSON_UNESCAPED_UNICODE), 0, 400, '…'));

                return self::SUCCESS;
            }
            $this->table(
                ['ID', 'Nombre', 'Creado', 'Término'],
                array_map(fn ($d) => [
                    $d['id'] ?? '',
                    mb_strimwidth((string) ($d['name'] ?? ''), 0, 60, '…'),
                    substr((string) ($d['created_at'] ?? ''), 0, 10),
                    substr((string) ($d['document_end_date'] ?? ''), 0, 10),
                ], $docs)
            );
            $this->line('Detalle de uno: php artisan webdox:documentos --ver=ID');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $w->revocar();
        }
    }

    private function detalle(Webdox $w, string $id): int
    {
        $r = $w->get("documents/$id");
        if (! $r->successful()) {
            $this->error("HTTP {$r->status()}: " . mb_strimwidth($r->body(), 0, 400, '…'));

            return self::FAILURE;
        }
        $d = $r->json();
        foreach (['id', 'name', 'folio', 'document_date', 'document_end_date', 'creator_name', 'attachment_file_name', 'workflow_id'] as $k) {
            $this->line(str_pad($k, 22) . (is_scalar($d[$k] ?? null) ? $d[$k] : json_encode($d[$k] ?? null)));
        }
        $this->info('Atributos (dynamic_attributes):');
        foreach ((array) ($d['dynamic_attributes'] ?? []) as $k => $a) {
            $nombre = is_array($a) ? ($a['name'] ?? $k) : $k;
            $valor = is_array($a) ? ($a['value'] ?? json_encode($a, JSON_UNESCAPED_UNICODE)) : $a;
            $this->line('  - ' . $nombre . ': ' . (is_scalar($valor) ? $valor : json_encode($valor, JSON_UNESCAPED_UNICODE)));
        }
        $this->line('Campos disponibles: ' . implode(', ', array_keys($d)));

        return self::SUCCESS;
    }

    private function atributos(Webdox $w): int
    {
        $r = $w->get('document_attributes', ['all' => 'true']);
        if (! $r->successful()) {
            $this->error("HTTP {$r->status()}: " . mb_strimwidth($r->body(), 0, 400, '…'));

            return self::FAILURE;
        }
        $this->table(['ID', 'Nombre', 'Tipo', 'Clasificable'], array_map(
            fn ($a) => [$a['id'] ?? '', $a['name'] ?? '', $a['type'] ?? ($a['type_cd'] ?? ''), ! empty($a['classifiable']) ? 'sí' : 'no'],
            $this->lista($r->json())
        ));
        $this->line('Filtrar por un atributo: filters[dynamic_attribute_<ID>]=valor');

        return self::SUCCESS;
    }

    /** La API entrega listas como arreglo; por si viniera envuelta, se aceptan las claves habituales. */
    private function lista(mixed $json): array
    {
        if (is_array($json) && array_is_list($json)) {
            return $json;
        }

        return (array) ($json['documents'] ?? $json['document_attributes'] ?? $json['data'] ?? []);
    }
}
