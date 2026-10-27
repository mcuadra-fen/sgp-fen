<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Observacion;
use App\Models\Partida;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Pantalla «Clasificar»: categoría y observaciones de cada partida del año. */
class ClasificarController extends Controller
{
    public function index(): Response
    {
        $partidas = Partida::delAnio()->with('observaciones')->orderBy('id')->get();
        $porCategoria = $partidas->countBy(fn ($p) => $p->categoria_id ?? 0);

        return Inertia::render('Clasificar', [
            'partidas' => $partidas->map(fn (Partida $p) => [
                'id' => $p->id,
                'cuenta' => (string) $p->cuenta,
                'unidad' => (string) $p->unidad,
                'actividad' => (string) $p->actividad,
                'subactividad' => (string) $p->subactividad,
                'sigma' => (string) $p->sigma,
                'total' => $p->total,
                'categoria_id' => $p->categoria_id,
                'observaciones' => $p->observaciones->map->paraInterfaz()->all(),
            ])->all(),
            'categorias' => Categoria::orderBy('nombre')->get()
                ->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre, 'n' => $porCategoria[$c->id] ?? 0])->all(),
        ]);
    }

    /** Asigna (o quita, con categoria_id vacío) la categoría de una o varias partidas del año. */
    public function asignar(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
        ], [
            'ids.required' => 'Seleccione al menos una partida.',
        ]);

        $n = Partida::delAnio()->whereIn('id', $data['ids'])->update(['categoria_id' => $data['categoria_id'] ?? null]);
        $nombre = isset($data['categoria_id']) ? Categoria::find($data['categoria_id'])->nombre : null;

        return back()->with('ok', ($n === 1 ? '1 partida' : "$n partidas") . ($nombre ? " en «{$nombre}»" : ' sin categoría'));
    }

    public function crearCategoria(Request $request)
    {
        $data = $request->validate(['nombre' => $this->reglaNombre()], $this->mensajes());
        Categoria::create(['nombre' => trim($data['nombre'])]);

        return back()->with('ok', 'Categoría creada');
    }

    public function renombrarCategoria(Request $request, Categoria $categoria)
    {
        $data = $request->validate(['nombre' => $this->reglaNombre($categoria->id)], $this->mensajes());
        $categoria->update(['nombre' => trim($data['nombre'])]);

        return back()->with('ok', 'Categoría renombrada');
    }

    /** Las partidas de la categoría quedan sin categoría; no se borra ninguna partida. */
    public function eliminarCategoria(Categoria $categoria)
    {
        $categoria->delete();

        return back()->with('ok', "Categoría «{$categoria->nombre}» eliminada");
    }

    public function agregarObservacion(Request $request, Partida $partida)
    {
        $data = $request->validate(['texto' => ['required', 'string', 'max:2000']], [
            'texto.required' => 'Escriba la observación.',
            'texto.max' => 'La observación no puede superar 2000 caracteres.',
        ]);
        $partida->observaciones()->create(['texto' => trim($data['texto'])]);

        return back()->with('ok', 'Observación agregada');
    }

    public function eliminarObservacion(Observacion $observacion)
    {
        $observacion->delete();

        return back()->with('ok', 'Observación eliminada');
    }

    private function reglaNombre(?int $ignorar = null): array
    {
        return ['required', 'string', 'max:80', Rule::unique('categorias', 'nombre')->ignore($ignorar)];
    }

    private function mensajes(): array
    {
        return [
            'nombre.required' => 'Escriba el nombre de la categoría.',
            'nombre.unique' => 'Ya existe una categoría con ese nombre.',
            'nombre.max' => 'El nombre no puede superar 80 caracteres.',
        ];
    }
}
