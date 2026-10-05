<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Support\NombreArchivo;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Salida comun de los controladores que exportan PDF y Excel (reportes y bitacora).
 */
trait ExportaArchivos
{
    /** Nombre del usuario que genera el archivo (se imprime en la cabecera de PDF y Excel). */
    protected function usuarioActual(): string
    {
        return auth()->user()?->name ?? 'Sistema';
    }

    /** Fecha para un nombre de archivo, en DD-MM-AAAA (las barras del formato DD/MM/AAAA no se admiten en nombres). */
    protected function fechaParaArchivo(CarbonInterface $fecha): string
    {
        return NombreArchivo::fecha($fecha);
    }

    /** "01-09-2025-al-20-09-2026" */
    protected function rangoParaArchivo(CarbonInterface $desde, CarbonInterface $hasta): string
    {
        return NombreArchivo::rango($desde, $hasta);
    }

    /** "estado-de-cuenta_A-1_01-09-2025-al-20-09-2026.pdf" (titulo_villa_fecha; ver NombreArchivo). */
    protected function nombreArchivo(string $titulo, ?string $villa, string $fecha, string $extension): string
    {
        return NombreArchivo::armar($titulo, $villa, $fecha, $extension);
    }

    /**
     * dompdf necesita mucha memoria con reportes grandes (ej. estado de cuenta de todas las villas en
     * varios años: ~170 MB) y el limite por defecto de PHP es 128 MB. Se sube solo mientras se genera.
     */
    private const MEMORIA_EXPORTACION = '1024M';

    protected function pdf(string $vista, array $datos, string $archivo, string $orientacion = 'portrait'): Response
    {
        ini_set('memory_limit', self::MEMORIA_EXPORTACION);
        set_time_limit(600);

        // el usuario que genera el reporte aparece en la cabecera, bajo la fecha de generacion
        $datos['usuario'] = $this->usuarioActual();

        $pdf = Pdf::loadView($vista, $datos)->setPaper('letter', $orientacion);
        $pdf->render();

        // pie de pagina con numeracion, dibujado sobre cada pagina ya renderizada
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $fuente = $dompdf->getFontMetrics()->getFont('helvetica');
        // alineado con los margenes laterales de 3 cm (85 pt) y centrado en el margen inferior de 2.54 cm (72 pt)
        $lado = 85;
        $y = $canvas->get_height() - 40;
        $gris = [0.27, 0.27, 0.27]; // gris neutro (ahorro de tinta)
        $canvas->page_text($lado, $y, 'Hotel y Villas Palma Real', $fuente, 8, $gris);
        $canvas->page_text($canvas->get_width() - $lado - 62, $y, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fuente, 8, $gris);

        return $pdf->stream($archivo);
    }

    protected function descargarXlsx(Spreadsheet $spreadsheet, string $nombreArchivo): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $nombreArchivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
