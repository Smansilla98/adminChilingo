@extends('layouts.app')

@section('title', 'Accesos')
@section('page-title', 'Accesos — Matriz por usuario')

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <h2 class="h5 mb-1">Matriz de accesos</h2>
        <p class="text-muted mb-3">Elegí un usuario y marcá qué módulos puede ver/usar. <strong>Administradores</strong> siempre mantienen acceso total para evitar bloqueos.</p>

        <form method="GET" action="{{ route('accesos.index') }}" class="row g-2 align-items-end mb-3">
            <div class="col-md-6">
                <label class="form-label">Usuario</label>
                <select name="user_id" class="form-select">
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ ($usuario?->id ?? null) === $u->id ? 'selected' : '' }}>
                            {{ $u->name ?: $u->username ?: 'Usuario #'.$u->id }} @if($u->email) — {{ $u->email }} @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-secondary" type="submit">Ver</button>
            </div>
        </form>

        @if(!$usuario)
            <div class="alert alert-warning mb-0">No hay usuarios para administrar.</div>
        @else
            <form method="POST" action="{{ route('accesos.update') }}">
                @csrf
                <input type="hidden" name="user_id" value="{{ $usuario->id }}">

                <div class="accordion" id="accModulos">
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
                                                <div class="form-check border rounded p-2">
                                                    <input type="hidden" name="access[{{ $it['clave'] }}]" value="0">
                                                    <input class="form-check-input" type="checkbox" name="access[{{ $it['clave'] }}]" value="1" id="acc_{{ md5($it['clave']) }}" {{ $it['valor'] ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="acc_{{ md5($it['clave']) }}">
                                                        {{ $it['etiqueta'] }}
                                                        <span class="text-muted small">({{ $it['clave'] }})</span>
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

                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Guardar accesos</button>
                    <a href="{{ route('dashboard') }}" class="btn btn-secondary">Volver</a>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection

