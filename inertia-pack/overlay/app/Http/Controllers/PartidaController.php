<?php

namespace App\Http\Controllers;

use App\Http\Requests\PartidaRequest;
use App\Models\Partida;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartidaController extends Controller
{
    /** Todas las partidas; la búsqueda y los filtros se aplican en la interfaz. */
    public function index(): Response
    {
        return Inertia::render('Partidas/Index', [
            'partidas' => Partida::orderBy('id')->get()->map->paraInterfaz()->all(),
        ]);
    }

    public function create(): Response
    {
        $primera = Partida::orderBy('id')->first();

        return $this->formulario([
            'id' => null,
            'dpto' => $primera?->dpto ?? 'Unidad de Desarrollo Tecnológico',
            'unidad' => '',
            'actividad' => '',
            'subactividad' => '',
            'sigma' => '',
            'cuenta' => '',
            'dep_codigo' => (string) ($primera?->dep_codigo ?? ''),
            'un_codigo' => '',
        ], array_fill(0, 12, 0), false);
    }

    public function store(PartidaRequest $request)
    {
        Partida::create($request->datos());

        return redirect()->route('partidas.index')->with('ok', 'Partida agregada');
    }

    public function edit(Partida $partida): Response
    {
        $p = $partida->paraInterfaz();
        $meses = $p['meses'];
        unset($p['meses'], $p['es_ejemplo']);

        return $this->formulario($p, $meses, true);
    }

    public function update(PartidaRequest $request, Partida $partida)
    {
        $partida->fill($request->datos());
        $partida->es_ejemplo = false;
        $partida->save();

        return redirect()->route('partidas.index')->with('ok', 'Partida actualizada');
    }

    /** Borrado lógico: se puede deshacer desde el aviso. */
    public function destroy(Partida $partida)
    {
        $partida->delete();

        return redirect()->route('partidas.index')
            ->with('ok', 'Partida eliminada')
            ->with('undo', route('partidas.restaurar', $partida->id, false));
    }

    public function restaurar(int $id)
    {
        Partida::withTrashed()->findOrFail($id)->restore();

        return redirect()->route('partidas.index')->with('ok', 'Partida restaurada');
    }

    public function duplicar(Partida $partida)
    {
        $copia = $partida->replicate();
        $copia->es_ejemplo = false;
        $copia->save();

        return redirect()->route('partidas.index')->with('ok', 'Partida duplicada');
    }

    /** Edición de un mes desde la grilla (llamada AJAX, responde JSON). */
    public function mes(Request $request, Partida $partida): JsonResponse
    {
        $data = $request->validate([
            'mes' => ['required', 'integer', 'between:1,12'],
            'monto' => ['required', 'integer', 'min:0'],
        ]);

        $partida->{'m' . $data['mes']} = $data['monto'];
        $partida->save();

        return response()->json(['total' => $partida->total]);
    }

    private function formulario(array $partida, array $meses, bool $editando): Response
    {
        $campo = fn (string $c, bool $sinEjemplos = false) => Partida::query()
            ->when($sinEjemplos, fn ($q) => $q->where('es_ejemplo', false))
            ->whereNotNull($c)->where($c, '!=', '')->distinct()->orderBy($c)->pluck($c)->values()->all();

        return Inertia::render('Partidas/Form', [
            'partida' => $partida,
            'meses' => $meses,
            'editando' => $editando,
            'listas' => [
                'dpto' => $campo('dpto'),
                'unidad' => $campo('unidad'),
                'actividad' => $campo('actividad'),
                'subactividad' => $campo('subactividad'),
                'sigma' => $campo('sigma'),
                'cuenta' => $campo('cuenta', true),
            ],
            // unidad => [dep_codigo, un_codigo], para autocompletar al elegir la unidad
            'codigos' => Partida::query()->whereNotNull('un_codigo')->orderBy('id')->get(['unidad', 'dep_codigo', 'un_codigo'])
                ->unique('unidad')
                ->mapWithKeys(fn ($p) => [$p->unidad => [(string) $p->dep_codigo, (string) $p->un_codigo]])
                ->all(),
        ]);
    }
}
