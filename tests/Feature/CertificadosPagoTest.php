<?php

namespace Tests\Feature;

use App\Models\CertificadoPago;
use App\Models\LineaPago;
use App\Models\User;
use App\Support\CertificadosPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class CertificadosPagoTest extends TestCase
{
    use RefreshDatabase;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = storage_path('framework/testing/certificados');
        File::deleteDirectory($this->base);
        $dir = $this->base . '/2026/Proveedores/09. Septiembre/ID4397_02_SEPTIEMBRE';
        File::makeDirectory($dir, 0755, true);

        // Igual que los certificados reales: texto arriba, tabla desde la fila 5, filas numeradas vacías y fila «Total»
        $s = new Spreadsheet();
        $h = $s->getActiveSheet();
        $h->fromArray(['Fecha: ', null, 46297], null, 'A1');
        $h->setCellValue('A2', 'Certificado y Solicitud de pago de proveedores N° 024/2026');
        $h->fromArray(['N°', 'Nombre Empresa', 'Rut', 'N° FACTURA / NC', 'Monto Neto', 'Iva ', 'Imp. Especifico / Exento', 'Total ', 'Cuenta Contable', 'Unidad', 'Centro de Costo', 'OBS. '], null, 'A5');
        $h->fromArray([1, 'POLODEV SPA', '11.111.111-1', 917, 2150000, 408500, null, 2558500, 510207020, 'UDT', 120513014201001, 'Licencia Gitkraken'], null, 'A6');
        $h->fromArray([2, 'DATXER CHILE S.A.', '22.222.222-2', 684901, 11650245, 2213546.55, null, 13863791.55, 'VARIOS', 'VARIOS', 'PRORRATEO ADJUNTO', 'Impresión'], null, 'A7');
        $h->fromArray([3, 0, 0], null, 'A8');
        $h->fromArray([null, 'Total ', 13800245, 2622046.55, 0, 16422291.55], null, 'A9');
        $h->setCellValue('A10', 'Mario Cuadra S.');
        (new Xlsx($s))->save($dir . '/ID4397_Certificado de Pago.xlsx');
        File::put($dir . '/ID4397_Certificado de Pago Firmado.pdf', 'x'); // no se lee
        File::put($dir . '/~$ID4397_Certificado de Pago.xlsx', 'x'); // temporal de Excel, no se lee
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);
        parent::tearDown();
    }

    public function test_lee_el_certificado_y_guarda_una_linea_por_factura(): void
    {
        $r = (new CertificadosPago())->importarCarpeta($this->base);
        $this->assertSame(['leidos' => 1, 'sin_cambios' => 0, 'errores' => []], $r);

        $c = CertificadoPago::firstOrFail();
        $this->assertSame([4397, '024/2026', '2026-10-02'], [$c->webdox_id, $c->numero, $c->fecha->toDateString()]);

        $l = LineaPago::orderBy('n')->get();
        $this->assertCount(2, $l); // la fila numerada vacía y la de «Total» no cuentan
        $this->assertSame(['POLODEV SPA', '11.111.111-1', '917', 2150000, 408500, 2558500, '510207020', '120513014201001'],
            [$l[0]->proveedor, $l[0]->rut, $l[0]->factura, $l[0]->neto, $l[0]->iva, $l[0]->total, $l[0]->cuenta_contable, $l[0]->centro_costo]);
        $this->assertSame([2213547, 13863792, 'VARIOS'], [$l[1]->iva, $l[1]->total, $l[1]->cuenta_contable]);
        $this->assertSame([4397, 4397], $l->pluck('webdox_id')->all());

        // Sin cambios en el archivo no se vuelve a leer
        $this->assertSame(['leidos' => 0, 'sin_cambios' => 1, 'errores' => []], (new CertificadosPago())->importarCarpeta($this->base));
        $this->assertSame(2, LineaPago::count());
    }

    public function test_la_pantalla_muestra_las_facturas(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        config(['services.webdox.pagos.certificados' => $this->base]);

        $this->from('/pagos')->post('/pagos/certificados')->assertRedirect('/pagos')->assertSessionHas('ok');
        $this->get('/pagos')->assertInertia(fn (Assert $page) => $page->has('lineas', 2)
            ->where('lineas.0.webdox_id', 4397)->where('lineas.0.numero', '024/2026'));
    }

    public function test_el_detalle_muestra_la_solicitud_y_las_facturas_del_certificado(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        (new CertificadosPago())->importarCarpeta($this->base);
        \App\Models\PagoWebdox::create(['webdox_id' => 4397, 'tipo' => 'pago', 'titulo' => 'Pago a proveedores - Unidad de Desarrollo Tecnológico - 24',
            'estado' => 'finished', 'formulario' => 'Pago a proveedores', 'creada_en' => now(), 'correlativo' => '24', 'departamento' => 'Unidad de Desarrollo Tecnológico',
            'atributos' => ['Nombre de solicitante' => 'Tiare Aránguiz Cortez', 'Cargo de aprobador de pago' => 'Jefe(a)']]);

        $this->get('/pagos')->assertInertia(fn (Assert $page) => $page->where('pagos.0.departamento', 'Unidad de Desarrollo Tecnológico'));
        $this->get('/pagos/4397')->assertInertia(fn (Assert $page) => $page->component('PagoDetalle')
            ->where('solicitud.correlativo', '24')->where('certificado.numero', '024/2026')->where('certificado.descargable', true)
            ->has('certificado.lineas', 2)->where('certificado.lineas.0.proveedor', 'POLODEV SPA'));
        $this->get('/pagos/4397/excel')->assertOk()->assertDownload('ID4397_Certificado de Pago.xlsx');
        $this->get('/pagos/9999')->assertNotFound();
    }

    public function test_ejecucion_cruza_por_unidad_y_cuenta(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        (new CertificadosPago())->importarCarpeta($this->base);
        \App\Models\Partida::forceCreate(['anio' => 2026, 'version' => 'inicial', 'unidad' => 'Gabinete UDT', 'actividad' => 'TI', 'subactividad' => 'Licencias', 'sigma' => 'Servicios', 'dpto' => 'UDT',
            'cuenta' => '510207020 Licencias', 'un_codigo' => '12051301', 'dep_codigo' => '12051300', 'm1' => 5000000]);
        $this->post('/anio', ['anio' => 2026, 'version' => 'inicial']);

        $this->get('/ejecucion')->assertInertia(fn (Assert $page) => $page->component('Ejecucion')
            ->where('filas', fn ($f) => collect($f)->firstWhere('clave', '510207020')['pagado'] === 2558500)
            ->has('sinPartida', 1)->where('sinPartida.0.motivo', 'varios'));
    }
}
