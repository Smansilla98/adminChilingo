@extends('layouts.app')

@section('title', 'Cargar comprobante')
@section('page-title', 'Cargar comprobante')

@section('content')
<x-ito.shell-page title="Cargar comprobante" subtitle="Las familias lo envían por el enlace público; acá lo cargás vos si te lo alcanzaron en clase." :plain="true">
    <form action="{{ route('comprobantes-cuota-alumnos.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <x-ito.form-section title="De quién y qué mes" icon="bi-person">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="comp-alumno">Alumno</label>
                    <select id="comp-alumno" name="alumno_id" class="form-select @error('alumno_id') is-invalid @enderror" required>
                        <option value="">Elegí un alumno…</option>
                        @foreach($alumnos as $a)
                            <option value="{{ $a->id }}" @selected((int) old('alumno_id') === $a->id)>{{ $a->nombre_apellido }}</option>
                        @endforeach
                    </select>
                    @error('alumno_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label" for="comp-mes">Mes</label>
                    <select id="comp-mes" name="mes" class="form-select @error('mes') is-invalid @enderror" required>
                        @foreach(\App\Models\FacturacionMensual::nombresMeses() as $m => $nombre)
                            <option value="{{ $m }}" @selected((int) old('mes', now()->month) === (int) $m)>{{ $nombre }}</option>
                        @endforeach
                    </select>
                    @error('mes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label" for="comp-anio">Año</label>
                    <input id="comp-anio" type="number" name="año" class="form-control @error('año') is-invalid @enderror" value="{{ old('año', now()->year) }}" min="2000" max="2100" required>
                    @error('año')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="comp-fecha">Fecha de pago</label>
                    <input id="comp-fecha" type="date" name="fecha_pago" class="form-control @error('fecha_pago') is-invalid @enderror" value="{{ old('fecha_pago', now()->toDateString()) }}" required>
                    @error('fecha_pago')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <fieldset>
                        <legend class="form-label required">Bloques que paga</legend>
                        <div class="ito-check-grid">
                            @foreach($bloques as $b)
                                <label class="ito-check-chip" for="comp-bloque-{{ $b->id }}">
                                    <input class="form-check-input" type="checkbox" name="bloque_ids[]" value="{{ $b->id }}" id="comp-bloque-{{ $b->id }}" @checked(in_array($b->id, array_map('intval', (array) old('bloque_ids', [])), true))>
                                    <span>{{ $b->nombre }}@if($b->sede) <span class="text-muted">· {{ $b->sede->nombre }}</span>@endif</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="form-text">Solo los bloques a los que tenés acceso.</div>
                        @error('bloque_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </fieldset>
                </div>
            </div>
        </x-ito.form-section>

        <x-ito.form-section title="Archivo" icon="bi-paperclip">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="comp-file">Comprobante</label>
                    <input id="comp-file" type="file" name="comprobante" class="form-control @error('comprobante') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png" required>
                    <div class="form-text">PDF, JPG o PNG.</div>
                    @error('comprobante')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="comp-notas">Notas</label>
                    <textarea id="comp-notas" name="notas" class="form-control" rows="2" placeholder="Ej.: lo trajo impreso a clase">{{ old('notas') }}</textarea>
                </div>
            </div>
        </x-ito.form-section>

        <x-ito.form-actions :cancel="route('comprobantes-cuota-alumnos.index')" submit="Guardar comprobante" />
    </form>
</x-ito.shell-page>
@endsection
