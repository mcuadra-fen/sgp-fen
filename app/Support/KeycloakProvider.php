<?php

namespace App\Support;

use Illuminate\Support\Arr;
use SocialiteProviders\Keycloak\Provider;

/**
 * Proveedor de Keycloak que además conserva el id_token: se envía como id_token_hint al cerrar sesión,
 * para que Keycloak no pida confirmación y vuelva directo al sistema.
 */
class KeycloakProvider extends Provider
{
    protected $scopes = ['openid', 'profile', 'email'];

    protected $scopeSeparator = ' ';

    protected function userInstance(array $response, array $user)
    {
        $u = parent::userInstance($response, $user);
        $u->idToken = Arr::get($response, 'id_token');

        return $u;
    }
}
