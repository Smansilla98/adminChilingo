@extends('layouts.app')

@section('title', 'Acontecimientos — Archivo histórico')
@section('page-title', 'Archivo histórico')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/archivo-gestion.css') }}?v=1">
@endpush

@section('content')
<div class="ito-page agx">
    <div class="ito-page-head">
        <div>
            <p class="hub-eyebrow">Archivo histórico</p>
            <h1 class="ito-page-title">Acontecimientos</h1>
            <p class="ito-page-sub">Hechos fechados que agrupan fotos: un ensayo, una gira, un show, la apertura de una sede.</p>
        </div>
        <div class="ito-page-actions">
            @can('create', \App\Models\ArchivoAcontecimiento::class)
                <a href="{{ route('archivo.gestion.eventos.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo acontecimiento</a>
            @endcan
        </div>
    </div>
    @include('archivo.gestion._nav')

    <form method="GET" class="agx-filtros" role="search">
        <input type="search" name="q" class="form-control form-control-sm agx-filtros__q" value="{{ $q }}" placeholder="Buscar por título" aria-label="Buscar">
        <select name="capitulo" class="form-select form-select-sm" aria-label="Capítulo">
            <option value="">Todo capítulo</option>
            @foreach($capitulos as $c)<option value="{{ $c->id }}" @selected((string) request('capitulo') === (string) $c->id)>{{ $c->titulo }}</option>@endforeach
        </select>
        <select name="sin" class="form-select form-select-sm" aria-label="Faltantes">
            <option value="">Con o sin portada</option>
            <option value="portada" @selected(request('sin') === 'portada')>Sin portada</option>
        </select>
        <button class="btn btn-sm btn-secondary">Filtrar</button>
    </form>

    @if($acontecimientos->isEmpty())
        <x-ito.empty title="No hay acontecimientos" icon="bi-calendar2-event" />
    @else
        <div class="ito-card">
            <div class="ito-table-wrap">
                <table class="ito-table">
                    <thead><tr><th>Año</th><th>Acontecimiento</th><th>Capítulo</th><th>Fotos</th><th>Estado</th></tr></thead>
                    <tbody>
                        @foreach($acontecimientos as $a)
                            <tr>
                                <td class="ito-mono">{{ $a->fechaLegible() ?? '—' }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="agx-lista__mini" style="background: {{ $a->portada?->color ?: '#2a2725' }}">@if($a->portada)<img src="{{ $a->portada->imagenUrl(400) }}" alt="" loading="lazy">@endif</span>
                                        <a href="{{ route('archivo.gestion.eventos.edit', $a) }}" class="fw-semibold">{{ $a->titulo }}</a>
                                    </div>
                                </td>
                                <td>{{ $a->capitulo?->titulo ?? '—' }}</td>
                                <td><a href="{{ route('archivo.gestion.fotos', ['acontecimiento' => $a->id]) }}">{{ $a->fotos_count }}</a></td>
                                <td><span @class(['ito-status', 'ito-status--success' => $a->publicado, 'ito-status--neutral' => ! $a->publicado])>{{ $a->publicado ? 'Publicado' : 'Borrador' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $acontecimientos->links() }}</div>
    @endif
</div>
@endsection
