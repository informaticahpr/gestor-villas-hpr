@extends('layouts.pdf')

@section('estilos')
        body { font-size: 13px; color: #2b1c14; }
        .folio { text-align: right; margin-bottom: 20px; }
        .folio .numero { font-size: 22px; font-weight: bold; letter-spacing: 1px; }
        .folio .tipo { font-size: 11px; color: #6b5c4f; text-transform: uppercase; }
        table.recibo-datos { width: 100%; margin-bottom: 24px; }
        table.recibo-datos td { padding: 6px 0; vertical-align: top; }
        table.recibo-datos td.etiqueta { color: #6b5c4f; width: 160px; }
        .importe { font-size: 20px; font-weight: bold; margin: 20px 0; }
        .leyenda { border-top: 1px solid #cbb994; padding-top: 16px; margin-top: 16px; line-height: 1.6; }
        .pie { margin-top: 40px; font-size: 11px; color: #6b5c4f; }
@endsection

@section('contenido')
    <div class="folio">
        <div class="tipo">Folio</div>
        <div class="numero">{{ $movimiento->FOLIO ?? '—' }}</div>
    </div>

    <table class="recibo-datos">
        <tr>
            <td class="etiqueta">Fecha</td>
            <td>{{ $movimiento->FECHA_APLI->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Villa</td>
            <td>{{ $movimiento->CLV_CLIE }} — {{ $movimiento->villa->nombre_completo }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Concepto</td>
            <td>{{ $movimiento->concepto->DESCR }}</td>
        </tr>
        @if (! $movimiento->concepto->ES_CARGO)
            <tr>
                <td class="etiqueta">Forma de pago</td>
                <td>{{ $movimiento->formaPago->nombre ?? '—' }}</td>
            </tr>
        @endif
        @if ($movimiento->OBS)
            <tr>
                <td class="etiqueta">Descripción</td>
                <td>{{ $movimiento->OBS }}</td>
            </tr>
        @endif
    </table>

    <div class="importe">Total: ${{ number_format((float) $movimiento->IMPORTE, 2) }}</div>

    <div class="leyenda">
        @if ($movimiento->concepto->ES_CARGO)
            Se aplica un cargo de <strong>${{ number_format((float) $movimiento->IMPORTE, 2) }}</strong>
            a la villa {{ $movimiento->CLV_CLIE }} por concepto de <strong>{{ $movimiento->concepto->DESCR }}</strong>.
        @else
            {{ $movimiento->villa->nombre_completo }} paga
            <strong>${{ number_format((float) $movimiento->IMPORTE, 2) }}</strong>
            por concepto de <strong>{{ $movimiento->concepto->DESCR }}</strong>
            mediante <strong>{{ $movimiento->formaPago->nombre ?? '—' }}</strong>.
        @endif
    </div>

    <div class="pie">
        Registrado por {{ $movimiento->usuario->name ?? 'Sistema' }} el {{ $movimiento->created_at?->format('d/m/Y H:i') }}
    </div>
@endsection
