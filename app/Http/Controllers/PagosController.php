<?php

namespace App\Http\Controllers;

use App\Models\CertificadoPago;
use App\Models\LineaPago;
use App\Models\PagoWebdox;
use App\Support\CertificadosPago;
use App\Support\SincronizarPagos;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/** Módulo «Pagos a proveedores»: solicitudes de Webdox de la UDT y las facturas de sus certificados de pago. */
class PagosController extends Controller
{
    public function index(): Response
    {
        $certificados = CertificadoPago::withCount('lineas')->withSum('lineas', 'total')->get()->keyBy('webdox_id');

        return Inertia::render('Pagos', [
            'pagos' => PagoWebdox::where('tipo', 'pago')->orderByDesc('creada_en')->get()->map(fn (PagoWebdox $p) => [
                ...$p->paraInterfaz(),
                'departamento' => $p->departamento,
                'certificado' => ($c = $certificados[$p->webdox_id] ?? null) ? [
                    'numero' => $c->numero, 'lineas' => $c->lineas_count, 'total' => (int) $c->lineas_sum_total, 'archivo' => $c->archivo,
                ] : null,
            ])->all(),
            'lineas' => LineaPago::with('certificado:id,numero,fecha')->orderByDesc('webdox_id')->orderBy('n')->get()
                ->map(fn (LineaPago $l) => [
                    'id' => $l->id, 'webdox_id' => $l->webdox_id, 'numero' => $l->certificado?->numero,
                    'fecha' => $l->certificado?->fecha?->format('Y-m-d'), 'proveedor' => $l->proveedor, 'rut' => $l->rut,
                    'factura' => $l->factura, 'neto' => $l->neto, 'iva' => $l->iva, 'exento' => $l->exento, 'total' => $l->total,
                    'cuenta' => $l->cuenta_contable, 'centro_costo' => $l->centro_costo, 'obs' => $l->observacion,
                ])->all(),
            'estados' => PagoWebdox::ESTADOS,
            'sincronizado' => PagoWebdox::where('tipo', 'pago')->max('sincronizado_en'),
            'origen' => config('services.webdox.pagos'),
        ]);
    }

    /** Una solicitud «Pago a proveedores»: todos sus campos de Webdox y las facturas de su certificado de pago. */
    public function show(int $webdoxId): Response
    {
        $p = PagoWebdox::where('tipo', 'pago')->where('webdox_id', $webdoxId)->firstOrFail();
        $c = CertificadoPago::with('lineas')->where('webdox_id', $webdoxId)->first();

        return Inertia::render('PagoDetalle', [
            'solicitud' => [...$p->paraInterfaz(), 'departamento' => $p->departamento],
            'estados' => PagoWebdox::ESTADOS,
            'certificado' => $c ? [
                'numero' => $c->numero, 'fecha' => $c->fecha?->format('Y-m-d'), 'archivo' => $c->archivo,
                'descargable' => is_file($c->ruta),
                'lineas' => $c->lineas->map(fn (LineaPago $l) => [
                    'id' => $l->id, 'n' => $l->n, 'proveedor' => $l->proveedor, 'rut' => $l->rut, 'factura' => $l->factura,
                    'neto' => $l->neto, 'iva' => $l->iva, 'exento' => $l->exento, 'total' => $l->total,
                    'cuenta' => $l->cuenta_contable, 'unidad' => $l->unidad, 'centro_costo' => $l->centro_costo, 'obs' => $l->observacion,
                ])->all(),
            ] : null,
        ]);
    }

    /** Descarga el Excel del certificado desde la carpeta compartida (la API de Webdox no permite bajar los adjuntos). */
    public function excel(int $webdoxId)
    {
        $c = CertificadoPago::where('webdox_id', $webdoxId)->firstOrFail();
        abort_unless(is_file($c->ruta), 404);

        return response()->download($c->ruta, $c->archivo);
    }

    /** Trae lo reciente desde Webdox (la carga completa se hace con «php artisan webdox:sincronizar --todo»). */
    public function sincronizar()
    {
        set_time_limit(300);
        try {
            $r = (new SincronizarPagos())->ejecutar();
        } catch (Throwable $e) {
            Log::warning('Webdox: falló la sincronización de pagos', ['error' => $e->getMessage()]);

            return back()->with('error', 'No se pudo sincronizar con Webdox. Revise la conexión o los datos WEBDOX_* del .env.');
        }

        return back()->with('ok', "Sincronizado con Webdox: {$r['nuevas']} nuevas, {$r['actualizadas']} actualizadas");
    }

    /** Lee de la carpeta compartida los certificados nuevos o modificados. */
    public function leerCertificados()
    {
        $carpeta = config('services.webdox.pagos.certificados');
        if (! $carpeta) {
            return back()->with('error', 'Falta WEBDOX_CERTIFICADOS_DIR en el .env (carpeta compartida de certificados).');
        }
        set_time_limit(300);
        try {
            $r = (new CertificadosPago())->importarCarpeta($carpeta);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        $msg = "Certificados: {$r['leidos']} leídos, {$r['sin_cambios']} sin cambios";

        return $r['errores']
            ? back()->with('error', $msg . '. Con problemas: ' . implode('; ', array_map(fn ($a, $e) => "$a ($e)", array_keys($r['errores']), $r['errores'])))
            : back()->with('ok', $msg);
    }
}
