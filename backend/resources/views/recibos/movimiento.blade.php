@extends('layouts.pdf')

@section('estilos')
        body { font-size: 13px; color: #2b1c14; }
        table.recibo-fila { width: 100%; }
        table.recibo-fila > tr > td, table.recibo-fila td.folio { vertical-align: top; padding: 0; }
        .folio { text-align: right; width: 190px; }
        .folio .numero { font-size: 22px; font-weight: bold; letter-spacing: 1px; }
        .folio .tipo { font-size: 11px; color: #6b5c4f; text-transform: uppercase; }
        table.recibo-datos { width: 100%; }
        table.recibo-datos td { padding: 6px 0; vertical-align: top; }
        table.recibo-datos td.etiqueta { color: #6b5c4f; width: 160px; }
        .importe { font-size: 20px; font-weight: bold; margin: 18px 0; }
        /* la linea dorada de abajo cierra el recibo (igual a la del encabezado) */
        .pie { margin-top: 28px; padding-bottom: 10px; border-bottom: 2px solid #b8860b; font-size: 11px; color: #6b5c4f; }
        .anulado { border: 2px solid #a81f2e; color: #a81f2e; padding: 10px 14px; margin-bottom: 14px; }
        .anulado .sello { font-size: 22px; font-weight: bold; letter-spacing: 4px; }
        .anulado .detalle { font-size: 11px; margin-top: 4px; }
        /* logo mas grande que en los reportes */
        table.cabecera td.logo { width: 110px; }
        table.cabecera img { width: 96px; }
        .titulo { margin-top: 0; font-size: 18px; }
        .subtitulo { margin-top: 4px; font-size: 11px; }
        table.cabecera { margin-bottom: 10px; }
        .salto-pagina { page-break-after: always; }
        /* absolute (no fixed): solo en la pagina de la copia */
        .marca-copia { position: absolute; top: 128px; left: 0; width: 100%; text-align: center; font-size: 115px;
            font-weight: bold; color: #723314; opacity: 0.10; transform: rotate(-30deg); letter-spacing: 10px; }
        .marca-agua { position: fixed; top: 17%; left: 0; width: 100%; text-align: center; font-size: 100px;
            font-weight: bold; color: #a81f2e; opacity: 0.12; transform: rotate(-30deg); }
@endsection

@section('contenido')
    {{-- position: fixed => se repite en las dos paginas (original y copia) --}}
    @if ($movimiento->anulado())
        <div class="marca-agua">ANULADO</div>
    @endif

    {{-- Original para el propietario --}}
    @include('recibos._cuerpo', ['copia' => false])

    {{-- Copia para el hotel, en otra pagina y con su propia cabecera --}}
    <div class="salto-pagina"></div>
    @include('layouts._cabecera-pdf')
    @include('recibos._cuerpo', ['copia' => true])
@endsection
