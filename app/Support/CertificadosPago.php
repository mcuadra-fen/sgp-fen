<?php

namespace App\Support;

use App\Models\CertificadoPago;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/**
 * Lee los Excel «ID####_Certificado de Pago.xlsx» de la carpeta compartida (Google Drive) y guarda una línea
 * por factura pagada. El ID del nombre del archivo es el de la solicitud en Webdox.
 *
 * Estructura esperada (primera hoja): «Fecha:», «Certificado … N° 024/2026», y una tabla con encabezado
 * N° | Nombre Empresa | Rut | N° FACTURA / NC | Monto Neto | Iva | Imp. Específico / Exento | Total |
 * Cuenta Contable | Unidad | Centro de Costo | OBS., que termina en la fila «Total».
 */
class CertificadosPago
{
    /** Nombre del archivo: ID4397_Certificado de Pago.xlsx (se excluyen los «Firmado») */
    private const ARCHIVO = '/^ID\s*(\d+)[_\s-].*certificado de pago(?!.*firmad).*\.xlsx$/i';

    /** encabezado normalizado (empieza con) => campo */
    private const COLUMNAS = [
        'nombre empresa' => 'proveedor', 'proveedor' => 'proveedor', 'rut' => 'rut',
        'n factura' => 'factura', 'factura' => 'factura', 'monto neto' => 'neto', 'neto' => 'neto', 'iva' => 'iva',
        'imp especifico' => 'exento', 'exento' => 'exento', 'total' => 'total', 'cuenta' => 'cuenta_contable',
        'unidad' => 'unidad', 'centro de costo' => 'centro_costo', 'centro' => 'centro_costo', 'obs' => 'observacion', 'n' => 'n',
    ];

