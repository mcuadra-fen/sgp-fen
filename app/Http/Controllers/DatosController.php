<?php

namespace App\Http\Controllers;

use App\Models\Partida;
use App\Support\Anio;
use App\Support\Presupuesto;
use Database\Seeders\PartidaSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DatosController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Datos', [
            'tsv' => $this->tsv(),
            'preview' => session('preview'),
            // [año, cantidad de partidas], para la lista de años cargados
            'cargados' => Partida::query()->selectRaw('anio, COUNT(*) as n')->groupBy('anio')->orderByDesc('anio')->get()
                ->map(fn ($r) => [(int) $r->anio, (int) $r->n])->all(),
        ]);
    }

    /** Lee el texto pegado y muestra cuántas filas son válidas, sin guardar nada. */
    public function revisar(Request $request)
    {
        $p = Presupuesto::parsePegado((string) $request->input('texto', ''));
        $anio = filter_var($request->input('anio'), FILTER_VALIDATE_INT, ['options' => ['min_range' => Anio::MIN, 'max_range' => Anio::MAX]]);

        return back()->with('preview', $this->resumenDe($p, $anio ?: null));
    }

    public function importar(Request $request)
    {
        $data = $request->validate([
            'texto' => ['required', 'string', 'max:2000000'],
            'modo' => ['required', 'in:agregar,reemplazar'],
            'anio' => ['required', 'integer', 'between:' . Anio::MIN . ',' . Anio::MAX],
        ], [
            'texto.required' => 'Pegue primero las filas de su planilla.',
            'anio.required' => 'Indique el año al que corresponde la planilla.',
            'anio.integer' => 'El año debe ser un número, por ejemplo 2026.',
            'anio.between' => 'El año debe estar entre ' . Anio::MIN . ' y ' . Anio::MAX . '.',
        ]);
        $anio = (int) $data['anio'];

        $p = Presupuesto::parsePegado($data['texto']);
        if (! $p['filas']) {
            return back()->with('preview', $this->resumenDe($p, $anio))->with('error', 'No hay filas válidas para importar.');
        }

        DB::transaction(function () use ($p, $data, $anio) {
            if ($data['modo'] === 'reemplazar') {
                Partida::delAnio($anio)->delete(); // borrado lógico, solo de ese año
            }
            foreach ($p['filas'] as $f) {
                $partida = new Partida([
                    'anio' => $anio,
                    'dpto' => $f['dpto'], 'unidad' => $f['unidad'], 'actividad' => $f['actividad'],
                    'subactividad' => $f['subactividad'], 'sigma' => $f['sigma'], 'cuenta' => $f['cuenta'],
                    'dep_codigo' => $f['dep_codigo'] ?: null, 'un_codigo' => $f['un_codigo'] ?: null,
                ]);
                $partida->setMeses($f['meses'])->save();
            }
        });
        Anio::elegir($anio);

        $n = count($p['filas']);

        return redirect()->route('partidas.index')
            ->with('ok', "$n filas " . ($data['modo'] === 'reemplazar' ? "cargadas en $anio, reemplazando lo anterior de ese año" : "agregadas a $anio"));
    }

    /** Cambia el año que se ve en todas las pestañas. */
    public function elegirAnio(Request $request)
    {
        $data = $request->validate(['anio' => ['required', 'integer', 'between:' . Anio::MIN . ',' . Anio::MAX]]);
        Anio::elegir((int) $data['anio']);

        return back();
    }

    /** Borrado lógico de todas las partidas de un año; se puede deshacer desde el aviso. */
    public function eliminarAnio(int $anio)
    {
        $n = Partida::delAnio($anio)->count();
        if (! $n) {
            return back()->with('error', "No hay partidas cargadas en $anio.");
        }

        // Todas con la misma marca de tiempo, para que «Deshacer» restaure solo estas
        $marca = now()->startOfSecond();
        Partida::delAnio($anio)->update(['deleted_at' => $marca]);

        return back()
            ->with('ok', "Año $anio eliminado ($n partidas)")
            ->with('undo', route('datos.anio.restaurar', ['anio' => $anio, 'marca' => $marca->format('Y-m-d H:i:s')], false));
    }

    public function restaurarAnio(Request $request, int $anio)
    {
        $marca = $request->validate(['marca' => ['required', 'date_format:Y-m-d H:i:s']])['marca'];
        $n = Partida::onlyTrashed()->where('anio', $anio)->where('deleted_at', $marca)->restore();
        Anio::elegir($anio);

        return back()->with('ok', "Año $anio restaurado ($n partidas)");
    }

    /** Descarga real (enlace normal, no navegación Inertia). */
    public function exportar()
    {
        $tsv = $this->tsv();

        return response()->streamDownload(
            function () use ($tsv) {
                echo "\xEF\xBB\xBF" . $tsv; // BOM para que Excel lea bien las tildes
            },
            'sgp-fen-' . Anio::actual() . '.tsv',
            ['Content-Type' => 'text/tab-separated-values; charset=UTF-8']
        );
    }

    public function quitarEjemplos()
    {
        $n = Partida::where('es_ejemplo', true)->count();
        Partida::where('es_ejemplo', true)->delete();

        return back()->with('ok', $n ? "$n filas de ejemplo quitadas" : 'No hay filas de ejemplo');
    }

    public function reiniciar()
    {
        DB::transaction(function () {
            Partida::withTrashed()->forceDelete();
            (new PartidaSeeder())->run();
        });

        return redirect()->route('datos')->with('ok', 'Datos iniciales restaurados');
    }

    private function tsv(): string
    {
        return Presupuesto::toTsv(Partida::delAnio()->orderBy('id')->get()->map->comoFila()->all());
    }

    /** Solo lo que la interfaz necesita mostrar (no se envían todas las filas leídas). */
    private function resumenDe(array $p, ?int $anio = null): array
    {
        return [
            'validas' => count($p['filas']),
            'avisos' => $p['avisos'],
            'errores' => array_slice($p['errores'], 0, 3),
            'anio' => $anio,
            // partidas que ya hay en ese año: «Reemplazar» las enviaría a la papelera
            'existentes' => $anio ? Partida::delAnio($anio)->count() : 0,
        ];
    }
}
