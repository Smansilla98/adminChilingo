@php
    $profesorVinculado = isset($alumno) ? $alumno->profesorPerfil() : null;
@endphp
<div class="card mb-3">
    <div class="card-header">También es profesor</div>
    <div class="card-body">
        @if($profesorVinculado)
        <p class="mb-2">
            <i class="bi bi-person-badge"></i>
            Perfil docente vinculado:
            <a href="{{ route('profesores.show', $profesorVinculado) }}">{{ $profesorVinculado->nombre }}</a>
        </p>
        @else
        <div class="form-check mb-2">
            <input type="hidden" name="crear_perfil_profesor" value="0">
            <input class="form-check-input" type="checkbox" name="crear_perfil_profesor" value="1" id="crear_perfil_profesor" {{ old('crear_perfil_profesor') ? 'checked' : '' }}>
            <label class="form-check-label" for="crear_perfil_profesor">Crear o vincular perfil de profesor con los mismos datos</label>
        </div>
        <p class="text-muted small mb-2">Si ya existe un profesor, podés vincularlo sin crear uno nuevo:</p>
        <select name="vincular_profesor_id" class="form-select form-select-sm">
            <option value="">— Crear nuevo si marcás la casilla —</option>
            @foreach(($profesoresSinVinculo ?? collect()) as $p)
            <option value="{{ $p->id }}" @selected((int) old('vincular_profesor_id') === (int) $p->id)>{{ $p->nombre }} @if($p->email)({{ $p->email }})@endif</option>
            @endforeach
        </select>
        @endif
    </div>
</div>
