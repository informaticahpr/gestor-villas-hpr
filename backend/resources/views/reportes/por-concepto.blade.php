@extends('layouts.pdf')
@use('App\Support\Formato')

@section('estilos')
        /* anchos en % (suman 100): fecha, correlativo y villa justos; la descripcion con el resto */
        table.concepto { table-layout: fixed; }
@endsection

@section('contenido')
    <table class="datos concepto">
        <thead>
            <tr>
                <th style="width: 8%;">Fecha</th>
                <th style="width: 9%;">Correlativo No.</th>
                <th style="width: 5%;">Villa</th>
                <th style="width: 20%;">Propietario</th>
                <th style="width: {{ $esCargo ? 49 : 39 }}%;">Descripción</th>
                @unless ($esCargo)
                    <th style="width: 10%;">Forma de pago</th>
                @endunless
                <th class="der" style="width: 9%;">Importe</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $f)
                <tr>
                    <td>{{ Formato::fecha($f['fecha']) }}</td>
                    <td>{{ $f['correlativo'] ?? '—' }}</td>
                    <td>{{ $f['villa'] }}</td>
                    <td>{{ $f['propietario'] }}</td>
                    <td>{{ $f['descripcion'] }}</td>
                    @unless ($esCargo)
                        <td>{{ $f['forma_pago'] ?? '—' }}</td>
                    @endunless
                    <td class="der">{{ Formato::monto($f['importe']) }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $esCargo ? 6 : 7 }}" class="vacio">Sin movimientos de este concepto en el rango</td></tr>
            @endforelse
        </tbody>
        @if (count($filas) > 0)
            <tfoot>
                <tr class="total-grande">
                    <td colspan="{{ $esCargo ? 5 : 6 }}" class="etiqueta">TOTAL ({{ count($filas) }} {{ count($filas) === 1 ? 'movimiento' : 'movimientos' }})</td>
                    <td class="der gran-total">{{ Formato::monto($total) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
@endsection
