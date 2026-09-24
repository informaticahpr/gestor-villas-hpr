@extends('layouts.pdf')
@use('App\Support\Formato')

@section('contenido')
    @php($buckets = ['d90mas' => '+90 días', 'd90' => '90 días', 'd60' => '60 días', 'd30' => '30 días'])

    <table class="datos">
        <thead>
            <tr>
                <th style="width: 50px;">Villa</th>
                <th>Propietario</th>
                @foreach ($buckets as $etiqueta)
                    <th class="der" style="width: 96px;">{{ $etiqueta }}</th>
                @endforeach
                <th class="der" style="width: 116px;">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($antiguedad['filas'] as $f)
                <tr @class(['zebra' => $loop->even])>
                    <td>{{ $f['villa'] }}</td>
                    <td>{{ $f['propietario'] }}</td>
                    @foreach ($buckets as $clave => $etiqueta)
                        <td class="der">{{ $f['bucket'] === $clave ? Formato::monto($f['saldo']) : '' }}</td>
                    @endforeach
                    <td class="der"><strong>{{ Formato::monto($f['saldo']) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="7" class="vacio">Ninguna villa con saldo pendiente</td></tr>
            @endforelse
        </tbody>
        @if (count($antiguedad['filas']) > 0)
            <tfoot>
                <tr class="total-grande">
                    <td colspan="2" class="etiqueta">SALDOS</td>
                    @foreach ($buckets as $clave => $etiqueta)
                        <td class="der">{{ Formato::monto($antiguedad['totales'][$clave]) }}</td>
                    @endforeach
                    <td class="der gran-total">{{ Formato::monto($antiguedad['total_saldo']) }}</td>
                </tr>
                <tr class="subtotal">
                    <td colspan="2">Porcentajes</td>
                    @foreach ($buckets as $clave => $etiqueta)
                        <td class="der">{{ $antiguedad['porcentajes'][$clave] }}%</td>
                    @endforeach
                    <td class="der">100%</td>
                </tr>
            </tfoot>
        @endif
    </table>
@endsection
