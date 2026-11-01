<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Prueba de conexión con la API v2 de Webdox CLM: pide un token (OAuth2, grant «password»),
 * hace una consulta de solo lectura y revoca el token. No muestra claves ni tokens.
 */
class WebdoxProbar extends Command
{
    protected $signature = 'webdox:probar';

    protected $description = 'Prueba la conexión con la API de Webdox usando los datos WEBDOX_* del .env';

    public function handle(): int
    {
        $c = config('services.webdox');
        $base = rtrim((string) $c['base_url'], '/');

        $faltan = array_keys(array_filter(['WEBDOX_USERNAME' => $c['username'], 'WEBDOX_PASSWORD' => $c['password']], fn ($v) => ! $v));
        if ($faltan) {
            $this->error('Faltan en el .env: ' . implode(', ', $faltan));

            return self::FAILURE;
        }

        $this->line("API: $base");
        $this->line('Usuario: ' . $c['username'] . ($c['customer'] ? " · ambiente: {$c['customer']}" : ''));

        // 1. Token
        try {
            $r = Http::asForm()->acceptJson()->timeout(20)->post("$base/oauth/token", array_filter([
                'grant_type' => 'password',
                'username' => $c['username'],
                'password' => $c['password'],
                'customer' => $c['customer'],
            ]));
        } catch (Throwable $e) {
            $this->error('1. Token: no se pudo conectar con el servidor: ' . $e->getMessage());

            return self::FAILURE;
        }
        if (! $r->successful() || ! $r->json('access_token')) {
            $this->error("1. Token: rechazado (HTTP {$r->status()}): " . mb_strimwidth($r->body(), 0, 300, '…'));
            if (in_array($r->status(), [400, 401], true)) {
                $this->line('   Revise usuario y clave; si su usuario está en varios ambientes, complete WEBDOX_CUSTOMER.');
            }

            return self::FAILURE;
        }
        $token = $r->json('access_token');
        $this->info('1. Token: OK (' . $r->json('token_type', 'Bearer') . ', vence en ' . round($r->json('expires_in', 0) / 60) . ' min)');

        // 2. Consulta de solo lectura
        $q = Http::withToken($token)->acceptJson()->timeout(20)->get("$base/templates", ['page' => 1, 'per_page' => 5]);
        if ($q->successful()) {
            $datos = $q->json();
            $lista = is_array($datos) && array_is_list($datos) ? $datos : ($datos['templates'] ?? $datos['data'] ?? []);
            $this->info('2. Consulta de plantillas: OK (' . count($lista) . ' recibidas)');
            foreach (array_slice($lista, 0, 5) as $t) {
                $this->line('   - ' . ($t['name'] ?? json_encode($t)));
            }
        } else {
            $this->warn("2. Consulta de plantillas: HTTP {$q->status()}: " . mb_strimwidth($q->body(), 0, 300, '…'));
            $this->line('   El token sí funcionó; puede que el usuario no tenga permiso para ver plantillas.');
        }

        // 3. Revocar el token de prueba
        $v = Http::asForm()->acceptJson()->timeout(20)->post("$base/oauth/revoke", ['token' => $token]);
        $this->line('3. Token revocado: ' . ($v->successful() ? 'OK' : "HTTP {$v->status()}"));

        return self::SUCCESS;
    }
}
