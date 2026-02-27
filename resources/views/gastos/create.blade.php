@extends('layouts.app')

@section('title', 'Nuevo gasto')
@section('page-title', 'Nuevo gasto')

@section('content')
<div class="card">
    <div class="card-header">Registrar gasto</div>
    <div class="card-body">
        <form action="{{ route('gastos.store') }}" method="POST">
            @csrf
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Sede</label>
                    <select name="sede_id" id="sede_id" class="form-select @error('sede_id') is-invalid @enderror">
                        <option value="">— Sin sede —</option>
                        @foreach($sedes as $s)
                        <option value="{{ $s->id }}" {{ old('sede_id') == $s->id ? 'selected' : '' }}>{{ $s->nombre }}</option>
                        @endforeach
                    </select>
                    @error('sede_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Bloque (opcional)</label>
                    <select name="bloque_id" class="form-select @error('bloque_id') is-invalid @enderror">
                        <option value="">— Sin bloque —</option>
                        @foreach($bloques as $b)
                        <option value="{{ $b->id }}" {{ old('bloque_id') == $b->id ? 'selected' : '' }}>{{ $b->nombre }} ({{ $b->sede?->nombre ?? '-' }})</option>
                        @endforeach
                    </select>
                    @error('bloque_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha *</label>
                    <input type="date" name="fecha" class="form-control @error('fecha') is-invalid @enderror" value="{{ old('fecha', date('Y-m-d')) }}" required>
                    @error('fecha')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Tipo *</label>
                    <select name="tipo" id="tipo_gasto" class="form-select @error('tipo') is-invalid @enderror" required>
                        @foreach(\App\Models\Gasto::TIPOS as $k => $v)
                        <option value="{{ $k }}" {{ old('tipo') === $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                    @error('tipo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Subtipo</label>
                    <select name="subtipo" id="subtipo_gasto" class="form-select @error('subtipo') is-invalid @enderror">
                        <option value="">— Opcional —</option>
                    </select>
                    @error('subtipo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Monto *</label>
                    <input type="number" name="monto" class="form-control @error('monto') is-invalid @enderror" step="0.01" min="0" value="{{ old('monto') }}" required>
                    @error('monto')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Descripción</label>
                    <input type="text" name="descripcion" class="form-control @error('descripcion') is-invalid @enderror" value="{{ old('descripcion') }}" placeholder="Ej. Pago luz marzo">
                    @error('descripcion')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Proveedor</label>
                    <input type="text" name="proveedor" class="form-control @error('proveedor') is-invalid @enderror" value="{{ old('proveedor') }}">
                    @error('proveedor')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Notas</label>
                <textarea name="notas" class="form-control @error('notas') is-invalid @enderror" rows="2">{{ old('notas') }}</textarea>
                @error('notas')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('gastos.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function() {
    const subtipos = @json(\App\Models\Gasto::SUBTIPOS ?? []);
    const tipoSelect = document.getElementById('tipo_gasto');
    const subtipoSelect = document.getElementById('subtipo_gasto');
    const oldSubtipo = @json(old('subtipo'));

    function fillSubtipos() {
        const tipo = tipoSelect.value;
        const opts = subtipos[tipo] || {};
        subtipoSelect.innerHTML = '<option value="">— Opcional —</option>';
        for (const [k, v] of Object.entries(opts)) {
            const opt = document.createElement('option');
            opt.value = k;
            opt.textContent = v;
            if (oldSubtipo === k) opt.selected = true;
            subtipoSelect.appendChild(opt);
        }
    }
    tipoSelect.addEventListener('change', fillSubtipos);
    fillSubtipos();
})();
</script>
@endpush
@endsection
