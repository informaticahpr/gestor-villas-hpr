{{-- Cabecera de los PDF (logo, titulo, fecha y usuario). El nombre del hotel ya va en el logo. La usa
     layouts/pdf y, para repetirla en la pagina de la copia, el recibo. Necesita $titulo, $usuario y, opcional, $subtitulo. --}}
@php
    $logo ??= 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path('img/logo.png')));
@endphp
<table class="cabecera">
    <tr>
        <td class="logo"><img src="{{ $logo }}" alt=""></td>
        <td>
            <div class="titulo">{{ $titulo }}</div>
            @isset($subtitulo)
                <div class="subtitulo">{{ $subtitulo }}</div>
            @endisset
        </td>
        <td class="generado">
            Generado el {{ now()->format('d/m/Y H:i') }}
            <div class="usuario">Por: {{ $usuario }}</div>
        </td>
    </tr>
</table>
