@extends('layouts.app')

@section('title', 'Accesos')
@section('page-title', 'Accesos — Matriz por usuario')

@section('content')
<div class="ito-page">
    <div class="ito-page-head">
        <div>
            <h1 class="ito-page-title">Accesos</h1>
            <p class="ito-page-sub">Quién puede ver qué en el sistema</p>
        </div>
    </div>

    <div class="ito-card">
        <div class="p-3 p-md-4">
            <p class="text-muted mb-3">Elegí una persona y tildá las partes del sistema que puede usar. Los <strong>administradores</strong> siempre ven todo, para no quedar sin acceso.</p>

            <form method="GET" action="{{ route('accesos.index') }}" class="ito-toolbar-filters d-flex flex-wrap align-items-end gap-2 mb-4">
                <div class="ito-field ito-field-grow">
                    <label>Usuario</label>
                    <select name="user_id" class="form-select">
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected(($usuario?->id ?? null) === $u->id)>
                                {{ $u->name ?: $u->username ?: 'Usuario #'.$u->id }} @if($u->email) — {{ $u->email }} @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-secondary btn-sm" type="submit">Ver</button>
            </form>

            @if(!$usuario)
                <div class="alert alert-warning mb-0">No hay usuarios para administrar.</div>
            @else
                <form method="POST" action="{{ route('accesos.update') }}">
                    @csrf
                    <input type="hidden" name="user_id" value="{{ $usuario->id }}">

                    <div class="accordion ito-accordion" id="accModulos">
                        @php $i = 0; @endphp
                        @foreach($agrupado as $grupo => $items)
                            @php $i++; $cid = 'grupo_'.$i; @endphp
                            <div class="accordion-item">
                                <h3 class="accordion-header" id="h_{{ $cid }}">
                                    <button class="accordion-button {{ $i === 1 ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#c_{{ $cid }}" aria-expanded="{{ $i === 1 ? 'true' : 'false' }}" aria-controls="c_{{ $cid }}">
                                        {{ $grupo }}
                                    </button>
                                </h3>
                                <div id="c_{{ $cid }}" class="accordion-collapse collapse {{ $i === 1 ? 'show' : '' }}" aria-labelledby="h_{{ $cid }}" data-bs-parent="#accModulos">
                                    <div class="accordion-body">
                                        <div class="row g-2">
                                            @foreach($items as $it)
                                                <div class="col-md-6">
                                                    <div class="form-check border rounded p-2" style="border-color: var(--border) !important;">
                                                        <input type="hidden" name="access[{{ $it['clave'] }}]" value="0">
                                                        <input class="form-check-input" type="checkbox" name="access[{{ $it['clave'] }}]" value="1" id="acc_{{ md5($it['clave']) }}" @checked($it['valor'])>
                                                        <label class="form-check-label" for="acc_{{ md5($it['clave']) }}">
                                                            {{ $it['etiqueta'] }}
                                                            <span class="text-muted small ito-mono">({{ $it['clave'] }})</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($usuario->isAdmin())
                        <div class="border rounded p-3 my-3" style="border-color: var(--border) !important; background: var(--s2);">
                            <label class="form-label mb-1" for="telefono">WhatsApp (resumen semanal)</label>
                            <input type="text" name="telefono" id="telefono" class="form-control @error('telefono') is-invalid @enderror"
                                   value="{{ old('telefono', $usuario->telefono) }}"
                                   placeholder="Ej. +5491112345678 o 91112345678">
                            <div class="form-text">Si cargás un teléfono, recibirá cada lunes el resumen de asistencias y cuotas pendientes.</div>
                            @error('telefono')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endif

                    <p class="form-text mb-2">Solo se guardan los accesos de la persona elegida arriba. Los administradores siempre ven todo.</p>
                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Guardar accesos</button>
                        <a href="{{ route('dashboard') }}" class="btn btn-secondary">Volver</a>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
