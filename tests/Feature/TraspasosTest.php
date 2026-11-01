<?php

namespace Tests\Feature;

use App\Models\CentroCosto;
use App\Models\LineaTraspaso;
use App\Models\Traspaso;
use App\Models\User;
use App\Support\Traspasos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class TraspasosTest extends TestCase
{
    use RefreshDatabase;

    private string $base;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = storage_path('framework/testing/traspasos');
        File::deleteDirectory($this->base);
        $this->dir = $this->base . '/2026/Traspaso de recursos entre centros de costos/10. Octubre/ID4675';
        File::makeDirectory($this->dir, 0755, true);

        // Asiento como los reales: cargos a las áreas (520214067, debe) y abono a la UDT (620309067, haber)
        File::put($this->dir . '/ID4675 TRASPASO POR HOSTING TF OCTUBRE 2026 DESDE DEPTOS A UDT.txt', implode("\r\n", [
            "120522044201001\t520214067\t2849915\t0\tID4675 TRASPASO POR HOSTING TF OCTUBRE 2026 DESDE DEPTOS A UDT",
            "120532014801001\t520214067\t150085\t0\tID4675 TRASPASO POR HOSTING TF OCTUBRE 2026 DESDE DEPTOS A UDT",
            "120513011103001\t620309067\t0\t3000000\tID4675 TRASPASO POR HOSTING TF OCTUBRE 2026 DESDE DEPTOS A UDT",
        ]));

        // Tarifario: la columna «CECO» da el nombre de cada centro de costo; el área se arrastra a las filas siguientes
        $s = new Spreadsheet();
        $h = $s->getActiveSheet();
        $h->fromArray(['UNIDAD / DEPARTAMENTO / ESCUELAS', 'SUBAREAS', 'CECO', 'TRASPASO HOSTING UDT'], null, 'A14');
        $h->fromArray(['DIRECCIÓN ECONÓMICA Y ADMINISTRATIVA', 'Gabinete DEA', '120522014201001', 100], null, 'A15');
        $h->fromArray([null, 'Planta Física', '120522044201001', 200], null, 'A16');
        (new Xlsx($s))->save($this->dir . '/058_TRASPASOS_FIJOS_OCTUBRE_2026_UDT[1].xlsx');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);
        parent::tearDown();
    }

    public function test_lee_el_asiento_y_el_tarifario(): void
    {
        $r = (new Traspasos())->importarCarpeta($this->base);
        $this->assertSame(['leidos' => 1, 'sin_cambios' => 0, 'errores' => []], $r);

        $t = Traspaso::firstOrFail();
        $this->assertSame([4675, 'cobro', 3000000], [$t->webdox_id, $t->sentido, $t->total]);
        $this->assertSame([2849915, 150085], LineaTraspaso::where('lado', 'cargo')->orderByDesc('monto')->pluck('monto')->all());
        $this->assertSame(['DIRECCIÓN ECONÓMICA Y ADMINISTRATIVA', 'Planta Física'],
            [CentroCosto::where('codigo', '120522044201001')->value('area'), CentroCosto::where('codigo', '120522044201001')->value('nombre')]);

        $this->assertSame(['leidos' => 0, 'sin_cambios' => 1, 'errores' => []], (new Traspasos())->importarCarpeta($this->base));
    }

    public function test_un_asiento_que_no_cuadra_no_se_guarda(): void
    {
        File::put($this->dir . '/malo.txt', "120522044201001\t520214067\t100\t0\tID4675 X\r\n120513011103001\t620309067\t0\t90\tID4675 X");
        $r = (new Traspasos())->importarCarpeta($this->base);
        $this->assertSame(1, $r['leidos']);
        $this->assertStringContainsString('no cuadra', $r['errores'][array_key_first($r['errores'])]);
        $this->assertSame(1, Traspaso::count());
    }

    public function test_el_detalle_muestra_los_campos_de_webdox_y_el_resumen_del_excel(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        config(['services.webdox.pagos.certificados' => $this->base]);
        // Un Excel de detalle como los reales: títulos, tabla desde «CECO», fila «TOTAL» con fórmulas
        $s = new Spreadsheet();
        $h = $s->getActiveSheet();
        $h->setCellValue('A12', 'PRORRATEO CORRESPONDIENTE AL MES DE OCTUBRE 2026');
        $h->fromArray(['UNIDAD / DEPARTAMENTO / ESCUELAS', 'SUBAREAS', 'CECO', 'TRASPASO HOSTING UDT', 'TOTALES POR DEPARTAMENTO'], null, 'A14');
        $h->fromArray(['DIRECCIÓN ECONÓMICA Y ADMINISTRATIVA', 'Gabinete DEA', 120522014201001, 100.4, '=SUM(D15:D15)'], null, 'A15');
        $h->fromArray([null, 'Planta Física', 120522044201001, 200, '=SUM(D16:D16)'], null, 'A16');
        $h->fromArray(['TOTAL', null, null, '=SUM(D15:D16)', '=SUM(E15:E16)'], null, 'A17');
        File::delete($this->dir . '/058_TRASPASOS_FIJOS_OCTUBRE_2026_UDT[1].xlsx');
        (new Xlsx($s))->save($this->dir . '/058_TRASPASOS_FIJOS_OCTUBRE_2026_UDT[2].xlsx');
        \App\Models\PagoWebdox::create(['webdox_id' => 4675, 'tipo' => 'traspaso', 'titulo' => 'Traspaso de recursos (Cobros DEA) - Unidad de Desarrollo Tecnológico',
            'estado' => 'finished', 'formulario' => 'Traspaso (Cobros DEA)', 'creada_en' => now(),
            'atributos' => ['Descripción de traspaso' => 'Traspasos fijos octubre 2026', 'Total de traspaso' => '300', 'Correlativo' => '58', 'Fecha de memo' => '2026-10-13T03:00:00.000Z'],
            'adjuntos' => [['id' => 1, 'nombre' => '058_TRASPASOS_FIJOS_OCTUBRE_2026_UDT.xlsx', 'tipo' => 'xlsx', 'peso' => 1]]]);

        $this->get('/traspasos')->assertInertia(fn (Assert $page) => $page->where('solicitudes.0.total', 300)
            ->where('solicitudes.0.descripcion', 'Traspasos fijos octubre 2026')->where('solicitudes.0.excel', '058_TRASPASOS_FIJOS_OCTUBRE_2026_UDT[2].xlsx'));

        $this->get('/traspasos/4675')->assertInertia(fn (Assert $page) => $page->component('TraspasoDetalle')
            ->where('solicitud.fecha_memo', '2026-10-13')->where('solicitud.correlativo', '58')
            ->has('resumen.filas', 2)->where('resumen.filas.1.0', 'DIRECCIÓN ECONÓMICA Y ADMINISTRATIVA') // área arrastrada
            ->where('resumen.total.4', 300));

        $this->get('/traspasos/4675/excel')->assertOk()->assertDownload('058_TRASPASOS_FIJOS_OCTUBRE_2026_UDT[2].xlsx');
        $this->get('/traspasos/9999')->assertNotFound();
    }

    public function test_la_pantalla_muestra_lo_cobrado_por_area(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        config(['services.webdox.pagos.certificados' => $this->base]);
        // Solo se muestran los comprobantes de solicitudes «Traspaso (Cobros DEA)»; ID3812 es de otro formulario
        \App\Models\PagoWebdox::create(['webdox_id' => 4675, 'tipo' => 'traspaso', 'titulo' => 'Traspaso de recursos (Cobros DEA) - Unidad de Desarrollo Tecnológico',
            'estado' => 'finished', 'formulario' => 'Traspaso (Cobros DEA)', 'creada_en' => now()]);
        $otro = $this->base . '/2026/Traspaso de recursos entre centros de costos/06. Junio/ID3812';
        File::makeDirectory($otro, 0755, true);
        File::put($otro . '/a.txt', "120501094201001\t520214067\t80000\t0\tID3812 DISCO\r\n120513014201001\t620309067\t0\t80000\tID3812 DISCO");

        $this->from('/traspasos')->post('/traspasos/leer')->assertRedirect('/traspasos')->assertSessionHas('ok');
        $this->get('/traspasos')->assertInertia(fn (Assert $page) => $page->component('Traspasos')->has('lineas', 2)
            ->where('lineas.0.centro_costo', '120522044201001')->where('lineas.0.nombre', 'Planta Física')
            ->where('lineas.0.monto', 2849915)->where('lineas.0.webdox_id', 4675));
    }
}
