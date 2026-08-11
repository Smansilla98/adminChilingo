@extends('layouts.app')

@section('title', 'Cierre de mes')
@section('page-title', 'Cierre de mes')

@section('content')
<div class="ito-page">
    <div class="ito-page-head mb-3">
        <div>
            <p class="hub-eyebrow">Operativo</p>
            <h1 class="ito-page-title">Cierre de mes</h1>
            <p class="ito-page-sub">Checklist para {{ $mesLabel }}.</p>
        </div>
        <div class="ito-page-actions">
            <a href="{{ route('operativo.pendientes') }}" class="btn btn-outline-secondary btn-sm">Pendientes</a>
        </div>
    </div>

    <form method="get" class="d-flex flex-wrap gap-2 align-items-end mb-3">
        <div class="ito-field mb-0">
            <label>Mes</label>
            <select name="mes" class="form-select form-select-sm">
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected($mes === $m)>{{ \Carbon\Carbon::create()->month($m)->locale('es')->translatedFormat('F') }}</option>
                @endfor
            </select>
        </div>
        <div class="ito-field mb-0">
            <label>Año</label>
            <input type="number" name="anio" class="form-control form-control-sm" value="{{ $anio }}" min="2020" max="2100">
        </div>
        <button type="submit" class="btn btn-secondary btn-sm">Cambiar</button>
    </form>

    <div class="mb-3">
        <span class="badge {{ $okCount === $totalCheck ? 'bg-success' : 'bg-warning text-dark' }}">
            {{ $okCount }}/{{ $totalCheck }} ítems OK
        </span>
    </div>

    <div class="list-group operativo-checklist">
        @foreach($checklist as $item)
            <div class="list-group-item d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        @if($item['ok'] === true)
                            <i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i>
                        @elseif($item['ok'] === false)
                            <i class="bi bi-exclamation-circle-fill text-warning" aria-hidden="true"></i>
                        @else
                            <i class="bi bi-circle text-muted" aria-hidden="true"></i>
                        @endif
                        <strong>{{ $item['titulo'] }}</strong>
                    </div>
                    <p class="small text-muted mb-0 mt-1">{{ $item['detalle'] }}</p>
                </div>
                @if(!empty($item['href']) && !empty($item['accion']))
                    <a href="{{ $item['href'] }}" class="btn btn-sm btn-outline-primary">{{ $item['accion'] }}</a>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endsection
