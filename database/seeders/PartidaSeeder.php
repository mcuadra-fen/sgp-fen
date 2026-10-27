<?php

namespace Database\Seeders;

use App\Models\Partida;
use App\Support\Presupuesto;
use Illuminate\Database\Seeder;

class PartidaSeeder extends Seeder
{
    private const DPTO = 'Unidad de Desarrollo Tecnológico';

    public function run(): void
    {
        $basal = 'Aporte basal + Aporte inversiones';

        $filas = [
            // Filas reales entregadas por Mario
            ['Gabinete UDT', 'Aportes', $basal, 'Aportes Universidad', '620308254 Aporte Convenio Actividades de Interes Nacional', 9925158, 'uniforme', '12051300', '12051301', false, 0],
            ['Gabinete UDT', 'Aportes', $basal, 'Aportes Universidad', '620311001 Aporte Institucional', 59550949, 'uniforme', '12051300', '12051301', false, 0],
            // Filas de ejemplo (marcadas, se quitan desde Importar y exportar)
            ['Soporte y Redes', 'Operación', 'Licencias de software', 'Gastos de operación', 'Ejemplo: Licencias de software', 18400000, 'trimestral', '12051300', '12051302', true, 0],
            ['Soporte y Redes', 'Inversión', 'Equipamiento de red', 'Inversiones', 'Ejemplo: Equipamiento de red', 12500000, 'unico', '12051300', '12051302', true, 5],
            ['Gabinete UDT', 'Operación', 'Servicios de nube', 'Gastos de operación', 'Ejemplo: Servicios de nube', 9600000, 'uniforme', '12051300', '12051301', true, 0],
        ];

        foreach ($filas as [$unidad, $actividad, $sub, $sigma, $cuenta, $total, $modo, $dep, $un, $ejemplo, $mes]) {
            $p = new Partida([
                'anio' => (int) date('Y'),
                'dpto' => self::DPTO,
                'unidad' => $unidad,
                'actividad' => $actividad,
                'subactividad' => $sub,
                'sigma' => $sigma,
                'cuenta' => $cuenta,
                'dep_codigo' => $dep,
                'un_codigo' => $un,
                'es_ejemplo' => $ejemplo,
            ]);
            $p->setMeses(Presupuesto::reparto($total, $modo, $mes))->save();
        }
    }
}
