{{-- Cuerpo del recibo / nota de cargo. Se imprime dos veces (ver movimiento.blade.php): $copia = false para el
     original del propietario, true para la copia que se queda el hotel (solo se distingue por la
     marca de agua COPIA). --}}
@if ($copia && ! $movimiento->anulado())
    <div class="marca-copia">COPIA</div>
@endif
    @if ($movimiento->anulado())
        <div class="anulado">
            <div class="sello">ANULADO</div>
            <div class="detalle">
                Anulado por {{ $movimiento->anuladoPor->name ?? '—' }} el {{ $movimiento->ANULADO_EN->format('d/m/Y H:i') }}.
                Motivo: {{ $movimiento->MOTIVO_ANULACION }}
            </div>
        </div>
    @endif

    <table class="recibo-fila">
        <tr>
            <td>
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
            </td>
            <td class="folio">
                <div class="tipo">Folio</div>
                <div class="numero">{{ $movimiento->correlativo_texto ?? '—' }}</div>
            </td>
        </tr>
    </table>

    <div class="importe">Total: ${{ number_format((float) $movimiento->IMPORTE, 2) }}</div>

    <div class="pie">
        Registrado por {{ $movimiento->usuario->name ?? 'Sistema' }} el {{ $movimiento->created_at?->format('d/m/Y H:i') }}
    </div>
