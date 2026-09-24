@extends('layouts.pdf')

@section('contenido')
    <table class="datos">
        <thead>
            <tr>
                <th style="width: 84px;">Fecha</th>
                <th style="width: 78px;">Usuario</th>
                <th style="width: 118px;">Cambio</th>
                <th>Descripción</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($registros as $r)
                <tr @class(['zebra' => $loop->even])>
                    <td>{{ $r['fecha']->format('d/m/Y H:i') }}</td>
                    <td>{{ $r['usuario'] }}</td>
                    <td>{{ $r['cambio'] }}</td>
                    <td>{{ $r['descripcion'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="vacio">Sin registros para estos filtros</td></tr>
            @endforelse
        </tbody>
        @if (count($registros) > 0)
            <tfoot>
                <tr class="total">
                    <td colspan="4">Total de registros: {{ count($registros) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
@endsection
