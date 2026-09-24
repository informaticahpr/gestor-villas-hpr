@extends('layouts.pdf')
@use('App\Support\Formato')

@section('contenido')
    <table class="datos">
        <thead>
            <tr>
                <th style="width: 70px;">Villa</th>
                <th>Propietario</th>
                <th class="der" style="width: 140px;">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($saldos as $s)
                <tr @class(['zebra' => $loop->even])>
                    <td>{{ $s['villa'] }}</td>
                    <td>{{ $s['propietario'] }}</td>
                    <td @class(['der', 'deuda' => $s['saldo'] > 0, 'favor' => $s['saldo'] < 0])>{{ Formato::monto($s['saldo']) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="vacio">Sin resultados</td></tr>
            @endforelse
        </tbody>
        @if (count($saldos) > 0)
            <tfoot>
                <tr class="total-grande">
                    <td colspan="2" class="etiqueta">{{ mb_strtoupper($etiquetaTotal) }}</td>
                    <td class="der gran-total">{{ Formato::monto($total) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
@endsection
