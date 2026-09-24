{{--
    Base de todos los PDF del sistema (reportes y recibos): logo, nombre del hotel, titulo,
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
        @page { margin: 36px 36px 56px 36px; }
        body { font-family: 'Helvetica', sans-serif; font-size: 10px; color: #34241a; }

        table { border-collapse: collapse; }
        .der { text-align: right; }
        .centro { text-align: center; }

        /* cabecera */
        table.cabecera { width: 100%; margin-bottom: 16px; border-bottom: 2px solid #b8860b; }
        table.cabecera td { vertical-align: middle; padding-bottom: 8px; }
        table.cabecera td.logo { width: 76px; }
        table.cabecera img { width: 66px; }
        .empresa { font-size: 16px; font-weight: bold; color: #723314; text-transform: uppercase; letter-spacing: 1px; }
        .titulo { margin-top: 3px; font-size: 12px; font-weight: bold; color: #b8860b; text-transform: uppercase; }
        .subtitulo { margin-top: 3px; font-size: 9px; color: #6b5c4f; }
        .generado { font-size: 8px; color: #6b5c4f; text-align: right; }
        .generado .usuario { margin-top: 2px; font-weight: bold; }

        /* tablas de datos */
        table.datos { width: 100%; margin-bottom: 4px; }
        table.datos th { background: #723314; color: #ffffff; padding: 6px 7px; font-size: 9px; text-align: left; }
        table.datos th.der { text-align: right; }
        table.datos td { padding: 5px 7px; border-bottom: 1px solid #eadfcf; }
        table.datos tr.zebra td { background: #fdf4ec; }
        table.datos tr.total td { background: #fbe7d3; border-top: 2px solid #b8860b; border-bottom: none; font-weight: bold; font-size: 11px; }
        table.datos tr.subtotal td { font-weight: bold; color: #6b5c4f; }
        table.datos tr.inicial td { color: #6b5c4f; font-style: italic; }
        /* fila de totales grande: Saldos Generales y Antigüedad de Saldos */
        table.datos tr.total-grande td { background: #fbe7d3; border-top: 2px solid #b8860b; border-bottom: none; padding: 7px 7px; font-weight: bold; font-size: 12px; }
        table.datos tr.total-grande td.etiqueta { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
        table.datos tr.total-grande td.gran-total { font-size: 12px; }
        /* ultima linea de cada villa en el estado de cuenta: "SALDO" (mismo tono claro que los demas totales) */
        table.datos tr.saldo-final td { background: #fbe7d3; border-top: 2px solid #b8860b; border-bottom: 2px solid #b8860b; padding: 7px 7px; font-weight: bold; font-size: 12px; text-transform: uppercase; }
        .obs { display: block; margin-top: 2px; font-size: 8px; color: #6b5c4f; }
        .deuda { color: #8c1926; }
        .favor { color: #047857; }
        .vacio { padding: 26px 0; text-align: center; color: #6b5c4f; }

        @yield('estilos')
    </style>
</head>
<body>
    <table class="cabecera">
        <tr>
            <td class="logo"><img src="{{ $logo }}" alt=""></td>
            <td>
                <div class="empresa">Hotel y Villas Palma Real</div>
                <div class="titulo">{{ $titulo }}</div>
                @isset($subtitulo)
                    <div class="subtitulo">{{ $subtitulo }}</div>
                @endisset
            </td>
            <td class="generado">
                Generado el {{ now()->format('d/m/Y H:i') }}
                <div class="usuario">Por: {{ $usuario }}</div>
            </td>
        </tr>
    </table>

    @yield('contenido')
</body>
</html>
