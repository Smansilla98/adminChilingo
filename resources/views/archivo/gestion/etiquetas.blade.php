@extends('layouts.app')

@section('title', 'Etiquetas — Archivo histórico')
@section('page-title', 'Archivo histórico')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/archivo-gestion.css') }}?v=1">
@endpush

@section('content')
<div class="ito-page agx">
    <div class="ito-page-head">
        <div>
            <p class="hub-eyebrow">Archivo histórico</p>
            <h1 class="ito-page-title">Etiquetas</h1>
            <p class="ito-page-sub">Las etiquetas se comparten con la Biblioteca. Renombrar a un nombre existente fusiona las dos. Se crean escribiéndolas en cada foto.</p>
        </div>
    </div>
    @include('archivo.gestion._nav')

    @if($tags->isEmpty())
        <x-ito.empty title="Todavía no hay etiquetas en el archivo" icon="bi-hash" />
    @else
        <div class="ito-card">
            <div class="ito-table-wrap">
                <table class="ito-table">
                    <thead><tr><th>Etiqueta</th><th>Fotos</th><th>En biblioteca</th>@if($puedeEditar)<th class="text-end">Acciones</th>@endif</tr></thead>
                    <tbody>
                        @foreach($tags as $t)
                            <tr>
                                <td><a href="{{ route('archivo.gestion.fotos', ['tag' => $t->slug]) }}">#{{ $t->nombre }}</a></td>
                                <td>{{ $t->archivo_fotos_count }}</td>
                                <td>{{ $t->biblioteca_count }}</td>
                                @if($puedeEditar)
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('archivo.gestion.etiquetas.update', $t) }}" class="d-inline-flex gap-1">
                                            @csrf @method('PUT')
                                            <input class="form-control form-control-sm" name="nombre" value="{{ $t->nombre }}" aria-label="Nuevo nombre para #{{ $t->nombre }}" style="max-width: 160px">
                                            <button class="btn btn-sm btn-secondary">Renombrar</button>
                                        </form>
                                        <form method="POST" action="{{ route('archivo.gestion.etiquetas.destroy', $t) }}" class="d-inline" data-confirmar="¿Quitar #{{ $t->nombre }} de todas las fotos del archivo?">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">Quitar</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/archivo-gestion.js') }}?v=1" defer></script>
@endpush
