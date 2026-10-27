<?php

namespace App\Http\Controllers;

use App\Models\Partida;
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
        ]);
    }

    /** Lee el texto pegado y muestra cuántas filas son válidas, sin guardar nada. */
    public function revisar(Request $request)
    {
        $p = Presupuesto::parsePegado((string) $request->input('texto', ''));

        return back()->with('preview', $this->resumenDe($p));
    }

    public function importar(Request $request)
    {
        $data = $request->validate([
            'texto' => ['required', 'string', 'max:2000000'],
            'modo' => ['required', 'in:agregar,reemplazar'],
        ], [
            'texto.required' => 'Pegue primero las filas de su planilla.',
        ]);

        $p = Presupuesto::parsePegado($data['texto']);
        if (! $p['filas']) {
            return back()->with('preview', $this->resumenDe($p))->with('error', 'No hay filas válidas para importar.');
        }

        DB::transaction(function () use ($p, $data) {
            if ($data['modo'] === 'reemplazar') {
                Partida::query()->delete(); // borrado lógico
            }
            foreach ($p['filas'] as $f) {
                $partida = new Partida([
                    'dpto' => $f['dpto'], 'unidad' => $f['unidad'], 'actividad' => $f['actividad'],
                    'subactividad' => $f['subactividad'], 'sigma' => $f['sigma'], 'cuenta' => $f['cuenta'],
                    'dep_codigo' => $f['dep_codigo'] ?: null, 'un_codigo' => $f['un_codigo'] ?: null,
                ]);
                $partida->setMeses($f['meses'])->save();
            }
        });

        $n = count($p['filas']);

        return redirect()->route('partidas.index')
            ->with('ok', "$n filas " . ($data['modo'] === 'reemplazar' ? 'cargadas, reemplazando lo anterior' : 'agregadas'));
    }

    /** Descarga real (enlace normal, no navegación Inertia). */
    public function exportar()
    {
        $tsv = $this->tsv();

        return response()->streamDownload(
            function () use ($tsv) {
                echo "\xEF\xBB\xBF" . $tsv; // BOM para que Excel lea bien las tildes
            },
            'presupuesto-udt.tsv',
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
        return Presupuesto::toTsv(Partida::orderBy('id')->get()->map->comoFila()->all());
    }

    /** Solo lo que la interfaz necesita mostrar (no se envían todas las filas leídas). */
    private function resumenDe(array $p): array
    {
        return [
            'validas' => count($p['filas']),
            'avisos' => $p['avisos'],
            'errores' => array_slice($p['errores'], 0, 3),
        ];
    }
}
