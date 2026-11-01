<?php

namespace App\Support;

use App\Models\PagoWebdox;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Trae desde Webdox las solicitudes del formulario y la unidad configurados (por defecto «Pago a proveedores» y
 * «Traspaso de recursos» de la Unidad de Desarrollo Tecnológico) y las guarda en pagos_webdox.
 *
 * La API no filtra por formulario ni por nombre: se recorren las páginas y se filtra aquí. Solo se pide el
 * detalle de las solicitudes nuevas o que aún no terminan, para gastar pocas llamadas (límite: 60 por minuto).
 */
class SincronizarPagos
{
    public function __construct(private Webdox $webdox = new Webdox()) {}

    /**
     * @param  bool  $todo  recorrer todas las solicitudes; si no, solo las creadas en los últimos 60 días
     *                      desde la más reciente guardada (y se actualizan las que siguen abiertas)
     * @return array{nuevas:int, actualizadas:int, revisadas:int}
     */
    public function ejecutar(bool $todo = false): array
    {
        $cfg = config('services.webdox.pagos');
        // formulario en minúsculas => tipo guardado
        $formularios = [Str::lower($cfg['formulario']) => 'pago'];
        foreach (explode('|', $cfg['formulario_traspasos']) as $f) {
            $formularios[Str::lower(trim($f))] = 'traspaso';
        }
        $unidad = Str::lower($cfg['unidad']);

        $filtros = [];
        $ultima = PagoWebdox::max('creada_en');
        if (! $todo && $ultima) {
            $filtros['created_start_date'] = Carbon::parse($ultima)->subDays(60)->toDateString();
        }

        $conocidos = PagoWebdox::pluck('estado', 'webdox_id')->all();
        $sinAdjuntos = PagoWebdox::whereNull('adjuntos')->pluck('webdox_id')->flip()->all();
        $vistos = [];
        $nuevas = $actualizadas = $revisadas = 0;

        try {
            for ($p = 1; $p <= 500; $p++) {
                $pagina = $this->webdox->solicitudes($filtros, $p);
                if (! $pagina) {
                    break;
                }
                foreach ($pagina as $s) {
                    $revisadas++;
                    $tipo = $formularios[Str::lower($s['request_form_name'] ?? '')] ?? null;
                    if (! $tipo || ! Str::contains(Str::lower($s['title'] ?? ''), $unidad)) {
                        continue;
                    }
                    $id = (int) $s['id'];
                    $vistos[$id] = true;
                    $antes = $conocidos[$id] ?? null;
                    // Ya guardada, terminada, sin cambio de estado y con sus adjuntos: nada que pedir
                    if ($antes !== null && $antes === $s['status'] && in_array($antes, PagoWebdox::FINALES, true) && ! isset($sinAdjuntos[$id])) {
                        continue;
                    }
                    $this->guardar($s, tipo: $tipo);
                    $antes === null ? $nuevas++ : $actualizadas++;
                }
            }

            // Abiertas que no aparecieron en el recorrido (más antiguas que el rango): se consultan una a una
            $pendientes = array_diff_key(array_filter($conocidos, fn ($e) => ! in_array($e, PagoWebdox::FINALES, true)), $vistos);
            foreach (array_keys($pendientes) as $id) {
                $r = $this->webdox->get("workflow_requests/$id");
                if ($r->successful()) {
                    $this->guardar($r->json(), detalle: $r->json());
                    $actualizadas++;
                }
            }
        } finally {
            $this->webdox->revocar();
        }

        return compact('nuevas', 'actualizadas', 'revisadas');
    }

    private function guardar(array $s, ?array $detalle = null, ?string $tipo = null): void
    {
        $detalle ??= $this->webdox->get('workflow_requests/' . $s['id'])->throw()->json();

        $campos = [];
        foreach ($detalle['request_form_attributes'] ?? [] as $a) {
            $campos[$a['attribute_name']] = $a['attribute_value'];
        }
        $campo = function (string $empieza) use ($campos) {
            foreach ($campos as $k => $v) {
                if (Str::startsWith(Str::lower($k), Str::lower($empieza))) {
                    return is_scalar($v) && $v !== '' && $v !== 'null' ? (string) $v : null;
                }
            }

            return null;
        };
        $persona = fn ($p) => is_array($p) ? trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?: null : null;
        $fechaCert = $campo('Fecha para Certificado');

        PagoWebdox::updateOrCreate(['webdox_id' => (int) $s['id']], [
            'titulo' => (string) ($detalle['title'] ?? $s['title']),
            'estado' => (string) ($detalle['status'] ?? $s['status']),
            'formulario' => $s['request_form_name'] ?? config('services.webdox.pagos.formulario'),
            ...($tipo ? ['tipo' => $tipo] : []),
            'creada_en' => Carbon::parse($detalle['created_at'] ?? $s['created_at'])->setTimezone(config('app.timezone')),
            'solicitante' => $persona($detalle['requester'] ?? $s['requester'] ?? null),
            'asignado' => $persona($detalle['assigned'] ?? $s['assigned'] ?? null),
            'aprobador' => $campo('Aprobador de pago'),
            'departamento' => $campo('Departamento'),
            'correlativo' => $campo('Correlativo para Certificado'),
            // Webdox guarda la fecha como medianoche de Chile en UTC (03:00Z): se toma solo el día local
            'fecha_certificado' => $fechaCert ? Carbon::parse($fechaCert)->setTimezone('America/Santiago')->toDateString() : null,
            'cantidad_documentos' => is_numeric($campo('Cantidad de documentos')) ? (int) $campo('Cantidad de documentos') : null,
            'fecha_pago_requerida' => $campo('Fecha de pago requerida'),
            'link_antecedentes' => $campo('Link de repositorio'),
            'n_adjuntos' => count($detalle['attachments'] ?? []),
            'adjuntos' => array_map(fn ($a) => ['id' => $a['id'] ?? null, 'nombre' => $a['name'] ?? null, 'tipo' => $a['file_ext'] ?? null, 'peso' => $a['file_size'] ?? null], $detalle['attachments'] ?? []),
            'atributos' => $campos,
            'sincronizado_en' => now(),
        ]);
    }
}
