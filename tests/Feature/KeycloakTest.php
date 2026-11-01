<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class KeycloakTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_sesion_se_pide_ingresar(): void
    {
        $this->get('/resumen')->assertRedirect('/login');
        $this->post('/datos/importar')->assertRedirect('/login');
        $this->get('/login')->assertRedirectContains('/protocol/openid-connect/auth'); // directo a Keycloak
        $this->withSession(['error' => 'x'])->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Login'));
    }

    public function test_el_boton_lleva_a_keycloak_con_la_configuracion_del_cliente(): void
    {
        config([
            'services.keycloak.base_url' => 'https://sso.ejemplo.cl',
            'services.keycloak.realms' => 'FenUChile',
            'services.keycloak.client_id' => 'SGP-local',
        ]);

        $url = $this->get('/login/keycloak')->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        $this->assertStringStartsWith('https://sso.ejemplo.cl/realms/FenUChile/protocol/openid-connect/auth?', $url);
        $this->assertSame('SGP-local', $q['client_id']);
        $this->assertSame('code', $q['response_type']);
        $this->assertSame('openid profile email', $q['scope']);
        $this->assertSame(url('/signin-oidc'), $q['redirect_uri']);
    }

    public function test_la_vuelta_de_keycloak_crea_el_usuario_e_inicia_sesion(): void
    {
        $kc = (new SocialiteUser())->map(['id' => 'abc-123', 'name' => 'Ana Pérez', 'nickname' => 'aperez', 'email' => 'aperez@fen.uchile.cl']);
        $kc->idToken = 'token-de-prueba';
        Socialite::fake('keycloak', $kc);
        config(['sgp.admins' => ['aperez@fen.uchile.cl']]);

        $this->get('/signin-oidc?code=x&state=y')->assertRedirect('/resumen');
        $this->assertAuthenticated();
        $this->assertSame('token-de-prueba', session('kc_id_token'));

        $u = User::where('keycloak_id', 'abc-123')->firstOrFail();
        $this->assertSame(['Ana Pérez', 'aperez', 'aperez@fen.uchile.cl'], [$u->name, $u->username, $u->email]);

        $this->get('/resumen')->assertInertia(fn (Assert $page) => $page->where('usuario.nombre', 'Ana Pérez'));

        // Un segundo ingreso actualiza los datos, no duplica el usuario
        $kc->map(['name' => 'Ana Pérez Soto']);
        auth()->logout();
        $this->get('/signin-oidc?code=x&state=y');
        $this->assertSame(1, User::count());
        $this->assertSame('Ana Pérez Soto', $u->fresh()->name);
    }

    public function test_error_de_keycloak_vuelve_al_ingreso_con_aviso(): void
    {
        $this->get('/signin-oidc?error=access_denied&error_description=Sin+permiso')
            ->assertRedirect('/login')->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_salir_cierra_la_sesion_tambien_en_keycloak(): void
    {
        config(['services.keycloak.base_url' => 'https://sso.ejemplo.cl', 'services.keycloak.realms' => 'FenUChile']);
        $this->actingAs(User::factory()->create());

        $url = $this->withSession(['kc_id_token' => 'tok'])->post('/logout')->assertRedirect()->headers->get('Location');

        $this->assertGuest();
        $this->assertStringStartsWith('https://sso.ejemplo.cl/realms/FenUChile/protocol/openid-connect/logout?', $url);
        $this->assertStringContainsString('post_logout_redirect_uri=' . urlencode(url('/signout-callback-oidc')), $url);
        $this->assertStringContainsString('id_token_hint=tok', $url);

        $this->get('/signout-callback-oidc')->assertRedirectContains('/protocol/openid-connect/auth'); // de nuevo al login de Keycloak
    }

    public function test_salir_sin_uri_de_vuelta_registrada(): void
    {
        config(['services.keycloak.post_logout_redirect' => null]);
        $this->actingAs(User::factory()->create());

        $url = $this->post('/logout')->assertRedirect()->headers->get('Location');

        $this->assertStringNotContainsString('post_logout_redirect_uri', $url);
        $this->assertStringContainsString('client_id=', $url);
    }

    public function test_el_ingreso_se_hace_en_la_direccion_registrada(): void
    {
        config(['app.url' => 'http://127.0.0.1:8000']);

        $this->get('http://localhost/login')->assertRedirect('http://127.0.0.1:8000/login');
        $this->get('http://127.0.0.1:8000/login')->assertRedirectContains('/protocol/openid-connect/auth');
    }
}
