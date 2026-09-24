<?php

namespace App\Services;

use App\Support\Formato;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Arma los .xlsx de los reportes con la misma imagen que los PDF: logo y nombre del hotel,
 * encabezado de tabla en cafe de la marca, filas alternadas, totales resaltados y
 * configuracion de impresion.
 */
class ReporteExcelService
{
    private const EMPRESA = 'Hotel y Villas Palma Real';

    private const MONEDA = '"$"#,##0.00;-"$"#,##0.00';

    private const FECHA = 'dd/mm/yyyy';

    // paleta de la marca (RGB sin '#')
    private const CAFE = '723314';

    private const ORO = 'B8860B';

    private const CREMA = 'FDF4EC';

    private const DURAZNO = 'FBE7D3';

    private const BORDE = 'E5D6BD';

    private const GRIS = '6B5C4F';

    /** Fila donde empieza la tabla (arriba va el encabezado con logo). */
    private const FILA_INICIAL = 5;

    public function saldosGenerales(string $titulo, string $etiquetaTotal, Carbon $hasta, Collection $saldos, string $usuario): Spreadsheet
    {
        [$libro, $hoja] = $this->plantilla($titulo, 'Saldos al '.$hasta->format('d/m/Y'), 'C', PageSetup::ORIENTATION_PORTRAIT, $usuario);
        $this->anchos($hoja, ['A' => 12, 'B' => 50, 'C' => 24]);

        $fila = self::FILA_INICIAL;
        $this->encabezadoTabla($hoja, $fila, ['Villa', 'Propietario', 'Saldo'], ['C']);
        $primera = $fila + 1;

        foreach ($saldos as $i => $s) {
            $fila++;
            $hoja->setCellValue("A{$fila}", $s['villa']);
            $hoja->setCellValue("B{$fila}", $s['propietario']);
            $hoja->setCellValue("C{$fila}", $s['saldo']);
            $this->filaDeDatos($hoja, "A{$fila}:C{$fila}", $i % 2 === 1);
        }

        if ($saldos->isEmpty()) {
            $this->filaVacia($hoja, ++$fila, 'C', 'Sin resultados');
        } else {
            $hoja->getStyle("C{$primera}:C{$fila}")->getNumberFormat()->setFormatCode(self::MONEDA);
            $hoja->getStyle("C{$primera}:C{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $hoja->setAutoFilter("A".self::FILA_INICIAL.":C{$fila}");

            $fila++;
            $hoja->setCellValue("A{$fila}", mb_strtoupper($etiquetaTotal));
            $hoja->mergeCells("A{$fila}:B{$fila}");
            $hoja->setCellValue("C{$fila}", $saldos->sum('saldo'));
            $this->filaDeTotal($hoja, "A{$fila}:C{$fila}", 12);
            $hoja->getStyle("C{$fila}")->getNumberFormat()->setFormatCode(self::MONEDA);
        }

        $this->configurarImpresion($hoja, $fila, true);
        $hoja->freezePane('A'.(self::FILA_INICIAL + 1));

        return $libro;
    }

    public function antiguedadDeSaldos(Carbon $hasta, array $antiguedad, string $usuario): Spreadsheet
    {
        $titulo = 'Antigüedad de Saldos';
        [$libro, $hoja] = $this->plantilla($titulo, 'Saldos al '.$hasta->format('d/m/Y'), 'G', PageSetup::ORIENTATION_LANDSCAPE, $usuario);
        $this->anchos($hoja, ['A' => 12, 'B' => 40, 'C' => 19, 'D' => 19, 'E' => 19, 'F' => 19, 'G' => 25]);

        $buckets = ['d90mas', 'd90', 'd60', 'd30'];

        $fila = self::FILA_INICIAL;
        $this->encabezadoTabla($hoja, $fila, ['Villa', 'Propietario', '+90 días', '90 días', '60 días', '30 días', 'Saldo'], ['C', 'D', 'E', 'F', 'G']);

        foreach ($antiguedad['filas'] as $i => $f) {
            $fila++;
            $hoja->setCellValue("A{$fila}", $f['villa']);
            $hoja->setCellValue("B{$fila}", $f['propietario']);
            foreach ($buckets as $j => $bucket) {
                $hoja->setCellValue([3 + $j, $fila], $f['bucket'] === $bucket ? $f['saldo'] : null);
            }
            $hoja->setCellValue("G{$fila}", $f['saldo']);
            $this->filaDeDatos($hoja, "A{$fila}:G{$fila}", $i % 2 === 1);
        }

        if (count($antiguedad['filas']) === 0) {
            $this->filaVacia($hoja, ++$fila, 'G', 'Ninguna villa con saldo pendiente');
        } else {
            $ultimaDatos = $fila;
            $hoja->getStyle('C'.(self::FILA_INICIAL + 1).":G{$ultimaDatos}")->getNumberFormat()->setFormatCode(self::MONEDA);
            $hoja->getStyle("G".(self::FILA_INICIAL + 1).":G{$ultimaDatos}")->getFont()->setBold(true);
            $hoja->setAutoFilter('A'.self::FILA_INICIAL.":G{$ultimaDatos}");

            $fila++;
            $hoja->setCellValue("A{$fila}", 'SALDOS');
            $hoja->mergeCells("A{$fila}:B{$fila}");
            foreach ($buckets as $j => $bucket) {
                $hoja->setCellValue([3 + $j, $fila], $antiguedad['totales'][$bucket]);
            }
            $hoja->setCellValue("G{$fila}", $antiguedad['total_saldo']);
            $this->filaDeTotal($hoja, "A{$fila}:G{$fila}", 12);
            $hoja->getStyle("C{$fila}:G{$fila}")->getNumberFormat()->setFormatCode(self::MONEDA);

            $fila++;
            $hoja->setCellValue("A{$fila}", 'Porcentajes');
            $hoja->mergeCells("A{$fila}:B{$fila}");
            foreach ($buckets as $j => $bucket) {
                $hoja->setCellValue([3 + $j, $fila], $antiguedad['porcentajes'][$bucket] / 100);
            }
            $hoja->setCellValue("G{$fila}", 1);
            $hoja->getStyle("C{$fila}:G{$fila}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
            $hoja->getStyle("A{$fila}:G{$fila}")->getFont()->setItalic(true)->getColor()->setRGB(self::GRIS);
            $hoja->getStyle("C{$fila}:G{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $this->configurarImpresion($hoja, $fila, true);
        $hoja->freezePane('A'.(self::FILA_INICIAL + 1));

        return $libro;
    }

    /**
     * @param  Collection<int, array{villa: string, propietario: string, saldo_actual: float, estado_cuenta: Collection}>  $reportes
     */
    public function estadoDeCuenta(Collection $reportes, Carbon $desde, Carbon $hasta, ?string $villa, string $usuario): Spreadsheet
    {
        $subtitulo = 'Del '.$desde->format('d/m/Y').' al '.$hasta->format('d/m/Y').' · '.($villa ? "Villa {$villa}" : 'Todas las villas');
        [$libro, $hoja] = $this->plantilla('Estado de Cuenta', $subtitulo, 'F', PageSetup::ORIENTATION_PORTRAIT, $usuario);
        $this->anchos($hoja, ['A' => 12, 'B' => 34, 'C' => 32, 'D' => 15, 'E' => 15, 'F' => 24]);

        $fila = self::FILA_INICIAL;

        foreach ($reportes as $r) {
            $estado = $r['estado_cuenta'];

            // banda con la villa y su saldo actual
            $hoja->setCellValue("A{$fila}", "Villa #{$r['villa']} — {$r['propietario']}");
            $hoja->mergeCells("A{$fila}:C{$fila}");
            $hoja->setCellValue("D{$fila}", 'Saldo a la fecha: '.Formato::monto($r['saldo_actual']));
            $hoja->mergeCells("D{$fila}:F{$fila}");
            $banda = $hoja->getStyle("A{$fila}:F{$fila}");
            $banda->getFont()->setBold(true)->getColor()->setRGB(self::CAFE);
            $banda->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::DURAZNO);
            $banda->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::ORO);
            $hoja->getStyle("D{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $hoja->getRowDimension($fila)->setRowHeight(20);
            $hoja->getStyle("A{$fila}:F{$fila}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

            $fila++;
            $this->encabezadoTabla($hoja, $fila, ['Fecha', 'Descripción', 'Observación', 'Cargo', 'Crédito', 'Saldo'], ['D', 'E', 'F']);

            $fila++;
            $hoja->setCellValue("A{$fila}", 'Saldo inicial');
            $hoja->mergeCells("A{$fila}:E{$fila}");
            $hoja->setCellValue("F{$fila}", $estado['saldo_inicial']);
            $hoja->getStyle("A{$fila}:F{$fila}")->getFont()->setItalic(true)->getColor()->setRGB(self::GRIS);
            $this->bordes($hoja, "A{$fila}:F{$fila}");

            foreach ($estado['movimientos'] as $i => $m) {
                $fila++;
                $hoja->setCellValue("A{$fila}", Date::PHPToExcel(Carbon::parse($m['fecha'])));
                $hoja->setCellValue("B{$fila}", $m['descripcion']);
                $hoja->setCellValue("C{$fila}", $m['observacion']);
                $hoja->setCellValue("D{$fila}", $m['cargo'] ?: null);
                $hoja->setCellValue("E{$fila}", $m['credito'] ?: null);
                $hoja->setCellValue("F{$fila}", $m['saldo']);
                $this->filaDeDatos($hoja, "A{$fila}:F{$fila}", $i % 2 === 1);
                $hoja->getStyle("A{$fila}")->getNumberFormat()->setFormatCode(self::FECHA);
                $hoja->getStyle("A{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $hoja->getStyle("C{$fila}")->getAlignment()->setWrapText(true);
            }

            $fila++;
            // ultima linea de la villa: "SALDO", grande y en negrita
            $hoja->setCellValue("A{$fila}", 'SALDO');
            $hoja->mergeCells("A{$fila}:E{$fila}");
            $hoja->setCellValue("F{$fila}", $estado['saldo_final']);
            $this->filaDeTotal($hoja, "A{$fila}:F{$fila}", 12);

            // formato de moneda para toda la villa (desde el saldo inicial hasta el saldo final)
            $desdeFila = $fila - $estado['movimientos']->count() - 1;
            $hoja->getStyle("D{$desdeFila}:F{$fila}")->getNumberFormat()->setFormatCode(self::MONEDA);
            $hoja->getStyle("D{$desdeFila}:F{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $fila += 2; // fila en blanco entre villas
        }

        if ($reportes->isEmpty()) {
            $this->filaVacia($hoja, $fila, 'F', 'Sin resultados');
        }

        $this->configurarImpresion($hoja, $fila, false);

        return $libro;
    }

    /**
     * @param  Collection<int, array{fecha: Carbon, usuario: string, cambio: string, descripcion: string}>  $registros
     */
    public function bitacora(Collection $registros, string $subtitulo, string $usuario): Spreadsheet
    {
        [$libro, $hoja] = $this->plantilla('Bitácora de Cambios', $subtitulo, 'D', PageSetup::ORIENTATION_LANDSCAPE, $usuario);
        $this->anchos($hoja, ['A' => 20, 'B' => 26, 'C' => 34, 'D' => 80]);

        $fila = self::FILA_INICIAL;
        $this->encabezadoTabla($hoja, $fila, ['Fecha', 'Usuario', 'Cambio', 'Descripción'], []);

        foreach ($registros as $i => $r) {
            $fila++;
            $hoja->setCellValue("A{$fila}", Date::PHPToExcel($r['fecha']));
            $hoja->setCellValue("B{$fila}", $r['usuario']);
            $hoja->setCellValue("C{$fila}", $r['cambio']);
            $hoja->setCellValue("D{$fila}", $r['descripcion']);
            $this->filaDeDatos($hoja, "A{$fila}:D{$fila}", $i % 2 === 1);
            $hoja->getStyle("A{$fila}")->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm');
            $hoja->getStyle("A{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $hoja->getStyle("D{$fila}")->getAlignment()->setWrapText(true);
        }

        if ($registros->isEmpty()) {
            $this->filaVacia($hoja, ++$fila, 'D', 'Sin registros para estos filtros');
        } else {
            $hoja->setAutoFilter('A'.self::FILA_INICIAL.":D{$fila}");

            $fila++;
            $hoja->setCellValue("A{$fila}", 'Total de registros: '.$registros->count());
            $hoja->mergeCells("A{$fila}:D{$fila}");
            $this->filaDeTotal($hoja, "A{$fila}:D{$fila}");
        }

        $this->configurarImpresion($hoja, $fila, true);
        $hoja->freezePane('A'.(self::FILA_INICIAL + 1));

        return $libro;
    }

    /**
     * Hoja nueva con el encabezado del reporte: logo, nombre del hotel, titulo y subtitulo.
     *
     * @return array{0: Spreadsheet, 1: Worksheet}
     */
    private function plantilla(string $titulo, string $subtitulo, string $ultimaColumna, string $orientacion, string $usuario): array
    {
        $libro = new Spreadsheet();
        $libro->getProperties()->setCreator($usuario)->setTitle($titulo);

        $hoja = $libro->getActiveSheet();
        $hoja->setTitle($titulo);
        $hoja->setShowGridlines(false);
        $hoja->getPageSetup()->setOrientation($orientacion);

        // los textos del encabezado ocupan de B a la penultima columna; la ultima queda libre
        // (zona blanca de la derecha) para la fecha de generacion y el usuario
        $ultimaTitulo = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($ultimaColumna) - 1);

        foreach ([1 => [self::EMPRESA, 16, true, self::CAFE, 28], 2 => [mb_strtoupper($titulo), 12, true, self::ORO, 20], 3 => [$subtitulo, 10, false, self::GRIS, 18]] as $fila => [$texto, $tam, $negrita, $color, $alto]) {
            $hoja->setCellValue("B{$fila}", $texto);
            if ($ultimaTitulo !== 'B') {
                $hoja->mergeCells("B{$fila}:{$ultimaTitulo}{$fila}");
            }
            $estilo = $hoja->getStyle("B{$fila}");
            $estilo->getFont()->setSize($tam)->setBold($negrita)->getColor()->setRGB($color);
            $estilo->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setIndent(1);
            $hoja->getRowDimension($fila)->setRowHeight($alto);
        }
        // el subtitulo puede ser largo (ej. el resumen de filtros de la bitacora): se encoge para caber
        $hoja->getStyle('B3')->getAlignment()->setShrinkToFit(true);

        $hoja->setCellValue("{$ultimaColumna}1", 'Generado el '.now()->format('d/m/Y H:i'));
        $hoja->setCellValue("{$ultimaColumna}2", 'Por: '.$usuario);
        $generado = $hoja->getStyle("{$ultimaColumna}1:{$ultimaColumna}2");
        $generado->getFont()->setSize(8)->getColor()->setRGB(self::GRIS);
        $generado->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER)->setShrinkToFit(true);
        $hoja->getStyle("{$ultimaColumna}2")->getFont()->setBold(true);
        $hoja->getStyle("A3:{$ultimaColumna}3")->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::ORO);

        $logo = new Drawing();
        $logo->setName('Logo')->setPath(resource_path('img/logo.png'))->setHeight(60)
            ->setCoordinates('A1')->setOffsetX(6)->setOffsetY(4)->setWorksheet($hoja);

        return [$libro, $hoja];
    }

    /** @param  array<string, float>  $anchos */
    private function anchos(Worksheet $hoja, array $anchos): void
    {
        foreach ($anchos as $columna => $ancho) {
            $hoja->getColumnDimension($columna)->setWidth($ancho);
        }
    }

    /**
     * @param  list<string>  $titulos
     * @param  list<string>  $columnasDerecha  columnas numericas, con el encabezado alineado a la derecha
     */
    private function encabezadoTabla(Worksheet $hoja, int $fila, array $titulos, array $columnasDerecha): void
    {
        $hoja->fromArray($titulos, null, "A{$fila}");
        $ultima = chr(ord('A') + count($titulos) - 1);
        $rango = "A{$fila}:{$ultima}{$fila}";

        $estilo = $hoja->getStyle($rango);
        $estilo->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::CAFE);
        $estilo->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $hoja->getRowDimension($fila)->setRowHeight(22);
        $this->bordes($hoja, $rango);

        foreach ($columnasDerecha as $columna) {
            $hoja->getStyle("{$columna}{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }

    private function filaDeDatos(Worksheet $hoja, string $rango, bool $alterna): void
    {
        $this->bordes($hoja, $rango);
        $hoja->getStyle($rango)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        if ($alterna) {
            $hoja->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::CREMA);
        }
    }

    /** Fila de totales en negrita; con `$tamano` grande (>= 12) la fila se hace mas alta. */
    private function filaDeTotal(Worksheet $hoja, string $rango, int $tamano = 11): void
    {
        $estilo = $hoja->getStyle($rango);
        $estilo->getFont()->setBold(true)->setSize($tamano)->getColor()->setRGB(self::CAFE);
        $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::DURAZNO);
        $estilo->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::ORO);
        $estilo->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        [$inicio] = explode(':', $rango);
        $hoja->getRowDimension((int) preg_replace('/\D/', '', $inicio))->setRowHeight($tamano >= 12 ? 24 : 22);
    }

    private function filaVacia(Worksheet $hoja, int $fila, string $ultimaColumna, string $texto): void
    {
        $hoja->setCellValue("A{$fila}", $texto);
        $hoja->mergeCells("A{$fila}:{$ultimaColumna}{$fila}");
        $estilo = $hoja->getStyle("A{$fila}");
        $estilo->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $estilo->getFont()->setItalic(true)->getColor()->setRGB(self::GRIS);
        $hoja->getRowDimension($fila)->setRowHeight(30);
    }

    private function bordes(Worksheet $hoja, string $rango): void
    {
        $hoja->getStyle($rango)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::BORDE);
    }

    private function configurarImpresion(Worksheet $hoja, int $ultimaFila, bool $repetirEncabezado): void
    {
        $config = $hoja->getPageSetup();
        $config->setPaperSize(PageSetup::PAPERSIZE_LETTER)->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
        $hoja->getPageMargins()->setLeft(0.4)->setRight(0.4)->setTop(0.5)->setBottom(0.6);
        $hoja->getHeaderFooter()->setOddFooter('&L&8'.self::EMPRESA.'&R&8Página &P de &N');

        if ($repetirEncabezado) {
            $config->setRowsToRepeatAtTopByStartAndEnd(self::FILA_INICIAL, self::FILA_INICIAL);
        }
    }
}
