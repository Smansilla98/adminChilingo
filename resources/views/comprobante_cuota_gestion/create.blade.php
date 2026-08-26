@extends('layouts.app')

@section('title', 'Cargar comprobante')
@section('page-title', 'Cargar comprobante de un alumno')

@section('content')
<x-ito.shell-page
    title="Cargar comprobante"
    eyebrow="Comprobantes"
    subtitle="Carga interna si te lo alcanzaron en clase."
>

        <p class="text-muted">Las familias envían el comprobante por el link público (con DNI). Vos también podés cargarlo acá si te lo alcanzaron en clase.</p>
        <form action="{{ route('comprobantes-cuota-alumnos.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="comp-alumno">Alumno</label>
                <select id="comp-alumno" name="alumno_id" class="form-select" required>
                    <option value="">Elegí alumno…</option>
                    @foreach($alumnos as $a)
                        <option value="{{ $a->id }}">{{ $a->nombre_apellido }}</option>
                    @endforeach
                </select>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label" for="comp-anio">Año</label>
                    <input id="comp-anio" type="number" name="año" class="form-control" value="{{ now()->year }}" min="2000" max="2100" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="comp-mes">Mes</label>
                    <select id="comp-mes" name="mes" class="form-select" required>
                        @foreach(range(1,12) as $m)
                            <option value="{{ $m }}" @selected($m === (int) now()->month)>{{ $m }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="comp-fecha">Fecha de pago</label>
                    <input id="comp-fecha" type="date" name="fecha_pago" class="form-control" value="{{ now()->toDateString() }}" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="comp-bloques">Bloque(s)</label>
                <select id="comp-bloques" name="bloque_ids[]" class="form-select" multiple size="6" required>
                    @foreach($bloques as $b)
                        <option value="{{ $b->id }}">{{ $b->nombre }} @if($b->sede) — {{ $b->sede->nombre }} @endif</option>
                    @endforeach
                </select>
                <div class="form-text">Solo los bloques del alumno y a los que tenés acceso.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="comp-file">Archivo (PDF o imagen)</label>
                <input id="comp-file" type="file" name="comprobante" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="comp-notas">Notas</label>
                <textarea id="comp-notas" name="notas" class="form-control" rows="2" placeholder="Ej. lo trajo impreso a clase"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Guardar comprobante</button>
            <a href="{{ route('comprobantes-cuota-alumnos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </form>
</x-ito.shell-page>

@endsection
