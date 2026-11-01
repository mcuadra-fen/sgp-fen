<?php

namespace Tests\Feature;

use App\Models\AccesoArea;
use App\Models\LineaTraspaso;
use App\Models\PagoWebdox;
use App\Models\Traspaso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class AccesosTest extends TestCase
{
    use RefreshDatabase;

    /** Un cobro de la UDT a dos áreas: Planta Física y Gabinete DEA. */
    private function cobro(): void
    {
        PagoWebdox::create(['webdox_id' => 4675, 'tipo' => 'traspaso', 'titulo' => 'Traspaso de recursos (Cobros DEA) - Unidad de Desarrollo Tecnológico',
            'estado' => 'finished', 'formulario' => 'Traspaso (Cobros DEA)', 'creada_en' => now()]);
        $t = Traspaso::create(['webdox_id' => 4675, 'comprobante' => '2026300001', 'fecha' => '2026-10-20', 'glosa' => 'ID4675 TRASPASO POR HOSTING TF OCTUBRE 2026 DESDE DEPTOS A UDT',
            'sentido' => 'cobro', 'total' => 300, 'archivo' => 'x.pdf', 'ruta' => 'x.pdf', 'modificado' => 1]);
        foreach ([['120522044201001', 'cargo', 200], ['120522014201001', 'cargo', 100], ['120513011103001', 'abono', 300]] as [$cc, $lado, $monto]) {
            LineaTraspaso::create(['traspaso_id' => $t->id, 'webdox_id' => 4675, 'centro_costo' => $cc, 'lado' => $lado,
                'cuenta' => $lado === 'cargo' ? '520214067' : '620309067', 'monto' => $monto]);
        }
    }

    public function test_sin_area_asignada_no_puede_ingresar(): void
    {
        Socialite::fake('keycloak', (new SocialiteUser())->map(['id' => 'x1', 'name' => 'Sin Acceso', 'email' => 'nadie@fen.uchile.cl']));

        $this->get('/signin-oidc?code=x&state=y')->assertRedirect('/login')->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_una_persona_del_area_solo_ve_sus_cobros(): void
    {
        $this->cobro();
        AccesoArea::create(['email' => 'jefe.pf@fen.uchile.cl', 'centro_costo' => '120522044201001']);
        Socialite::fake('keycloak', (new SocialiteUser())->map(['id' => 'x2', 'name' => 'Jefe PF', 'email' => 'Jefe.PF@fen.uchile.cl']));

        $this->get('/signin-oidc?code=x&state=y')->assertRedirect('/mis-cobros');
        $this->get('/mis-cobros')->assertInertia(fn (Assert $page) => $page->component('MisCobros')
            ->has('lineas', 1)->where('lineas.0.centro_costo', '120522044201001')->where('lineas.0.monto', 200)
            ->where('usuario.admin', false));

        // El resto del sistema no está disponible
        $this->get('/resumen')->assertRedirect('/mis-cobros');
        $this->get('/traspasos')->assertRedirect('/mis-cobros');
        $this->post('/accesos', ['email' => 'otro@fen.uchile.cl', 'centros' => ['120522014201001']])->assertForbidden();
        $this->assertSame(1, AccesoArea::count());
    }

    public function test_el_administrador_asigna_y_revisa_accesos(): void
    {
        $this->cobro();
        $this->actingAs(User::factory()->admin()->create());

        $this->post('/accesos', ['email' => 'Jefe.DEA@fen.uchile.cl', 'centros' => ['120522014201001']])->assertSessionHas('ok');
        $this->assertSame(['120522014201001'], AccesoArea::centrosDe('jefe.dea@fen.uchile.cl'));

        $this->get('/mis-cobros?email=jefe.dea@fen.uchile.cl')->assertInertia(fn (Assert $page) => $page
            ->where('vistaPrevia', true)->has('lineas', 1)->where('lineas.0.monto', 100));

        $this->delete('/accesos/' . AccesoArea::first()->id)->assertSessionHas('ok');
        $this->assertSame(0, AccesoArea::count());
    }
}
