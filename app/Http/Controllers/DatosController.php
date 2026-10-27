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
            // [año, versión, cantidad de partidas], para la lista de años cargados
            'cargados' => Partida::query()->selectRaw('anio, version, COUNT(*) as n')->groupBy('anio', 'version')
                ->orderByDesc('anio')->orderBy('version')->get()
                ->map(fn ($r) => [(int) $r->anio, $r->version, (int) $r->n])->all(),
        ]);
    }

    /** Lee el texto pegado y muestra cuántas filas son válidas, sin guardar nada. */
    public function revisar(Request $request)
    {
        $p = Presupuesto::parsePegado((string) $request->input('texto', ''));
        $anio = filter_var($request->input('anio'), FILTER_VALIDATE_INT, ['options' => ['min_range' => Anio::MIN, 'max_range' => Anio::MAX]]);
        $version = $this->versionDe($request->input('version'));

        return back()->with('preview', $this->resumenDe($p, $anio ?: null, $version));
    }

    public function importar(Request $request)
    {
        $data = $request->validate([
            'texto' => ['required', 'string', 'max:2000000'],
            'modo' => ['required', 'in:agregar,reemplazar'],
            'anio' => ['required', 'integer', 'between:' . Anio::MIN . ',' . Anio::MAX],
            'version' => ['required', 'in:' . implode(',', array_keys(Anio::VERSIONES))],
            'completar' => ['boolean'],
        ], [
            'texto.required' => 'Pegue primero las filas de su planilla.',
            'anio.required' => 'Indique el año al que corresponde la planilla.',
            'anio.integer' => 'El año debe ser un número, por ejemplo 2026.',
            'anio.between' => 'El año debe estar entre ' . Anio::MIN . ' y ' . Anio::MAX . '.',
            'version.required' => 'Indique si la planilla es el presupuesto inicial o la proyección.',
            'version.in' => 'Indique si la planilla es el presupuesto inicial o la proyección.',
        ]);
        $anio = (int) $data['anio'];
        $version = $data['version'];

        $p = Presupuesto::parsePegado($data['texto']);
        if (! $p['filas']) {
            return back()->with('preview', $this->resumenDe($p, $anio, $version))->with('error', 'No hay filas válidas para importar.');
        }

        // La proyección suele traer solo julio a diciembre: enero a junio se toman del presupuesto inicial
        $completar = $version === 'proyeccion' && ($data['completar'] ?? false);
        $cruce = $completar ? $this->cruzarConInicial($p['filas'], $anio) : null;

        DB::transaction(function () use ($p, $data, $anio, $version, $cruce) {
            if ($data['modo'] === 'reemplazar') {
                Partida::delAnio($anio, $version)->delete(); // borrado lógico, solo de ese año y versión
            }
            foreach ($p['filas'] as $i => $f) {
                $partida = new Partida([
                    'anio' => $anio,
                    'version' => $version,
                    'dpto' => $f['dpto'], 'unidad' => $f['unidad'], 'actividad' => $f['actividad'],
                    'subactividad' => $f['subactividad'], 'sigma' => $f['sigma'], 'cuenta' => $f['cuenta'],
                    'dep_codigo' => $f['dep_codigo'] ?: null, 'un_codigo' => $f['un_codigo'] ?: null,
                ]);
                $meses = $f['meses'];
                if ($inicial = $cruce['pares'][$i] ?? null) {
                    for ($m = 0; $m < 6; $m++) {
                        $meses[$m] = $inicial->meses()[$m];
                    }
                    $partida->categoria_id = $inicial->categoria_id;
                }
                $partida->setMeses($meses)->save();
            }
        });
        Anio::elegir($anio, $version);

        $n = count($p['filas']);
        $donde = "$anio (" . mb_strtolower(Anio::VERSIONES[$version]) . ')';
        $msg = "$n filas " . ($data['modo'] === 'reemplazar' ? "cargadas en $donde, reemplazando lo anterior" : "agregadas a $donde");
        if ($cruce) {
            $msg .= '. Enero a junio tomados del presupuesto inicial en ' . count($cruce['pares']) . " de $n filas";
        }

        return redirect()->route('partidas.index')->with('ok', $msg);
    }

    /** Cambia el año (y la versión) que se ve en todas las pestañas. */
    public function elegirAnio(Request $request)
    {
        $data = $request->validate([
            'anio' => ['required', 'integer', 'between:' . Anio::MIN . ',' . Anio::MAX],
            'version' => ['nullable', 'in:' . implode(',', array_keys(Anio::VERSIONES))],
        ]);
        Anio::elegir((int) $data['anio'], $data['version'] ?? null);

        return back();
    }

    /** Borrado lógico de una versión de un año; se puede deshacer desde el aviso. */
    public function eliminarAnio(Request $request, int $anio)
    {
        $version = $this->versionDe($request->query('version')) ?? 'inicial';
        $nombre = "$anio (" . mb_strtolower(Anio::VERSIONES[$version]) . ')';
        $n = Partida::delAnio($anio, $version)->count();
        if (! $n) {
            return back()->with('error', "No hay partidas cargadas en $nombre.");
        }

        // Todas con la misma marca de tiempo, para que «Deshacer» restaure solo estas
        $marca = now()->startOfSecond();
        Partida::delAnio($anio, $version)->update(['deleted_at' => $marca]);

        return back()
            ->with('ok', "$nombre eliminado ($n partidas)")
            ->with('undo', route('datos.anio.restaurar', ['anio' => $anio, 'version' => $version, 'marca' => $marca->format('Y-m-d H:i:s')], false));
    }

    public function restaurarAnio(Request $request, int $anio)
    {
        $marca = $request->validate(['marca' => ['required', 'date_format:Y-m-d H:i:s']])['marca'];
        $version = $this->versionDe($request->query('version')) ?? 'inicial';
        $n = Partida::onlyTrashed()->where('anio', $anio)->where('version', $version)->where('deleted_at', $marca)->restore();
        Anio::elegir($anio, $version);

        return back()->with('ok', "$anio (" . mb_strtolower(Anio::VERSIONES[$version]) . ") restaurado ($n partidas)");
    }

    /** Descarga real (enlace normal, no navegación Inertia). */
    public function exportar()
    {
        $tsv = $this->tsv();

        return response()->streamDownload(
            function () use ($tsv) {
                echo "\xEF\xBB\xBF" . $tsv; // BOM para que Excel lea bien las tildes
            },
            'sgp-fen-' . Anio::actual() . (Anio::version() === 'proyeccion' ? '-proyeccion' : '') . '.tsv',
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
    private function resumenDe(array $p, ?int $anio = null, ?string $version = null): array
    {
        $version ??= 'inicial';
        $cruce = $anio && $version === 'proyeccion' && $p['filas'] ? $this->cruzarConInicial($p['filas'], $anio) : null;

        return [
            'validas' => count($p['filas']),
            'avisos' => $p['avisos'],
            'errores' => array_slice($p['errores'], 0, 3),
            'anio' => $anio,
            'version' => $version,
            // partidas que ya hay en ese año y versión: «Reemplazar» las enviaría a la papelera
            'existentes' => $anio ? Partida::delAnio($anio, $version)->count() : 0,
            // solo proyección: cuántas filas encontraron su partida en el presupuesto inicial
            'iniciales' => $cruce ? $cruce['iniciales'] : null,
            'coinciden' => $cruce ? count($cruce['pares']) : null,
        ];
    }

    private function versionDe(mixed $v): ?string
    {
        return is_string($v) && isset(Anio::VERSIONES[$v]) ? $v : null;
    }

    /**
     * Busca, para cada fila pegada, su partida en el presupuesto inicial del mismo año
     * (misma unidad, actividad, subactividad, SIGMA, cuenta y códigos). Si hay partidas repetidas
     * se emparejan en orden, una con una.
     *
     * @return array{pares: array<int, Partida>, iniciales: int} índice de la fila => partida inicial
     */
    private function cruzarConInicial(array $filas, int $anio): array
    {
        $clave = fn (array $f) => implode('|', array_map(
            fn ($v) => preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) $v))),
            [$f['unidad'], $f['actividad'], $f['subactividad'], $f['sigma'], $f['cuenta'], $f['dep_codigo'], $f['un_codigo']]
        ));

        $iniciales = Partida::delAnio($anio, 'inicial')->orderBy('id')->get();
        $libres = $iniciales->groupBy(fn (Partida $p) => $clave($p->comoFila()))->map(fn ($g) => $g->values()->all())->all();

        $pares = [];
        foreach ($filas as $i => $f) {
            $k = $clave($f);
            if (! empty($libres[$k])) {
                $pares[$i] = array_shift($libres[$k]);
            }
        }

        return ['pares' => $pares, 'iniciales' => $iniciales->count()];
    }
}
