{{-- Filas de movimientos del estado de cuenta. Variables: $filas (colección) y $offset (posicion global de la primera, para alternar el fondo). --}}
@use('App\Support\Formato')
@foreach ($filas as $m)
    <tr @class(['zebra' => ($offset + $loop->index) % 2 === 1])>
        <td class="c-fecha">{{ Formato::fecha($m['fecha']) }}</td>
        <td class="c-desc">
            {{ $m['descripcion'] }}
            @if ($m['observacion'])
                <span class="obs">{{ $m['observacion'] }}</span>
            @endif
        </td>
        <td class="der c-monto">{{ $m['cargo'] ? Formato::monto($m['cargo']) : '' }}</td>
        <td class="der c-monto">{{ $m['credito'] ? Formato::monto($m['credito']) : '' }}</td>
        <td class="der c-saldo">{{ Formato::monto($m['saldo']) }}</td>
    </tr>
@endforeach
