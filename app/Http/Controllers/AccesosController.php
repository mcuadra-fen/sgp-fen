<?php

namespace App\Http\Controllers;

use App\Models\AccesoArea;
use App\Models\LineaTraspaso;
use App\Models\User;
use App\Support\CobrosUdt;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Asigna a cada correo FEN los centros de costo que puede ver en «Mis cobros». */
class AccesosController extends Controller
{
    public function index(): Response
    {
        $catalogo = CobrosUdt::catalogo();
        $cobrados = LineaTraspaso::where('lado', 'cargo')->distinct()->pluck('centro_costo')->all();
        $nombres = User::pluck('name', 'email')->mapWithKeys(fn ($n, $e) => [strtolower((string) $e) => $n]);

        return Inertia::render('Accesos', [
            'accesos' => AccesoArea::orderBy('email')->orderBy('centro_costo')->get()->map(fn (AccesoArea $a) => [
                'id' => $a->id, 'email' => $a->email, 'persona' => $nombres[$a->email] ?? null,
                'centro_costo' => $a->centro_costo, 'nombre' => $catalogo[$a->centro_costo]['nombre'] ?? null,
            ])->all(),
            // Opciones: los centros de costo del tarifario y los que ya tienen cobros
            'centros' => collect(array_unique([...array_keys($catalogo), ...$cobrados]))
                ->reject(fn ($c) => ($catalogo[$c]['area'] ?? null) === 'UDT')
                ->map(fn ($c) => ['codigo' => $c, 'nombre' => $catalogo[$c]['nombre'] ?? null, 'area' => $catalogo[$c]['area'] ?? null])
                ->sortBy(fn ($c) => ($c['area'] ?? 'ZZZ') . ($c['nombre'] ?? 'ZZZ') . $c['codigo'])->values()->all(),
            'admins' => config('sgp.admins'),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'centros' => ['required', 'array', 'min:1'],
            'centros.*' => ['string', 'regex:/^\d{15}$/'],
        ], [], ['email' => 'correo', 'centros' => 'centros de costo']);
        $email = strtolower(trim($datos['email']));
        foreach ($datos['centros'] as $c) {
            AccesoArea::firstOrCreate(['email' => $email, 'centro_costo' => $c]);
        }

        return back()->with('ok', "Acceso asignado a $email");
    }

    public function destroy(AccesoArea $acceso)
    {
        $acceso->delete();

        return back()->with('ok', "Se quitó {$acceso->centro_costo} a {$acceso->email}");
    }
}
