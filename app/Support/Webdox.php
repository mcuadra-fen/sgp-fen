<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente mínimo de la API v2 de Webdox CLM (solo lectura por ahora).
 * El token (OAuth2, grant «password») se pide una vez por instancia; dura 2 horas.
 */
class Webdox
{
    private ?string $token = null;

    public function base(): string
    {
        return rtrim((string) config('services.webdox.base_url'), '/');
    }

    /** @throws RuntimeException con la respuesta de Webdox si rechaza las credenciales */
    public function token(): string
    {
        if ($this->token) {
            return $this->token;
        }
        $c = config('services.webdox');
        $r = Http::asForm()->acceptJson()->timeout(20)->post($this->base() . '/oauth/token', array_filter([
            'grant_type' => 'password',
            'username' => $c['username'],
            'password' => $c['password'],
            'customer' => $c['customer'],
        ]));
        if (! $r->successful() || ! $r->json('access_token')) {
            throw new RuntimeException("Webdox rechazó el token (HTTP {$r->status()}): " . mb_strimwidth($r->body(), 0, 300, '…'));
        }

        return $this->token = $r->json('access_token');
    }

    public function get(string $ruta, array $query = []): Response
    {
        return $this->http()->get($this->base() . '/' . ltrim($ruta, '/'), $query);
    }

    /**
     * Documentos visibles para el usuario. Filtros de la API: name, client_name, subject_name, topic_name,
     * folio, creator_name, created_at_after / created_at_before (AAAA-mm-dd), signed, dynamic_attribute_<id>.
     */
    public function documentos(array $filtros = [], int $pagina = 1, int $porPagina = 10): Response
    {
        return $this->get('documents', ['page' => $pagina, 'per_page' => $porPagina, 'filters' => array_filter($filtros, fn ($v) => $v !== null && $v !== '')]);
    }

    /**
     * Una página (25) de solicitudes. Filtros de la API: status (requested, taken, finished, canceled, rejected,
     * in_review, under_modification), created_start_date / created_end_date (ISO 8601).
     */
    public function solicitudes(array $filtros = [], int $pagina = 1): array
    {
        $r = $this->get('workflow_requests', ['page' => $pagina, 'per_page' => 25, 'filters' => $filtros]);
        if (! $r->successful()) {
            throw new RuntimeException("Webdox respondió HTTP {$r->status()} al listar solicitudes");
        }

        return (array) $r->json();
    }

    public function revocar(): void
    {
        if ($this->token) {
            Http::asForm()->acceptJson()->timeout(20)->post($this->base() . '/oauth/revoke', ['token' => $this->token]);
            $this->token = null;
        }
    }

    private function http(): PendingRequest
    {
        // Límite de la API: 60 llamadas por minuto. Ante un 429 (o caída momentánea) se espera y se reintenta.
        return Http::withToken($this->token())->acceptJson()->timeout(30)->retry(
            4,
            fn (int $intento, $e) => $e instanceof RequestException && $e->response->status() === 429
                ? max(1, (int) $e->response->header('Retry-After')) * 1000 + 2000 * $intento
                : 1000 * $intento,
            fn ($e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && in_array($e->response->status(), [429, 502, 503, 504], true)),
            throw: false,
        );
    }
}
