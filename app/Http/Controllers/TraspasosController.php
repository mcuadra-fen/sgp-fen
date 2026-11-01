<?php

namespace App\Http\Controllers;

use App\Models\PagoWebdox;
use App\Models\Traspaso;
use App\Support\CobrosUdt;
use App\Support\Traspasos;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Módulo «Cobros a áreas»: lo que la UDT cobra a las otras áreas. Solicitudes de Webdox con formulario «Traspaso (Cobros DEA)»
 * de la UDT, y los montos desde sus comprobantes contables en la carpeta compartida (la carpeta también tiene comprobantes
 * de otros formularios: solo se muestran los que tienen una de estas solicitudes).
 */
class TraspasosController extends Controller
{
    public function index(): Response
    {
        $traspasos = Traspaso::withCount('lineas')->get()->groupBy('webdox_id');
        $excels = (new Traspasos())->excels((string) config('services.webdox.pagos.certificados'));

        return Inertia::render('Traspasos', [
            'lineas' => CobrosUdt::lineas(),
            'solicitudes' => PagoWebdox::where('tipo', 'traspaso')->orderByDesc('creada_en')->get()->map(fn (PagoWebdox $p) => [
                ...$p->paraInterfaz(),
                ...self::campos($p),
                'comprobantes' => ($t = $traspasos[$p->webdox_id] ?? null) ? $t->count() : 0,
                'total_comprobantes' => $t ? (int) $t->where('sentido', 'cobro')->sum('total') : null,
                'excel' => isset($excels[$p->webdox_id]) ? basename($excels[$p->webdox_id]) : null,
            ])->all(),
            'estados' => PagoWebdox::ESTADOS,
            'sincronizado' => PagoWebdox::where('tipo', 'traspaso')->max('sincronizado_en'),
        ]);
    }

    /** Una solicitud: todos sus campos de Webdox y el resumen del Excel de detalle (si está en la carpeta compartida). */
    public function show(int $webdoxId): Response
    {
        $p = PagoWebdox::where('tipo', 'traspaso')->where('webdox_id', $webdoxId)->firstOrFail();
        $excel = (new Traspasos())->excels((string) config('services.webdox.pagos.certificados'))[$webdoxId] ?? null;
        try {
            $resumen = $excel ? (new Traspasos())->resumenExcel($excel) : null;
            $error = null;
        } catch (Throwable $e) {
            $resumen = null;
            $error = 'No se pudo leer el Excel: ' . $e->getMessage();
        }

        return Inertia::render('TraspasoDetalle', [
            'solicitud' => [...$p->paraInterfaz(), ...self::campos($p)],
            'estados' => PagoWebdox::ESTADOS,
            'excel' => $excel ? basename($excel) : null,
            'resumen' => $resumen,
            'error' => $error,
            'comprobantes' => Traspaso::where('webdox_id', $webdoxId)->where('sentido', 'cobro')->orderBy('fecha')
                ->get(['comprobante', 'fecha', 'glosa', 'total', 'archivo'])->map(fn ($t) => [
                    'comprobante' => $t->comprobante, 'fecha' => $t->fecha?->format('Y-m-d'), 'glosa' => $t->glosa, 'total' => $t->total, 'archivo' => $t->archivo,
                ])->all(),
        ]);
    }

    /** Descarga el Excel de detalle desde la carpeta compartida (la API de Webdox no permite bajar los adjuntos). */
    public function excel(int $webdoxId)
    {
        $excel = (new Traspasos())->excels((string) config('services.webdox.pagos.certificados'))[$webdoxId] ?? abort(404);

        return response()->download($excel);
    }

    /** Campos del formulario «Traspaso (Cobros DEA)» con nombre propio (el resto queda en «atributos»). */
    private static function campos(PagoWebdox $p): array
    {
        $a = $p->atributos ?? [];
        $v = fn (string $k) => ($x = $a[$k] ?? null) !== null && $x !== '' && $x !== '-' && is_scalar($x) ? (string) $x : null;
        $fecha = $v('Fecha de memo');

        return [
            'descripcion' => $v('Descripción de traspaso'),
            'correlativo' => $v('Correlativo'),
            'fecha_memo' => $fecha ? \Carbon\Carbon::parse($fecha)->setTimezone('America/Santiago')->toDateString() : null,
            'unidad' => $v('Unidad'),
            'solicitante_nombre' => $v('Nombre de solicitante'),
            'aprobador' => $v('Aprobador(a) de traspaso'),
            'cargo_aprobador' => $v('Cargo de aprobador(a) de pago'),
            'ceco_ingreso' => $v('CECO ingreso del traspaso'),
            // «52.098.058» → 52098058
            'total' => ($t = $v('Total de traspaso')) !== null ? (int) preg_replace('/\D/', '', $t) : null,
        ];
    }

    /** Lee de la carpeta compartida los comprobantes nuevos o modificados. */
    public function leer()
    {
        $carpeta = config('services.webdox.pagos.certificados');
        if (! $carpeta) {
            return back()->with('error', 'Falta WEBDOX_CERTIFICADOS_DIR en el .env (carpeta compartida).');
        }
        set_time_limit(300);
        try {
            $r = (new Traspasos())->importarCarpeta($carpeta);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        $msg = "Comprobantes: {$r['leidos']} leídos, {$r['sin_cambios']} sin cambios";

        return $r['errores']
            ? back()->with('error', $msg . '. Con problemas: ' . implode('; ', array_map(fn ($a, $e) => "$a ($e)", array_keys($r['errores']), $r['errores'])))
            : back()->with('ok', $msg);
    }
}
