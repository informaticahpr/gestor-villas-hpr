{{-- Filas de movimientos del estado de cuenta. Variables: $filas (colección) y $offset (posicion global de la primera, para alternar el fondo). --}}
@use('App\Support\Formato')
@foreach ($filas as $m)
    <tr @class(['zebra' => ($offset + $loop->index) % 2 === 1])>
        <td>{{ Formato::fecha($m['fecha']) }}</td>
        <td>
            {{ $m['descripcion'] }}
            @if ($m['observacion'])
                <span class="obs">{{ $m['observacion'] }}</span>
            @endif
        </td>
        <td class="der">{{ $m['cargo'] ? Formato::monto($m['cargo']) : '' }}</td>
        <td class="der">{{ $m['credito'] ? Formato::monto($m['credito']) : '' }}</td>
        <td class="der">{{ Formato::monto($m['saldo']) }}</td>
    </tr>
@endforeach
