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

    public function test_exportar_y_volver_a_importar_conserva_los_datos(): void
    {
        $this->seed(PartidaSeeder::class);
        $tsv = Presupuesto::toTsv(Partida::orderBy('id')->get()->map->comoFila()->all());

        $this->post('/datos/importar', ['texto' => $tsv, 'modo' => 'reemplazar'])->assertRedirect('/partidas');

        $this->assertSame(5, Partida::count());
        $this->assertSame(109976107, Partida::all()->sum->total);
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
