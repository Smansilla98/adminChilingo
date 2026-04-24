@extends('layouts.app')

@section('title', 'Nueva cuota')
@section('page-title', 'Nueva cuota')

@section('content')
<div class="card">
    <div class="card-header">Nueva cuota</div>
    <div class="card-body">
        <p class="text-muted small">La cuota se asocia a un bloque. Podés elegir a qué alumnos aplica; si no elegís ninguno, aplica a todos los del bloque. Un alumno que esté en varios bloques puede pagar la cuota de cada uno.</p>
        <form action="{{ route('cuotas.store') }}" method="POST" id="formCuota">
            @csrf
            <div class="row mb-3">
                <div class="col-md-12">
                    <label class="form-label">Bloque *</label>
                    <select name="bloque_id" id="bloque_id" class="form-select @error('bloque_id') is-invalid @enderror" required>
                        <option value="">Seleccionar bloque</option>
                        @foreach($bloques as $b)
                        <option value="{{ $b->id }}" {{ old('bloque_id') == $b->id ? 'selected' : '' }} data-sede="{{ $b->sede?->nombre ?? '' }}">
                            {{ $b->nombre }} @if($b->sede) ({{ $b->sede->nombre }}) @endif
                        </option>
                        @endforeach
                    </select>
                    @error('bloque_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row mb-3" id="wrapAlumnos" style="display: none;">
                <div class="col-md-12">
                    <label class="form-label">Alumnos que pueden pagar esta cuota</label>
                    <p class="text-muted small mb-1">Opcional: seleccioná uno o varios. Si no seleccionás ninguno, la cuota aplica a todos los alumnos del bloque. Ctrl+clic para elegir varios.</p>
                    <select name="alumno_ids[]" id="alumno_ids" class="form-select @error('alumno_ids') is-invalid @enderror" multiple size="8">
                        {{-- Se llena por JS según bloque elegido --}}
                    </select>
                    @error('alumno_ids')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre') }}" placeholder="Ej. Cuota Marzo 2025" required>
                    @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Año *</label>
                    <input type="number" name="año" class="form-control" value="{{ old('año', date('Y')) }}" min="2020" max="2030" required>
                    @error('año')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Mes (opcional)</label>
                    <select name="mes" class="form-select">
                        <option value="">Sin mes</option>
                        @foreach(\App\Models\FacturacionMensual::nombresMeses() as $n => $nombre)
                        <option value="{{ $n }}" {{ old('mes') == $n ? 'selected' : '' }}>{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Monto *</label>
                    <input type="number" name="monto" class="form-control" step="0.01" min="0" value="{{ old('monto') }}" required>
                    @error('monto')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Descripción</label>
                    <input type="text" name="descripcion" class="form-control" value="{{ old('descripcion') }}">
                </div>
            </div>
            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" name="activo" class="form-check-input" id="activo" value="1" checked>
                    <label class="form-check-label" for="activo">Activa</label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('cuotas.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var bloquesAlumnos = @json($bloques->map(function ($b) {
        return [
            'id' => $b->id,
            'alumnos' => $b->alumnos->map(function ($a) {
                return ['id' => $a->id, 'nombre_apellido' => $a->nombre_apellido];
            })->values()
        ];
    })->keyBy('id'));

    var selectBloque = document.getElementById('bloque_id');
    var wrapAlumnos = document.getElementById('wrapAlumnos');
    var selectAlumnos = document.getElementById('alumno_ids');

    function actualizarAlumnos() {
        var bloqueId = selectBloque.value;
        selectAlumnos.innerHTML = '';
        wrapAlumnos.style.display = bloqueId ? 'block' : 'none';
        if (!bloqueId) return;
        var data = bloquesAlumnos[bloqueId];
        if (data && data.alumnos && data.alumnos.length) {
            data.alumnos.forEach(function(a) {
                var opt = document.createElement('option');
                opt.value = a.id;
                opt.textContent = a.nombre_apellido;
                selectAlumnos.appendChild(opt);
            });
        }
    }

    selectBloque.addEventListener('change', actualizarAlumnos);
    actualizarAlumnos();
});
</script>
@endpush
@endsection
