<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/** Inicio y cierre de sesión con el SSO de la FEN (Keycloak, OpenID Connect con «code»). */
class AuthController extends Controller
{
    /** Pantalla de ingreso: un botón que lleva a Keycloak (y los avisos si algo falló). */
    public function login(Request $request)
    {
        // Keycloak solo acepta volver a la dirección registrada (APP_URL); si se entró con otra
        // (p. ej. localhost en vez de 127.0.0.1) la cookie de sesión no viajaría y el ingreso fallaría.
        $base = rtrim((string) config('app.url'), '/');
        if ($base && $request->getSchemeAndHttpHost() !== $base) {
            return redirect()->away($base . '/login');
        }

        // Directo a Keycloak, salvo que haya un aviso que mostrar (falló el ingreso o se cerró la sesión):
        // así no se vuelve a Keycloak en bucle tras un error
        if (! session()->has('error') && ! session()->has('ok')) {
            return $this->redirect();
        }

        return Inertia::render('Login');
    }

    public function redirect()
    {
        return Inertia::location(Socialite::driver('keycloak')->redirect()->getTargetUrl());
    }

    /** CallbackPath: Keycloak vuelve aquí con el código; se canjea por los datos del usuario. */
    public function callback(Request $request)
    {
        if ($request->has('error')) {
            return redirect()->route('login')->with('error', 'Keycloak no autorizó el ingreso: ' . $request->query('error_description', $request->query('error')));
        }

        try {
            $kc = Socialite::driver('keycloak')->user();
        } catch (Throwable $e) {
            Log::warning('Keycloak: no se pudo completar el ingreso', ['error' => $e->getMessage()]);

            return redirect()->route('login')->with('error', 'No se pudo completar el ingreso con la cuenta FEN. Intente de nuevo.');
        }

        $user = User::updateOrCreate(['keycloak_id' => $kc->getId()], [
            'name' => $kc->getName() ?: ($kc->getNickname() ?: 'Usuario FEN'),
            'username' => $kc->getNickname(),
            'email' => $kc->getEmail(),
        ]);

        // Solo entran los administradores y las personas con centros de costo asignados
        if (! $user->puedeIngresar()) {
            return redirect()->route('login')->with('error',
                "La cuenta {$user->email} no tiene acceso a SGP-FEN. Pida a la Unidad de Desarrollo Tecnológico que le asigne su área.");
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('kc_id_token', $kc->idToken ?? null);

        return $user->esAdmin() ? redirect()->intended(route('resumen')) : redirect()->route('mis-cobros');
    }

    /** Cierra la sesión aquí y en Keycloak; Keycloak vuelve a SignedOutCallbackPath. */
    public function logout(Request $request)
    {
        $idToken = $request->session()->get('kc_id_token');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // La vuelta al sistema solo funciona si la URI está registrada en el cliente de Keycloak
        // («Valid post logout redirect URIs»); sin ella Keycloak muestra su propia página de sesión cerrada.
        $vuelta = config('services.keycloak.post_logout_redirect');
        $params = array_filter([
            'client_id' => config('services.keycloak.client_id'),
            'id_token_hint' => $idToken,
            'post_logout_redirect_uri' => $vuelta ? url($vuelta) : null,
        ]);

        return Inertia::location(Socialite::driver('keycloak')->getLogoutUrl() . '?' . http_build_query($params));
    }

    /** SignedOutCallbackPath: vuelta desde Keycloak después de cerrar sesión. */
    public function signedOut()
    {
        return $this->redirect(); // de nuevo al login de Keycloak
    }
}
