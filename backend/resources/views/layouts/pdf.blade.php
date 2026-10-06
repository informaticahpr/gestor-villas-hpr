{{--
    Base de todos los PDF del sistema (reportes y recibos): logo, titulo,
    fecha de generacion y usuario que lo genera. Variables: $titulo, $usuario y, opcional, $subtitulo.
    Las plantillas hijas llenan la seccion "contenido" y, si necesitan CSS propio, "estilos".
--}}
@php
    $logo = 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path('img/logo.png')));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        /* margenes: 2.54 cm arriba y abajo, 3 cm a los lados (el pie de pagina se alinea con ellos, ver ExportaArchivos::pdf) */
        @page { margin: 2.54cm 3cm 2.54cm 3cm; }
        /* Impresion en negro y grises, sin fondos de color (ahorro de tinta). Solo el logo queda en color. */
        body { font-family: 'Helvetica', sans-serif; font-size: 10px; color: #000000; }

        table { border-collapse: collapse; }
        .der { text-align: right; }
        .centro { text-align: center; }

        /* cabecera */
        /* sin margen abajo: la fila de titulos de columnas queda centrada entre esta linea y la suya */
        table.cabecera { width: 100%; border-bottom: 1.5px solid #000000; }
        table.cabecera td { vertical-align: middle; padding-bottom: 8px; }
        /* logo de 3 cm (ya trae el nombre del hotel, por eso no se repite en texto) */
        table.cabecera td.logo { width: 3.4cm; }
        table.cabecera img { width: 3cm; }
        .titulo { font-size: 20px; font-weight: bold; color: #000000; text-transform: uppercase; letter-spacing: 1px; }
        .subtitulo { margin-top: 5px; font-size: 12px; color: #444444; }
        .generado { font-size: 9px; color: #444444; text-align: right; }
        .generado .usuario { margin-top: 2px; font-weight: bold; }

        /* tablas de datos */
        table.datos { width: 100%; margin-bottom: 4px; }
        /* encabezados sin fondo (ahorran tinta): letra negra en negrita y una linea debajo */
        table.datos th { color: #000000; font-weight: bold; padding: 7px 7px; font-size: 11.5px; line-height: 13px; vertical-align: middle; text-align: left; border-bottom: 1px solid #000000; }
        table.datos th.der { text-align: right; }
        table.datos td { padding: 5px 7px; border-bottom: 1px solid #d0d0d0; }
        table.datos tr.total td { border-top: 1.5px solid #000000; border-bottom: none; font-weight: bold; font-size: 11px; }
        table.datos tr.subtotal td { font-weight: bold; color: #000000; }
        table.datos tr.inicial td { color: #444444; font-style: italic; }
        /* fila de totales grande: Saldos Generales y Antigüedad de Saldos */
        table.datos tr.total-grande td { border-top: 1.5px solid #000000; border-bottom: none; padding: 7px 7px; font-weight: bold; font-size: 12px; }
        table.datos tr.total-grande td.etiqueta { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
        table.datos tr.total-grande td.gran-total { font-size: 12px; }
        /* ultima linea de cada villa en el estado de cuenta: "SALDO" (sin fondo, para ahorrar tinta) */
        table.datos tr.saldo-final td { border-top: 1.5px solid #000000; border-bottom: 1.5px solid #000000; padding: 7px 7px; font-weight: bold; font-size: 12px; text-transform: uppercase; }
        .obs { display: block; margin-top: 2px; font-size: 8px; color: #444444; }
        .deuda { color: #000000; }
        .favor { color: #000000; }
        .vacio { padding: 26px 0; text-align: center; color: #444444; }

        @yield('estilos')
    </style>
</head>
<body>
    @include('layouts._cabecera-pdf')

    @yield('contenido')
</body>
</html>
