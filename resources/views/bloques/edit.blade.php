@extends('layouts.app')

@section('title', 'Editar bloque')
@section('page-title', 'Editar bloque')

@section('content')
<x-ito.shell-page :title="'Editar '.$bloque->nombre" :subtitle="$bloque->sede?->nombre" :plain="true">
    <form action="{{ route('bloques.update', $bloque) }}" method="POST">
        @csrf
        @method('PUT')
        @include('bloques._form', ['bloque' => $bloque])
        <x-ito.form-actions :cancel="route('bloques.show', $bloque)" submit="Guardar cambios" />
    </form>

    <x-ito.detail-section title="Días y horarios" icon="bi-clock" help="Así aparece el bloque en el calendario y en la toma de asistencia." style="max-width: 1040px">
        @if($bloque->horarios->isNotEmpty())
            <ul class="ito-horarios">
                @foreach($bloque->horarios as $h)
                    <li>
                        <span class="fw-semibold">{{ \App\Models\BloqueHorario::DIAS_SEMANA[$h->dia_semana] ?? $h->dia_semana }}</span>
                        <span class="text-muted">{{ \Carbon\Carbon::parse($h->hora_inicio)->format('H:i') }} a {{ \Carbon\Carbon::parse($h->hora_fin)->format('H:i') }}</span>
                        <form action="{{ route('bloque-horarios.destroy', $h) }}" method="POST" class="ms-auto" data-confirm="¿Quitar este horario?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-ghost text-danger" aria-label="Quitar horario"><i class="bi bi-trash" aria-hidden="true"></i></button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-muted small">Todavía no hay horarios. Sumalos para que aparezcan en el calendario.</p>
        @endif

        <form action="{{ route('bloques.horarios.store', $bloque) }}" method="POST" class="row g-2 align-items-end mt-2">
            @csrf
            <div class="col-sm-4">
                <label class="form-label" for="horario-dia">Día</label>
                <select id="horario-dia" name="dia_semana" class="form-select" required>
                    @foreach(\App\Models\BloqueHorario::DIAS_SEMANA as $n => $nombre)
                        <option value="{{ $n }}">{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-sm-3">
                <label class="form-label" for="horario-desde">Desde</label>
                <input id="horario-desde" type="time" name="hora_inicio" class="form-control" value="18:00" required>
            </div>
            <div class="col-6 col-sm-3">
                <label class="form-label" for="horario-hasta">Hasta</label>
                <input id="horario-hasta" type="time" name="hora_fin" class="form-control" value="19:30" required>
            </div>
            <div class="col-sm-2 d-grid">
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar</button>
            </div>
        </form>
    </x-ito.detail-section>
</x-ito.shell-page>
@endsection
