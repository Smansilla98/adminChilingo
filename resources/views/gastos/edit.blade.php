@extends('layouts.app')

@section('title', 'Editar gasto')
@section('page-title', 'Editar gasto')

@section('content')
<x-ito.shell-page
    title="Editar gasto"
    subtitle="Corregí el egreso y guardá."
    eyebrow="Gastos"
>
    <x-slot:actions>
        <a href="{{ route('gastos.show', $gasto) }}" class="btn btn-outline-secondary btn-sm">Ver</a>
        <a href="{{ route('gastos.index') }}" class="btn btn-outline-secondary btn-sm">Listado</a>
    </x-slot:actions>

    <form action="{{ route('gastos.update', $gasto) }}" method="POST" class="ito-form">
        @csrf
        @method('PUT')
        <x-ito.form-steps :steps="['Contexto', 'Detalle']" submit-label="Actualizar">
            <x-slot:cancel>
                <a href="{{ route('gastos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </x-slot:cancel>

            <x-ito.form-step :index="0" title="Contexto" help="Sede, bloque y fecha del egreso.">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Sede</label>
                        <select name="sede_id" class="form-select @error('sede_id') is-invalid @enderror">
                            <option value="">— Sin sede —</option>
                            @foreach($sedes as $s)
                            <option value="{{ $s->id }}" {{ old('sede_id', $gasto->sede_id) == $s->id ? 'selected' : '' }}>{{ $s->nombre }}</option>
                            @endforeach
                        </select>
                        @error('sede_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Bloque (opcional)</label>
                        <select name="bloque_id" class="form-select @error('bloque_id') is-invalid @enderror">
                            <option value="">— Sin bloque —</option>
                            @foreach($bloques as $b)
                            <option value="{{ $b->id }}" {{ old('bloque_id', $gasto->bloque_id) == $b->id ? 'selected' : '' }}>{{ $b->nombre }} ({{ $b->sede?->nombre ?? '-' }})</option>
                            @endforeach
                        </select>
                        @error('bloque_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fecha *</label>
                        <input type="date" name="fecha" class="form-control @error('fecha') is-invalid @enderror" value="{{ old('fecha', $gasto->fecha?->format('Y-m-d')) }}" required>
                        @error('fecha')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </x-ito.form-step>

            <x-ito.form-step :index="1" title="Detalle" help="Tipo, monto y descripción.">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Tipo *</label>
                        <select name="tipo" id="tipo_gasto" class="form-select @error('tipo') is-invalid @enderror" required>
                            @foreach(\App\Models\Gasto::TIPOS as $k => $v)
                            <option value="{{ $k }}" {{ old('tipo', $gasto->tipo) === $k ? 'selected' : '' }}>{{ $v }}</option>
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
                        <input type="number" name="monto" class="form-control @error('monto') is-invalid @enderror" step="0.01" min="0" value="{{ old('monto', $gasto->monto) }}" required>
                        @error('monto')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Descripción</label>
                        <input type="text" name="descripcion" class="form-control @error('descripcion') is-invalid @enderror" value="{{ old('descripcion', $gasto->descripcion) }}">
                        @error('descripcion')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Proveedor</label>
                        <input type="text" name="proveedor" class="form-control @error('proveedor') is-invalid @enderror" value="{{ old('proveedor', $gasto->proveedor) }}">
                        @error('proveedor')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notas</label>
                        <textarea name="notas" class="form-control @error('notas') is-invalid @enderror" rows="2">{{ old('notas', $gasto->notas) }}</textarea>
                        @error('notas')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </x-ito.form-step>
        </x-ito.form-steps>
    </form>
</x-ito.shell-page>
@endsection

@push('scripts')
<script>
(function() {
    const subtipos = @json(\App\Models\Gasto::SUBTIPOS ?? []);
    const tipoSelect = document.getElementById('tipo_gasto');
    const subtipoSelect = document.getElementById('subtipo_gasto');
    const currentSubtipo = @json(old('subtipo', $gasto->subtipo));

    function fillSubtipos() {
        const tipo = tipoSelect.value;
        const opts = subtipos[tipo] || {};
        subtipoSelect.innerHTML = '<option value="">— Opcional —</option>';
        for (const [k, v] of Object.entries(opts)) {
            const opt = document.createElement('option');
            opt.value = k;
            opt.textContent = v;
            if (currentSubtipo === k) opt.selected = true;
            subtipoSelect.appendChild(opt);
        }
    }
    tipoSelect.addEventListener('change', fillSubtipos);
    fillSubtipos();
})();
</script>
@endpush