    /** @return string[] rutas de los certificados en la carpeta (año/Proveedores/mes/ID…/archivo) */
    public function archivos(string $base): array
    {
        if (! is_dir($base)) {
            throw new RuntimeException("No se encuentra la carpeta de certificados: $base");
        }
        $out = [];
        foreach (glob(rtrim($base, '/\\') . '/*', GLOB_ONLYDIR) as $anio) {
            foreach (glob($anio . '/*', GLOB_ONLYDIR) as $sub) {
                if (Str::lower(basename($sub)) !== 'proveedores') {
                    continue;
                }
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sub, RecursiveDirectoryIterator::SKIP_DOTS));
                $it->setMaxDepth(3);
                foreach ($it as $f) {
                    if ($f->isFile() && preg_match(self::ARCHIVO, $f->getFilename()) && ! str_starts_with($f->getFilename(), '~$')) {
                        $out[] = $f->getPathname();
                    }
                }
            }
        }
        sort($out);

        return $out;
    }

    /**
     * Lee la carpeta y guarda los certificados nuevos o modificados.
     *
     * @return array{leidos:int, sin_cambios:int, errores:array<string,string>}
     */
    public function importarCarpeta(string $base): array
    {
        $leidos = $sinCambios = 0;
        $errores = [];
        $guardados = CertificadoPago::pluck('modificado', 'ruta')->all();
        foreach ($this->archivos($base) as $ruta) {
            if (($guardados[$ruta] ?? null) === filemtime($ruta)) {
                $sinCambios++;

                continue;
            }
            try {
                $this->importar($ruta);
                $leidos++;
            } catch (Throwable $e) {
                $errores[basename($ruta)] = $e->getMessage();
            }
        }

        return ['leidos' => $leidos, 'sin_cambios' => $sinCambios, 'errores' => $errores];
    }

    /** Lee un certificado y reemplaza sus líneas. */
    public function importar(string $ruta, ?string $nombre = null): CertificadoPago
    {
        $nombre ??= basename($ruta);
        if (! preg_match(self::ARCHIVO, $nombre, $m)) {
            throw new RuntimeException('El nombre no tiene el formato ID####_Certificado de Pago.xlsx');
        }
        $datos = $this->leer($ruta);

        return DB::transaction(function () use ($m, $nombre, $ruta, $datos) {
            $c = CertificadoPago::updateOrCreate(['webdox_id' => (int) $m[1]], [
                'numero' => $datos['numero'], 'fecha' => $datos['fecha'], 'archivo' => $nombre, 'ruta' => $ruta,
                'modificado' => (int) @filemtime($ruta),
            ]);
            $c->lineas()->delete();
            foreach ($datos['lineas'] as $l) {
                $c->lineas()->create([...$l, 'webdox_id' => $c->webdox_id]);
            }

            return $c;
        });
    }

    /** @return array{numero:?string, fecha:?string, lineas:array<int,array>} */
    public function leer(string $ruta): array
    {
        $reader = IOFactory::createReaderForFile($ruta);
        $reader->setReadDataOnly(true);
        // Valores tal como quedaron guardados en el archivo: no se recalculan fórmulas (la fecha suele ser =HOY())
        $filas = [];
        foreach ($reader->load($ruta)->getSheet(0)->getRowIterator() as $row) {
            $fila = [];
            foreach ($row->getCellIterator() as $cell) {
                $fila[] = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
            }
            $filas[] = $fila;
        }

        $numero = $fecha = null;
        $cabecera = null;
        $lineas = [];
        foreach ($filas as $i => $fila) {
            $texto = implode(' ', array_map(fn ($v) => (string) $v, $fila));
            if ($numero === null && preg_match('/N[°º]\s*(\d+\s*\/\s*\d{4})/u', $texto, $n)) {
                $numero = preg_replace('/\s+/', '', $n[1]);
            }
            if ($fecha === null && $cabecera === null) {
                // «Fecha:» y su valor (número de fecha de Excel) en la celda siguiente con datos
                $celdas = array_values(array_filter($fila, fn ($x) => $x !== null && $x !== ''));
                $pos = array_search(true, array_map(fn ($x) => Str::startsWith($this->norma($x), 'fecha'), $celdas), true);
                if ($pos !== false && is_numeric($celdas[$pos + 1] ?? null)) {
                    $fecha = FechaExcel::excelToDateTimeObject($celdas[$pos + 1])->format('Y-m-d');
                }
            }

            if ($cabecera === null) {
                $normas = array_map(fn ($v) => $this->norma($v), $fila);
                if (in_array('nombre empresa', $normas, true) || in_array('proveedor', $normas, true)) {
                    $cabecera = $this->mapear($normas);
                }

                continue;
            }
            // La fila «Total» cierra la tabla (puede estar en la 1.ª o la 2.ª columna)
            $primera = collect($fila)->first(fn ($x) => $x !== null && $x !== '');
            if (Str::startsWith($this->norma($primera ?? ''), 'total')) {
                break;
            }
            $l = [];
            foreach ($cabecera as $col => $campo) {
                $l[$campo] = $fila[$col] ?? null;
            }
            $proveedor = trim((string) ($l['proveedor'] ?? ''));
            if ($proveedor === '' || $proveedor === '0') {
                continue; // filas numeradas sin datos
            }
            $monto = fn ($k) => (int) round((float) ($l[$k] ?? 0));
            $txt = fn ($k) => ($v = trim((string) ($l[$k] ?? ''))) === '' ? null : $v;
            $lineas[] = [
                'n' => is_numeric($l['n'] ?? null) ? (int) $l['n'] : count($lineas) + 1,
                'proveedor' => $proveedor,
                'rut' => $txt('rut'),
                'factura' => is_float($l['factura'] ?? null) ? (string) (int) $l['factura'] : $txt('factura'),
                'neto' => $monto('neto'), 'iva' => $monto('iva'), 'exento' => $monto('exento'), 'total' => $monto('total'),
                'cuenta_contable' => is_float($l['cuenta_contable'] ?? null) ? sprintf('%.0f', $l['cuenta_contable']) : $txt('cuenta_contable'),
                'unidad' => $txt('unidad'),
                'centro_costo' => is_float($l['centro_costo'] ?? null) ? sprintf('%.0f', $l['centro_costo']) : $txt('centro_costo'),
                'observacion' => $txt('observacion'),
            ];
        }
        if ($cabecera === null) {
            throw new RuntimeException('No se encontró la tabla con el encabezado «Nombre Empresa»');
        }

        return compact('numero', 'fecha', 'lineas');
    }

    /** @return array<int,string> columna => campo */
    private function mapear(array $normas): array
    {
        $out = [];
        foreach ($normas as $col => $h) {
            if ($h === '') {
                continue;
            }
            foreach (self::COLUMNAS as $empieza => $campo) {
                if (($h === $empieza || Str::startsWith($h, $empieza . ' ')) && ! in_array($campo, $out, true)) {
                    $out[$col] = $campo;

                    break;
                }
            }
        }

        return $out;
    }

    /** «N° FACTURA / NC» => «n factura nc»; «Imp. Específico» => «imp especifico» */
    private function norma(mixed $v): string
    {
        $s = Str::lower(Str::ascii(trim((string) $v)));

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', $s)));
    }
}
