@extends('layouts.pdf')
@use('App\Support\Formato')

@section('contenido')
    <table class="datos">
        <thead>
            <tr>
                <th style="width: 62px;">Fecha</th>
                <th style="width: 72px;">Folio</th>
                <th style="width: 44px;">Villa</th>
                <th style="width: 150px;">Propietario</th>
                <th>Descripción</th>
                @unless ($esCargo)
                    <th style="width: 80px;">Forma de pago</th>
                @endunless
                <th class="der" style="width: 80px;">Importe</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $f)
                <tr>
                    <td>{{ Formato::fecha($f['fecha']) }}</td>
                    <td>{{ $f['folio'] ?? '—' }}</td>
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
