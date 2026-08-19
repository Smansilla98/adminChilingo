@extends('layouts.app')

@section('title', 'Calendario Villa Gesell')
@section('page-title', 'Calendario')

@section('content')
@include('villa-gesell.partials.nav')

<x-ito.list-page
    title="Calendario de la gira"
    subtitle="Un día por fecha. En cada uno podés generar varias tocadas: qué se toca y dónde."
>
    <x-slot:actions>
        <form action="{{ route('villa-gesell.dias.generar') }}" method="POST" class="d-inline">
            @csrf
            <button class="btn btn-outline-primary btn-sm" type="submit">Completar días faltantes</button>
        </form>
    </x-slot:actions>

    <div class="p-3">
        @forelse($dias as $dia)
            <article class="border rounded p-3 mb-3">
                <header class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                    <h2 class="h5 mb-0">{{ $dia->fecha->format('d/m/Y') }}</h2>
                    <form action="{{ route('villa-gesell.dias.slots', $dia) }}" method="POST" class="d-flex gap-2 align-items-center">
                        @csrf
                        <label class="visually-hidden" for="cant-{{ $dia->id }}">Cantidad de fechas</label>
                        <input id="cant-{{ $dia->id }}" type="number" name="cantidad" min="1" max="12" value="2" class="form-control form-control-sm" style="width:4.5rem">
                        <button class="btn btn-sm btn-outline-secondary" type="submit">Generar fechas</button>
                    </form>
                </header>

                <form action="{{ route('villa-gesell.dias.update', $dia) }}" method="POST" class="mb-3">
                    @csrf
                    @method('PUT')
                    <label class="form-label">Notas del día</label>
                    <div class="input-group">
                        <input type="text" name="notas" class="form-control" value="{{ $dia->notas }}" placeholder="Ensayo, descanso, traslado…">
                        <button class="btn btn-outline-primary" type="submit">Guardar</button>
                    </div>
                </form>

                <div class="d-flex flex-column gap-2 mb-3">
                    @forelse($dia->tocadas as $t)
                        <div class="d-flex flex-wrap gap-2 align-items-end">
                            <form action="{{ route('villa-gesell.tocadas.update', $t) }}" method="POST" class="row g-2 flex-grow-1 align-items-end">
                                @csrf
                                @method('PUT')
                                <div class="col-auto" style="width:4.5rem">
                                    <label class="form-label small mb-0">#</label>
                                    <input type="number" name="orden" class="form-control form-control-sm" value="{{ $t->orden }}" min="1">
                                </div>
                                <div class="col-auto">
                                    <label class="form-label small mb-0">Hora</label>
                                    <input type="time" name="hora" class="form-control form-control-sm" value="{{ $t->hora ? substr((string) $t->hora, 0, 5) : '' }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small mb-0">Qué se toca</label>
                                    <input type="text" name="que" class="form-control form-control-sm" value="{{ $t->que }}" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small mb-0">Dónde</label>
                                    <input type="text" name="donde" class="form-control form-control-sm" value="{{ $t->donde }}" placeholder="Lugar">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-0">Notas</label>
                                    <input type="text" name="notas" class="form-control form-control-sm" value="{{ $t->notas }}">
                                </div>
                                <div class="col-auto">
                                    <button class="btn btn-sm btn-primary" type="submit">Guardar</button>
                                </div>
                            </form>
                            <form action="{{ route('villa-gesell.tocadas.destroy', $t) }}" method="POST" data-confirm="¿Borrar esta fecha?">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit">Borrar</button>
                            </form>
                        </div>
                    @empty
                        <p class="text-muted mb-0">Sin fechas este día. Generá cupos vacíos o cargá una abajo.</p>
                    @endforelse
                </div>

                <form action="{{ route('villa-gesell.tocadas.store', $dia) }}" method="POST" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-2">
                        <label class="form-label">Hora</label>
                        <input type="time" name="hora" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Qué se toca *</label>
                        <input type="text" name="que" class="form-control" required placeholder="Murga, candombe, ronda…">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Dónde</label>
                        <input type="text" name="donde" class="form-control" placeholder="Peatonal, plaza, playa…">
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary" type="submit">Agregar fecha</button>
                    </div>
                </form>
            </article>
        @empty
            <p>No hay días. Guardá las fechas de la gira en el resumen y pulsá “Completar días faltantes”.</p>
        @endforelse
    </div>
</x-ito.list-page>
@endsection
