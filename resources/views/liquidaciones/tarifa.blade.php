@extends('layouts.app')

@section('title', 'Valor hora · '.$profesor->nombre)
@section('page-title', 'Liquidación docente')

@section('content')
<x-ito.shell-page :title="$profesor->nombre" eyebrow="Modalidad y valor hora" subtitle="Elegí cómo cobra este docente. El valor por sede, si lo cargás, reemplaza al general en esa sede.">
    <x-slot:actions>
        <a href="{{ route('liquidaciones.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Liquidaciones</a>
    </x-slot:actions>
    <form method="POST" action="{{ route('liquidaciones.tarifa.update', $profesor) }}" class="ito-card p-3" style="max-width: 40rem">
        @csrf @method('PUT')
        <fieldset class="mb-3">
            <legend class="form-label">Modalidad</legend>
            @foreach($modalidades as $k => $t)
                <div class="form-check"><input class="form-check-input" type="radio" name="modalidad" id="md-{{ $k }}" value="{{ $k }}" @checked($modalidad === $k)><label class="form-check-label" for="md-{{ $k }}">{{ $t }}</label></div>
            @endforeach
        </fieldset>
        <div class="mb-3">
            <label class="form-label" for="vh-gral">Valor hora general ($)</label>
            <input id="vh-gral" type="number" step="0.01" min="0" name="valor_hora" class="form-control" value="{{ $valores['general'] }}">
        </div>
        @foreach($sedes as $s)
            <div class="mb-2">
                <label class="form-label small" for="vh-{{ $s->id }}">Valor hora en {{ $s->nombre }} (vacío = el general)</label>
                <input id="vh-{{ $s->id }}" type="number" step="0.01" min="0" name="sedes[{{ $s->id }}]" class="form-control form-control-sm" value="{{ $valores['por_sede'][$s->id] ?? '' }}">
            </div>
        @endforeach
        <button class="btn btn-primary mt-2">Guardar</button>
    </form>
</x-ito.shell-page>
@endsection
