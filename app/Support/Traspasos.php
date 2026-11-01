<?php

namespace App\Support;

use App\Models\CentroCosto;
use App\Models\Traspaso;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Lee de la carpeta compartida los traspasos de recursos de la UDT:
 * año/«Traspaso de recursos entre centros de costos»/mes/ID####/…
 *
 * En cada carpeta ID#### se leen los comprobantes contables «T2026…pdf» (uno por traspaso). Si la carpeta aún no
 * tiene comprobantes se leen los .txt del asiento (centro de costo, cuenta, debe, haber, glosa).
 * Del asiento interesan dos cuentas: 5.2.02.14.067 (cargo al área que paga) y 6.2.03.09.067 (abono al área que recibe).
 * El tarifario «…TRASPASOS_FIJOS….xlsx» da el nombre de cada centro de costo.
 */
class Traspasos
{
    public const CARGO = '520214067';

    public const ABONO = '620309067';

    /** Primeros 8 dígitos de los centros de costo de la UDT (Gabinete y Unidad de Servicios) */
    public const UDT = ['12051301', '12051302'];

    public const CACHE_EXCELS = 'traspasos.excels';

    private const COMPROBANTE = '/^(ID\s*\d+_)?T20\d{8}.*\.pdf$/i';

    /** @return array<int|string, array{id:?int, archivos:string[]}> carpeta ID#### => archivos a leer */
    public function carpetas(string $base): array
    {
        if (! is_dir($base)) {
            throw new RuntimeException("No se encuentra la carpeta compartida: $base");
        }
        $out = [];
        foreach (glob(rtrim($base, '/\\') . '/*', GLOB_ONLYDIR) as $anio) {
            foreach (glob($anio . '/*', GLOB_ONLYDIR) as $sub) {
                if (! Str::startsWith(Str::lower(basename($sub)), 'traspaso')) {
                    continue;
                }
                foreach (glob($sub . '/*/*', GLOB_ONLYDIR) as $dir) {
                    $id = preg_match('/^ID\s*(\d+)/i', basename($dir), $m) ? (int) $m[1] : null;
                    $todos = array_filter(glob($dir . '/*'), 'is_file');
                    $pdf = array_values(array_filter($todos, fn ($f) => preg_match(self::COMPROBANTE, basename($f))));
                    $txt = array_values(array_filter($todos, fn ($f) => Str::endsWith(Str::lower($f), '.txt')));
                    $xlsx = array_values(array_filter($todos, fn ($f) => preg_match('/traspasos_fijos.*\.xlsx$/i', basename($f))
                        && ! Str::contains(Str::lower(basename($f)), 'hoja de trabajo') && ! str_starts_with(basename($f), '~$')));
                    $out[$dir] = ['id' => $id, 'archivos' => $pdf ?: $txt, 'tarifarios' => $xlsx];
                }
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * Lee la carpeta y guarda los comprobantes nuevos o modificados.
     *
     * @return array{leidos:int, sin_cambios:int, errores:array<string,string>}
     */
    public function importarCarpeta(string $base): array
    {
        Cache::forget(self::CACHE_EXCELS);
        $leidos = $sinCambios = 0;
        $errores = [];
        $guardados = Traspaso::pluck('modificado', 'ruta')->all();
        $vigentes = [];
        $tarifario = null;
        foreach ($this->carpetas($base) as $dir => $c) {
            foreach ($c['tarifarios'] as $t) {
                $tarifario = $tarifario && filemtime($tarifario) >= filemtime($t) ? $tarifario : $t;
            }
            foreach ($c['archivos'] as $ruta) {
                $vigentes[] = $ruta;
                if (($guardados[$ruta] ?? null) === filemtime($ruta)) {
                    $sinCambios++;

                    continue;
                }
                try {
                    $this->importar($ruta, $c['id']);
                    $leidos++;
                } catch (Throwable $e) {
                    $errores[basename($dir) . '/' . basename($ruta)] = $e->getMessage();
                }
            }
        }
        // Los .txt se reemplazan por el comprobante PDF cuando éste llega a la carpeta
        Traspaso::whereNotIn('ruta', $vigentes)->get()->each->delete();

        if ($tarifario) {
            try {
                $this->leerTarifario($tarifario);
            } catch (Throwable $e) {
                $errores[basename($tarifario)] = $e->getMessage();
            }
        }

        return ['leidos' => $leidos, 'sin_cambios' => $sinCambios, 'errores' => $errores];
    }

    public function importar(string $ruta, ?int $webdoxId = null): Traspaso
    {
        $d = Str::endsWith(Str::lower($ruta), '.txt') ? $this->leerTxt($ruta) : $this->leerPdf($ruta);
        $webdoxId ??= preg_match('/ID\s*(\d+)/i', (string) $d['glosa'], $m) ? (int) $m[1] : null;

        $cargos = array_sum(array_column(array_filter($d['lineas'], fn ($l) => $l['lado'] === 'cargo'), 'monto'));
        $abonos = array_sum(array_column(array_filter($d['lineas'], fn ($l) => $l['lado'] === 'abono'), 'monto'));
        if (! $d['lineas'] || $cargos !== $abonos) {
            throw new RuntimeException("no cuadra: cargos $cargos, abonos $abonos");
        }
        $esUdt = fn (string $lado) => collect($d['lineas'])->contains(fn ($l) => $l['lado'] === $lado && in_array(substr($l['centro_costo'], 0, 8), self::UDT, true));
        $sentido = $esUdt('abono') ? 'cobro' : ($esUdt('cargo') ? 'pago' : 'otro');

        return DB::transaction(function () use ($ruta, $webdoxId, $d, $cargos, $sentido) {
            Traspaso::where('ruta', $ruta)->get()->each->delete();
            $t = Traspaso::create([
                'webdox_id' => $webdoxId, 'comprobante' => $d['comprobante'], 'fecha' => $d['fecha'], 'glosa' => $d['glosa'],
                'sentido' => $sentido, 'total' => $cargos, 'archivo' => basename($ruta), 'ruta' => $ruta, 'modificado' => filemtime($ruta),
            ]);
            $t->lineas()->createMany(array_map(fn ($l) => $l + ['webdox_id' => $webdoxId], $d['lineas']));

            return $t;
        });
    }

    /** Comprobante contable «Comprobantes Históricos» exportado a PDF. */
    public function leerPdf(string $ruta): array
    {
        $texto = (new Parser())->parseFile($ruta)->getText();
        $lineas = [];
        foreach (preg_split('/\R/u', $texto) as $l) {
            if (! preg_match('/^\d+\s+(\d{8}\.\d{4}\.\d{3})\s+(\d\.\d\.\d\d\.\d\d\.\d{3})\s(.*)$/u', trim($l), $m)) {
                continue;
            }
            $cuenta = str_replace('.', '', $m[2]);
            if ($cuenta !== self::CARGO && $cuenta !== self::ABONO) {
                continue;
            }
            // El monto va al final, después de la fecha 00/00/0000 (y a veces del código de moneda pegado)
            $resto = preg_replace('/\s/u', '', preg_replace('/^.*\d\d\/\d\d\/\d{4}/u', '', $m[3]));
            if (! preg_match('/(\d{1,3}(?:\.\d{3})*)$/', $resto, $x)) {
                throw new RuntimeException('línea sin monto: ' . trim($l));
            }
            $lineas[] = [
                'centro_costo' => str_replace('.', '', $m[1]), 'cuenta' => $cuenta,
                'lado' => $cuenta === self::CARGO ? 'cargo' : 'abono', 'monto' => (int) str_replace('.', '', $x[1]),
            ];
        }
        $glosa = preg_match('/Glosa\s+General:\s*(.*?)\s*(?:L[ií]nea\s*Centro|$)/su', $texto, $g) ? preg_replace('/\s+/u', ' ', $g[1]) : null;

        return [
            'comprobante' => preg_match('/N[úu]mero\s*:\s*(\d+)/u', $texto, $n) ? $n[1] : null,
            'fecha' => preg_match('/Vencimiento:\s*(\d\d\/\d\d\/\d{4})/u', $texto, $f) ? Carbon::createFromFormat('d/m/Y', $f[1])->toDateString() : null,
            'glosa' => $glosa ? trim($glosa) : null,
            'lineas' => $lineas,
        ];
    }

    /** Asiento en texto: centro de costo, cuenta, debe, haber, glosa (separados por tabulación). */
    public function leerTxt(string $ruta): array
    {
        $contenido = file_get_contents($ruta);
        if (! mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }
        $lineas = [];
        $glosa = null;
        foreach (preg_split('/\R/u', $contenido) as $l) {
            $c = array_map('trim', explode("\t", $l));
            if (count($c) < 4 || ! in_array($c[1], [self::CARGO, self::ABONO], true)) {
                continue;
            }
            $glosa ??= $c[4] ?? null;
            $lineas[] = [
                'centro_costo' => $c[0], 'cuenta' => $c[1], 'lado' => $c[1] === self::CARGO ? 'cargo' : 'abono',
                'monto' => (int) round((float) ($c[1] === self::CARGO ? $c[2] : $c[3])),
            ];
        }

        return ['comprobante' => null, 'fecha' => Carbon::createFromTimestamp(filemtime($ruta), config('app.timezone'))->toDateString(), 'glosa' => $glosa, 'lineas' => $lineas];
    }

    /** @return array<int, string> ID de Webdox => Excel de detalle en la carpeta compartida (…/ID####/…xlsx, sin «hoja de trabajo») */
    public function excels(string $base): array
    {
        // Recorrer Google Drive es lento: se guarda 30 minutos (se renueva al «Leer comprobantes»)
        return Cache::remember(self::CACHE_EXCELS, now()->addMinutes(30), function () use ($base) {
            if (! is_dir($base)) {
                return [];
            }
            $out = [];
            // Solo año/«Traspaso…»/mes/ID####, no el resto de la unidad compartida
            foreach (glob(rtrim($base, '/\\') . '/*/[Tt]raspaso*/*/*', GLOB_ONLYDIR) as $dir) {
                if (! preg_match('/^ID\s*(\d+)\b/i', basename($dir), $m)) {
                    continue;
                }
                $xlsx = array_values(array_filter(glob($dir . '/*.xls*'), fn ($f) => ! str_starts_with(basename($f), '~$')
                    && ! Str::contains(Str::lower(basename($f)), 'hoja de trabajo')));
                if ($xlsx) {
                    $out[(int) $m[1]] = $xlsx[0];
                }
            }

            return $out;
        });
    }

    /**
     * Resumen del Excel de detalle: títulos de arriba y la tabla desde la fila con «CECO» hasta la fila «TOTAL»
     * (con las fórmulas calculadas).
     *
     * @return array{titulos:string[], encabezados:string[], filas:array<int, array<int, mixed>>, total:?array<int, mixed>}
     */
    public function resumenExcel(string $ruta): array
    {
        $filas = IOFactory::load($ruta)->getSheet(0)->toArray(null, true, false, false);
        $titulos = [];
        $encabezados = null;
        $tabla = [];
        $total = null;
        $area = null;
        foreach ($filas as $f) {
            $f = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $f);
            if ($encabezados === null) {
                if (in_array('CECO', array_map(fn ($v) => Str::upper((string) $v), $f), true)) {
                    $ultima = max(array_keys(array_filter($f, fn ($v) => $v !== null && $v !== '')));
                    $encabezados = array_map('strval', array_slice($f, 0, $ultima + 1));
                } elseif ($t = implode(' ', array_filter($f, fn ($v) => $v !== null && $v !== ''))) {
                    $titulos[] = $t;
                }

                continue;
            }
            $f = array_slice($f, 0, count($encabezados));
            if (Str::upper((string) $f[0]) === 'TOTAL') {
                $total = $f;

                break;
            }
            if (! array_filter($f, fn ($v) => $v !== null && $v !== '')) {
                continue;
            }
            // El área viene en celdas combinadas: solo en la primera fila del grupo
            $area = ($f[0] ?? '') !== '' ? $f[0] : $area;
            $f[0] = $area;
            $tabla[] = array_map(fn ($v) => is_float($v) ? (int) round($v) : $v, $f);
        }

        return [
            'titulos' => $titulos, 'encabezados' => $encabezados ?? [], 'filas' => $tabla,
            'total' => $total ? array_map(fn ($v) => is_float($v) ? (int) round($v) : $v, $total) : null,
        ];
    }

    /** Tarifario de traspasos fijos: columnas UNIDAD / DEPARTAMENTO, SUBÁREAS y CECO. */
    public function leerTarifario(string $ruta): int
    {
        $lector = IOFactory::createReaderForFile($ruta);
        $lector->setReadDataOnly(true);
        $filas = $lector->load($ruta)->getSheet(0)->toArray(null, false, false, false);
        $col = null;
        $area = null;
        $n = 0;
        foreach ($filas as $f) {
            if ($col === null) {
                foreach ($f as $i => $v) {
                    if (Str::upper(trim((string) $v)) === 'CECO') {
                        $col = $i;
                    }
                }

                continue;
            }
            $ceco = preg_replace('/\D/', '', (string) ($f[$col] ?? ''));
            if (strlen($ceco) !== 15) {
                continue;
            }
            $antes = array_values(array_filter(array_slice($f, 0, $col), fn ($v) => trim((string) $v) !== ''));
            if (count($antes) > 1) {
                $area = trim((string) $antes[0]);
            }
            CentroCosto::updateOrCreate(['codigo' => $ceco], ['area' => $area, 'nombre' => trim((string) end($antes)) ?: $area]);
            $n++;
        }

        return $n;
    }
}
