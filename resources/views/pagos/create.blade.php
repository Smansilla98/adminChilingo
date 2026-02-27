@extends('layouts.app')

@section('title', 'Registrar pago')
@section('page-title', 'Registrar pago')

@section('content')
<div class="card">
    <div class="card-header">Nuevo pago (varios alumnos, una cuota, comprobante PDF)</div>
    <div class="card-body">
        <form action="{{ route('pagos.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Fecha de pago *</label>
                    <input type="date" name="fecha_pago" class="form-control @error('fecha_pago') is-invalid @enderror" value="{{ old('fecha_pago', date('Y-m-d')) }}" required>
                    @error('fecha_pago')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cuota que se paga *</label>
                    <select name="cuota_id" class="form-select @error('cuota_id') is-invalid @enderror" required>
                        <option value="">Seleccionar cuota</option>
                        @foreach($cuotas as $c)
                        <option value="{{ $c->id }}" {{ old('cuota_id') == $c->id ? 'selected' : '' }}>{{ $c->nombre }} — $ {{ number_format($c->monto, 2, ',', '.') }}</option>
                        @endforeach
                    </select>
                    @error('cuota_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Monto total *</label>
                    <input type="number" name="monto_total" class="form-control" step="0.01" min="0" value="{{ old('monto_total') }}" required>
                    @error('monto_total')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Alumnos que pagan (seleccionar uno o varios) *</label>
                <p class="text-muted small">Ctrl+clic para elegir varios. El monto total se repartirá entre los seleccionados.</p>
                <select name="alumno_ids[]" class="form-select @error('alumno_ids') is-invalid @enderror" multiple size="12">
                    @foreach($alumnos as $a)
                    <option value="{{ $a->id }}" {{ in_array($a->id, old('alumno_ids', [])) ? 'selected' : '' }}>{{ $a->nombre_apellido }} @if($a->sede) ({{ $a->sede->nombre }}) @endif</option>
                    @endforeach
                </select>
                @error('alumno_ids')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Comprobante (PDF)</label>
                <input type="file" name="comprobante" class="form-control @error('comprobante') is-invalid @enderror" accept=".pdf">
                @error('comprobante')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Notas</label>
                <textarea name="notas" class="form-control" rows="2">{{ old('notas') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary">Guardar pago</button>
            <a href="{{ route('pagos.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
