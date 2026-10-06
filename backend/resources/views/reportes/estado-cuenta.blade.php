@extends('layouts.pdf')
@use('App\Support\Formato')

@section('estilos')
        /* mismas columnas en todos los bloques de una villa, para que queden alineados aunque se partan entre paginas */
        table.estado { table-layout: fixed; }
        table.estado tr { page-break-inside: avoid; }
        /* un bloque con esta clase nunca se parte: si no cabe en lo que queda de la pagina, pasa entero a la siguiente */
        .sin-corte { page-break-inside: avoid; }
        /* linea arriba de Fecha / Descripcion / ... para separarlos del nombre de la villa */
        table.datos tr.columnas th { border-top: 1px solid #000000; }
        /* anchos en % (con table-layout fixed, dompdf reparte parejo si no suman 100%): fecha justa, montos
           hasta -$99,999.99 sin partirse y la descripcion con el resto */
        table.estado .c-fecha { width: 12%; }
        table.estado .c-desc { width: 49%; }
        table.estado .c-monto { width: 12.5%; }
        table.estado .c-saldo { width: 14%; }
        /* nombre de la villa y saldo a la fecha, en una linea arriba de la tabla */
        table.titulo-villa { width: 100%; }
        table.titulo-villa td { padding: 4px 7px; font-weight: bold; color: #000000; }
@endsection

@section('contenido')
    {{--
        Corte de paginas. Una villa con pocas filas va en un solo bloque que no se parte. Una villa larga
        se divide en tres: cabeza (titulo + primeras filas), medio y cola (ultimas filas + saldo final).
        La cabeza y la cola no se parten, asi el titulo "Villa #X" nunca queda solo al final de una
        pagina ni el saldo final solo al inicio de otra.
    --}}
    @php
        $maxFilasEnteras = 8;
        $filasCabeza = 3;
        $filasCola = 2;
    @endphp

    @forelse ($reportes as $r)
        @php
            $movs = $r['estado_cuenta']['movimientos'];
            $total = count($movs);
            $partida = $total > $maxFilasEnteras;
            $cabeza = $partida ? $movs->slice(0, $filasCabeza) : $movs;
            $medio = $partida ? $movs->slice($filasCabeza, $total - $filasCabeza - $filasCola) : collect();
            $cola = $partida ? $movs->slice($total - $filasCola) : collect();
        @endphp

        {{-- cabeza: titulo, encabezado de columnas, saldo inicial y primeras filas --}}
        <div class="sin-corte" style="margin-top: {{ $loop->first ? 0 : 18 }}px;">
            <table class="titulo-villa">
                <tr>
                    <td style="font-size: 12px;">Villa #{{ $r['villa'] }} — {{ $r['propietario'] }}</td>
                    <td class="der" style="font-size: 13px;">Saldo a la fecha: {{ Formato::monto($r['saldo_actual']) }}</td>
                </tr>
            </table>
            <table class="datos estado">
                <thead>
                    <tr class="columnas">
                        <th class="c-fecha">Fecha</th>
                        <th class="c-desc">Descripción</th>
                        <th class="der c-monto">Cargo</th>
                        <th class="der c-monto">Crédito</th>
                        <th class="der c-saldo">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="inicial">
                        <td colspan="4">Saldo inicial</td>
                        <td class="der">{{ Formato::monto($r['estado_cuenta']['saldo_inicial']) }}</td>
                    </tr>
                    @include('reportes._filas-estado', ['filas' => $cabeza, 'offset' => 0])
                    @unless ($partida)
                        <tr class="saldo-final">
                            <td colspan="3">SALDO</td>
                            <td colspan="2" class="der">{{ Formato::monto($r['estado_cuenta']['saldo_final']) }}</td>
                        </tr>
                    @endunless
                </tbody>
            </table>
        </div>

        @if ($partida)
            {{-- medio: puede partirse entre paginas --}}
            @if ($medio->isNotEmpty())
                <table class="datos estado">
                    <tbody>
                        @include('reportes._filas-estado', ['filas' => $medio, 'offset' => $filasCabeza])
                    </tbody>
                </table>
            @endif

            {{-- cola: ultimas filas y saldo final, siempre juntas --}}
            <div class="sin-corte">
                <table class="datos estado">
                    <tbody>
                        @include('reportes._filas-estado', ['filas' => $cola, 'offset' => $total - $filasCola])
                        <tr class="saldo-final">
                            <td colspan="3">SALDO</td>
                            <td colspan="2" class="der">{{ Formato::monto($r['estado_cuenta']['saldo_final']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    @empty
        <p class="vacio">Sin resultados</p>
    @endforelse
@endsection
