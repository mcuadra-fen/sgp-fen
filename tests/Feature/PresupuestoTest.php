<?php

namespace Tests\Feature;

use App\Models\Partida;
use App\Support\Presupuesto;
use Database\Seeders\PartidaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PresupuestoTest extends TestCase
{
    use RefreshDatabase;

    private function valido(array $extra = []): array
    {
        return array_merge([
            'dpto' => 'Unidad de Desarrollo Tecnológico',
            'unidad' => 'Gabinete UDT',
            'actividad' => 'Aportes',
            'subactividad' => 'Aporte basal',
            'sigma' => 'Aportes Universidad',
            'cuenta' => '620311001 Aporte Institucional',
            'dep_codigo' => '12051300',
            'un_codigo' => '12051301',
            'meses' => array_fill(0, 12, '1.000'),
        ], $extra);
    }

    public function test_el_seeder_deja_los_totales_exactos(): void
    {
        $this->seed(PartidaSeeder::class);

        $this->assertSame(5, Partida::count());
        $reales = Partida::where('es_ejemplo', false)->orderBy('id')->get();
        $this->assertSame(9925158, $reales[0]->total);
        $this->assertSame(59550949, $reales[1]->total);
    }

    public function test_el_resumen_entrega_los_totales_a_react(): void
    {
        $this->seed(PartidaSeeder::class);

        $this->get('/resumen')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Resumen')
            ->where('total', 109976107)
            ->where('cantidad', 5)
            ->has('meses', 12)
            ->has('porSigma'));
    }

    public function test_la_grilla_recibe_todas_las_partidas(): void
    {
        $this->seed(PartidaSeeder::class);

        $this->get('/partidas')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Partidas/Index')
            ->has('partidas', 5)
            ->has('partidas.0.meses', 12)
            ->where('partidas.0.cuenta', '620308254 Aporte Convenio Actividades de Interes Nacional'));
    }

    public function test_el_formulario_nuevo_y_el_de_edicion(): void
    {
        $this->seed(PartidaSeeder::class);
        $p = Partida::orderBy('id')->first();

        $this->get('/partidas/create')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Partidas/Form')->where('editando', false)->has('meses', 12)->has('codigos'));
        $this->get("/partidas/{$p->id}/edit")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Partidas/Form')->where('editando', true)->where('partida.id', $p->id));
    }

    public function test_crea_una_partida_con_montos_formateados(): void
    {
        $this->post('/partidas', $this->valido())->assertRedirect('/partidas');

        $p = Partida::firstOrFail();
        $this->assertSame(12000, $p->total);
        $this->assertSame(1000, $p->m7);
    }

    public function test_valida_los_campos_obligatorios(): void
    {
        $this->post('/partidas', $this->valido(['unidad' => '', 'dep_codigo' => 'abc']))
            ->assertSessionHasErrors(['unidad', 'dep_codigo']);

        $this->assertSame(0, Partida::count());
    }

    public function test_edita_un_mes_desde_la_grilla(): void
    {
        $this->seed(PartidaSeeder::class);
        $p = Partida::orderBy('id')->first();

        $this->patchJson("/partidas/{$p->id}/mes", ['mes' => 3, 'monto' => 5000])
            ->assertOk()
            ->assertJson(['total' => $p->total - $p->m3 + 5000]);

        $this->assertSame(5000, $p->fresh()->m3);
        $this->patchJson("/partidas/{$p->id}/mes", ['mes' => 13, 'monto' => 1])->assertStatus(422);
    }

    public function test_eliminar_y_restaurar(): void
    {
        $this->seed(PartidaSeeder::class);
        $p = Partida::orderBy('id')->first();

        $this->delete("/partidas/{$p->id}")->assertRedirect('/partidas');
        $this->assertSame(4, Partida::count());

        $this->post("/partidas/{$p->id}/restaurar")->assertRedirect('/partidas');
        $this->assertSame(5, Partida::count());
    }

    public function test_eliminar_varias_y_deshacer(): void
    {
        $this->seed(PartidaSeeder::class);
        [$a, $b, $c, $d] = Partida::orderBy('id')->limit(4)->get();
        $otroAnio = Partida::create([...$a->only(['dpto', 'unidad', 'actividad', 'sigma', 'cuenta']), 'anio' => 2025]);

        $this->delete("/partidas/{$d->id}"); // borrada antes, por separado
        $this->travel(2)->seconds();

        $res = $this->post('/partidas/eliminar', ['ids' => [$a->id, $b->id, $c->id, $otroAnio->id]])
            ->assertRedirect('/partidas')
            ->assertSessionHas('ok', '3 partidas eliminadas');
        $this->assertSame(1, Partida::where('anio', (int) date('Y'))->count());
        $this->assertNotNull($otroAnio->fresh()); // otro año: no se toca

        $this->post($res->getSession()->get('undo'))->assertRedirect('/partidas');
        $this->assertSame(4, Partida::where('anio', (int) date('Y'))->count());
        $this->assertTrue($d->fresh()->trashed());

        $this->post('/partidas/eliminar', ['ids' => []])->assertSessionHasErrors('ids');
    }

    public function test_informe_compara_dos_anios(): void
    {
        $p = fn (int $anio, string $sigma, int $total) => (new Partida([
            'anio' => $anio, 'unidad' => 'U', 'actividad' => 'A', 'sigma' => $sigma, 'cuenta' => 'C',
        ]))->setMeses([$total])->save();

        $p(2026, 'Aportes Universidad', 1000);
        $p(2026, 'Traspaso de Recursos (I)', 500);
        $p(2026, 'Remuneraciones No Académicas', 800);
        $p(2026, 'Traspaso de Recursos (G)', 100);
        $p(2026, 'Inversión', 200);
        $p(2027, 'Aportes Universidad', 2000);
        $p(2027, 'Remuneraciones No Académicas', 600);
        $p(2027, 'Gastos Operacionales', 300);
        $p(2027, 'Inversión', 300);
        $p(2027, 'Otra clasificación', 50);

        $this->get('/informe?a=2026&b=2027')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Informe')->where('a', 2026)->where('b', 2027));

        $f = collect(\App\Support\Informe::filas(2026, 2027))->keyBy('nombre');
        $this->assertSame([1500, 2000, 500, 33.33], [$f['TOTAL INGRESOS']['a'], $f['TOTAL INGRESOS']['b'], $f['TOTAL INGRESOS']['var'], $f['TOTAL INGRESOS']['pct']]);
        $this->assertSame([-800, -600, 200, -25.0], [$f['Remuneraciones No Académicas']['a'], $f['Remuneraciones No Académicas']['b'], $f['Remuneraciones No Académicas']['var'], $f['Remuneraciones No Académicas']['pct']]);
        $this->assertNull($f['Gastos Operacionales']['pct']); // base cero
        $this->assertSame(-50, $f['Otra clasificación']['b']); // clasificación nueva: aparece como gasto
        $this->assertSame([-900, -950], [$f['SUBTOTAL GASTOS (SIN INVERSIÓN)']['a'], $f['SUBTOTAL GASTOS (SIN INVERSIÓN)']['b']]);
        $this->assertSame([600, 1050], [$f['SUBTOTAL FLUJO NETO']['a'], $f['SUBTOTAL FLUJO NETO']['b']]);
        $this->assertSame([400, 750], [$f['SUBTOTAL FLUJO NETO POST INVERSIÓN']['a'], $f['SUBTOTAL FLUJO NETO POST INVERSIÓN']['b']]);

        $p(2027, 'Inversión', 2000); // el flujo post inversión pasa a negativo: cambio de signo
        $f = collect(\App\Support\Informe::filas(2026, 2027))->keyBy('nombre');
        $this->assertNull($f['SUBTOTAL FLUJO NETO POST INVERSIÓN']['pct']);
    }

    public function test_clasificar_con_categorias_y_observaciones(): void
    {
        $this->seed(PartidaSeeder::class);
        [$a, $b, $c] = Partida::orderBy('id')->limit(3)->get();
        $proyectos = \App\Models\Categoria::where('nombre', 'Proyectos')->firstOrFail(); // creada por la migración

        $this->get('/clasificar')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Clasificar')->has('partidas', 5)->has('categorias', 2));

        // Asignar en lote, y quitar
        $this->post('/clasificar/asignar', ['ids' => [$a->id, $b->id], 'categoria_id' => $proyectos->id])
            ->assertSessionHas('ok', '2 partidas en «Proyectos»');
        $this->assertSame($proyectos->id, $a->fresh()->categoria_id);
        $this->post('/clasificar/asignar', ['ids' => [$b->id], 'categoria_id' => null]);
        $this->assertNull($b->fresh()->categoria_id);
        $this->post('/clasificar/asignar', ['ids' => [$c->id], 'categoria_id' => 999])->assertSessionHasErrors('categoria_id');

        // Categorías: no se repiten nombres; al eliminar, la partida queda sin categoría
        $this->post('/categorias', ['nombre' => 'Proyectos'])->assertSessionHasErrors('nombre');
        $this->post('/categorias', ['nombre' => ' Mantención '])->assertSessionHasNoErrors();
        $this->assertTrue(\App\Models\Categoria::where('nombre', 'Mantención')->exists());
        $this->patch("/categorias/{$proyectos->id}", ['nombre' => 'Proyectos TI'])->assertSessionHasNoErrors();
        $this->delete("/categorias/{$proyectos->id}");
        $this->assertNull($a->fresh()->categoria_id);
        $this->assertNotNull($a->fresh()); // la partida no se borra

        // Varias observaciones por partida
        $this->post("/partidas/{$a->id}/observaciones", ['texto' => 'Revisar con finanzas']);
        $this->post("/partidas/{$a->id}/observaciones", ['texto' => 'Monto a confirmar']);
        $this->post("/partidas/{$a->id}/observaciones", ['texto' => ''])->assertSessionHasErrors('texto');
        $this->assertSame(['Revisar con finanzas', 'Monto a confirmar'], $a->observaciones()->pluck('texto')->all());

        $this->get('/partidas')->assertInertia(fn (Assert $page) => $page->where('partidas.0.n_obs', 2));

        $this->delete('/observaciones/' . $a->observaciones()->first()->id);
        $this->assertSame(1, $a->observaciones()->count());
    }

    public function test_exportar_y_volver_a_importar_conserva_los_datos(): void
    {
        $this->seed(PartidaSeeder::class);
        $tsv = Presupuesto::toTsv(Partida::orderBy('id')->get()->map->comoFila()->all());

        $this->post('/datos/importar', ['texto' => $tsv, 'modo' => 'reemplazar', 'anio' => (int) date('Y')])->assertRedirect('/partidas');

        $this->assertSame(5, Partida::count());
        $this->assertSame(109976107, Partida::all()->sum->total);
    }

    public function test_importar_exige_el_anio(): void
    {
        $this->seed(PartidaSeeder::class);
        $tsv = Presupuesto::toTsv(Partida::orderBy('id')->get()->map->comoFila()->all());

        $this->post('/datos/importar', ['texto' => $tsv, 'modo' => 'agregar'])->assertSessionHasErrors('anio');
        $this->post('/datos/importar', ['texto' => $tsv, 'modo' => 'agregar', 'anio' => 1990])->assertSessionHasErrors('anio');

        $this->assertSame(5, Partida::count());
    }

    public function test_reemplazar_solo_afecta_al_anio_importado(): void
    {
        $this->seed(PartidaSeeder::class);
        $hoy = (int) date('Y');
        $tsv = Presupuesto::toTsv(Partida::orderBy('id')->limit(2)->get()->map->comoFila()->all());

        $this->post('/datos/importar', ['texto' => $tsv, 'modo' => 'reemplazar', 'anio' => 2025])->assertRedirect('/partidas');

        $this->assertSame(5, Partida::where('anio', $hoy)->count());
        $this->assertSame(2, Partida::where('anio', 2025)->count());

        // La importación deja a la vista el año importado, en todas las pestañas
        $this->get('/partidas')->assertInertia(fn (Assert $page) => $page->where('anio', 2025)->has('partidas', 2));
        $this->get('/resumen')->assertInertia(fn (Assert $page) => $page->where('cantidad', 2));

        $this->post('/anio', ['anio' => $hoy])->assertRedirect();
        $this->get('/resumen')->assertInertia(fn (Assert $page) => $page->where('cantidad', 5)->where('anios', [$hoy, 2025]));
    }

    public function test_eliminar_un_anio_y_deshacer(): void
    {
        $this->seed(PartidaSeeder::class);
        $hoy = (int) date('Y');
        $tsv = Presupuesto::toTsv(Partida::orderBy('id')->limit(2)->get()->map->comoFila()->all());
        $this->post('/datos/importar', ['texto' => $tsv, 'modo' => 'agregar', 'anio' => 2025]);
        $this->post('/datos/importar', ['texto' => $tsv, 'modo' => 'reemplazar', 'anio' => 2025]); // 2 a la papelera
        $this->travel(2)->seconds();

        $res = $this->from('/datos')->delete('/datos/anios/2025')->assertRedirect('/datos')->assertSessionHas('undo');
        $this->assertSame(0, Partida::where('anio', 2025)->count());
        $this->assertSame(5, Partida::where('anio', $hoy)->count());

        // Partidas de 2025 borradas antes (otra operación) no deben volver con «Deshacer»
        $this->post($res->getSession()->get('undo'))->assertRedirect();
        $this->assertSame(2, Partida::where('anio', 2025)->count());

        $this->from('/datos')->delete('/datos/anios/1999')->assertSessionHas('error');
    }

    public function test_revisar_informa_filas_validas_sin_guardar(): void
    {
        $this->seed(PartidaSeeder::class);
        $tsv = Presupuesto::toTsv(Partida::orderBy('id')->get()->map->comoFila()->all());

        $this->from('/datos')->post('/datos/revisar', ['texto' => $tsv])
            ->assertRedirect('/datos')
            ->assertSessionHas('preview.validas', 5);

        $this->assertSame(5, Partida::count());
    }

    public function test_quitar_ejemplos(): void
    {
        $this->seed(PartidaSeeder::class);

        $this->post('/datos/quitar-ejemplos')->assertRedirect();
        $this->assertSame(2, Partida::count());
    }
}
