@extends('layouts.archivo')

@section('body-class', 'archivo--aporte')

@php
    $estilos = ['borrador' => 'gris', 'pendiente' => 'amarillo', 'cambios' => 'naranja', 'rechazada' => 'rojo', 'publicada' => 'verde', 'oculta' => 'gris'];
@endphp

@section('content')
<div class="ar-aporte ar-aporte--detalle">
    <p class="ar-sobretitulo ar-mono"><a href="{{ route('archivo.aportes.index') }}">← Mis aportes</a></p>

    <div class="ar-aporte-detalle">
        <div class="ar-aporte-detalle__foto">
            @include('archivo.partials.figura', ['foto' => $foto, 'sizes' => '(min-width: 900px) 45vw, 100vw', 'eager' => true])
            <p class="ar-tenue ar-form__ayuda">
                {{ $foto->nombre_original }} · {{ $foto->ancho }}×{{ $foto->alto }} px
                · <a href="{{ route('archivo.original', $foto) }}">Descargar original</a>
            </p>
        </div>

        <div class="ar-aporte-detalle__texto">
            <p class="ar-aporte-tarjeta__estado ar-aporte-tarjeta__estado--grande"><span class="ar-estado ar-estado--{{ $estilos[$foto->estado] ?? 'gris' }}" aria-hidden="true"></span>{{ $foto->etiquetaEstado() }}</p>

            @if($foto->estado === 'cambios' && $foto->notas_revision)
                <div class="ar-nota ar-nota--cambios" role="note">
                    <strong>El equipo del archivo te pide:</strong>
                    <p>{!! nl2br(e($foto->notas_revision)) !!}</p>
                </div>
            @elseif($foto->estado === 'rechazada' && $foto->motivo_rechazo)
                <div class="ar-nota ar-nota--rechazo" role="note">
                    <strong>Por qué no se sumó:</strong>
                    <p>{!! nl2br(e($foto->motivo_rechazo)) !!}</p>
                </div>
            @elseif($foto->estado === 'publicada')
                <div class="ar-nota" role="note">
                    <p>¡Ya es parte del archivo! <a class="ar-enlace" href="{{ $foto->url() }}">Verla publicada →</a></p>
                </div>
            @endif

            @if($editable)
                <form class="ar-form" method="POST" action="{{ route('archivo.aportes.update', $foto) }}">
                    @csrf @method('PUT')
                    @include('archivo.partials.campos-aporte', ['foto' => $foto])
                    <div class="ar-form__acciones">
                        @can('enviar', $foto)
                            <button type="submit" class="ar-boton" name="enviar" value="1">{{ $foto->estado === 'cambios' ? 'Guardar y reenviar' : 'Guardar y enviar al archivo' }}</button>
                        @endcan
                        <button type="submit" class="ar-boton ar-boton--linea">Guardar cambios</button>
                    </div>
                </form>
            @else
                <h1 class="ar-ficha__titulo">{{ $foto->tituloVisible() }}</h1>
                @if($foto->descripcion)<p>{{ $foto->descripcion }}</p>@endif
            @endif

            @can('delete', $foto)
                <form method="POST" action="{{ route('archivo.aportes.destroy', $foto) }}" class="ar-form__borrar" onsubmit="return confirm('¿Eliminar esta foto de tus aportes?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="ar-enlace ar-enlace--peligro">Eliminar este aporte</button>
                </form>
            @endcan

            @if($foto->revisiones->isNotEmpty())
                <section class="ar-historial" aria-labelledby="historial">
                    <h2 class="ar-etiqueta" id="historial">Historial</h2>
                    <ol>
                        @foreach($foto->revisiones as $r)
                            <li>
                                <span class="ar-mono ar-tenue">{{ $r->created_at?->format('d/m/Y H:i') }}</span>
                                <span>{{ $r->etiqueta() }}</span>
                                @if($r->notas && in_array($r->accion, ['cambios', 'rechazada', 'aprobada'], true))<span class="ar-tenue">— {{ \Illuminate\Support\Str::limit($r->notas, 160) }}</span>@endif
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/archivo-carga.js') }}?v=1" defer></script>
@endpush
