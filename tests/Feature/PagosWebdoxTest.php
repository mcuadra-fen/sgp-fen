<?php

namespace Tests\Feature;

use App\Models\PagoWebdox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PagosWebdoxTest extends TestCase
{
    use RefreshDatabase;

    private function solicitud(int $id, string $form, string $titulo, string $estado = 'finished'): array
    {
        return ['id' => $id, 'title' => $titulo, 'status' => $estado, 'request_form_name' => $form, 'created_at' => '2026-10-26T20:22:55.000Z',
            'requester' => ['first_name' => 'Tiare', 'last_name' => 'Aránguiz'], 'assigned' => ['first_name' => 'N', 'last_name' => '']];
    }

    private string $estado = 'taken'; // estado de la solicitud 4770 en el Webdox simulado

    private function fakeWebdox(): void
    {
        config(['services.webdox' => [...config('services.webdox'), 'base_url' => 'https://wd.test/api/v2', 'username' => 'u', 'password' => 'p']]);
        $lista = [
            $this->solicitud(4770, 'Pago a proveedores', 'Pago a proveedores - Unidad de Desarrollo Tecnológico - 29'),
            $this->solicitud(4771, 'Pago a proveedores', 'Pago a proveedores - Unidad de Contabilidad DEA - 45'),
            $this->solicitud(4772, 'Traspaso (Cobros DEA)', 'Traspaso de recursos (Cobros DEA) - Unidad de Desarrollo Tecnológico - x'),
            $this->solicitud(4773, 'Traspaso de recursos', 'Traspaso de recursos - Unidad de Desarrollo Tecnológico - Decanato'),
        ];
        Http::fake([
            'wd.test/api/v2/oauth/token' => Http::response(['access_token' => 'tok', 'token_type' => 'Bearer', 'expires_in' => 7200]),
            'wd.test/api/v2/oauth/revoke' => Http::response([]),
            'wd.test/api/v2/workflow_requests/4770' => fn () => Http::response([...$lista[0], 'status' => $this->estado, 'attachments' => [], 'request_form_attributes' => [
                ['attribute_id' => 1, 'attribute_name' => 'Aprobador de pago', 'attribute_value' => 'Mario Cuadra Seguel'],
                ['attribute_id' => 2, 'attribute_name' => 'Cantidad de documentos a pagar', 'attribute_value' => '11'],
                ['attribute_id' => 3, 'attribute_name' => 'Fecha para Certificado de Pago (generado automáticamente)', 'attribute_value' => '2026-10-27T03:00:00.000Z'],
                ['attribute_id' => 4, 'attribute_name' => 'Correlativo para Certificado de Pago (generado automáticamente)', 'attribute_value' => '29'],
                ['attribute_id' => 5, 'attribute_name' => 'Link de repositorio con antecedentes', 'attribute_value' => 'https://drive.google.com/x'],
            ]]),
            'wd.test/api/v2/workflow_requests*' => fn ($req) => Http::response(str_contains($req->url(), 'page=1')
                ? [[...$lista[0], 'status' => $this->estado], $lista[1], $lista[2], $lista[3]] : []),
        ]);
    }

    public function test_sincroniza_pagos_y_traspasos_de_la_udt(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->fakeWebdox();

        $this->from('/pagos')->post('/pagos/sincronizar')->assertRedirect('/pagos')->assertSessionHas('ok');

        // No se traen la de Contabilidad ni el «Traspaso de recursos»; el «Traspaso (Cobros DEA)» de la UDT queda como tipo «traspaso»
        $this->assertSame([4770 => 'pago', 4772 => 'traspaso'], PagoWebdox::orderBy('webdox_id')->pluck('tipo', 'webdox_id')->all());
        $p = PagoWebdox::where('webdox_id', 4770)->first();
        $this->assertSame(['29', 11, '2026-10-27', 'Mario Cuadra Seguel', 'taken'],
            [$p->correlativo, $p->cantidad_documentos, $p->fecha_certificado->toDateString(), $p->aprobador, $p->estado]);

        $this->get('/pagos')->assertInertia(fn (Assert $page) => $page->component('Pagos')->has('pagos', 1)->where('pagos.0.correlativo', '29'));

        // Al terminar en Webdox, la siguiente sincronización actualiza el estado (sin duplicar)
        $this->estado = 'finished';
        $this->post('/pagos/sincronizar');
        $this->assertSame(2, PagoWebdox::count());
        $this->assertSame('finished', PagoWebdox::where('webdox_id', 4770)->value('estado'));
    }

    public function test_pagos_exige_sesion(): void
    {
        $this->get('/pagos')->assertRedirect('/login');
        $this->post('/pagos/sincronizar')->assertRedirect('/login');
    }
}
