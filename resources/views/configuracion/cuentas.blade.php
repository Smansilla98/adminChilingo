@extends('layouts.app')

@section('title', 'Cuentas de alumnos')
@section('page-title', 'Cuentas de alumnos')

@section('content')
<div class="ito-card p-4" style="max-width: 720px">
    <p class="text-muted">Cómo crean su cuenta los alumnos desde la app o la web.</p>
    <form method="POST" action="{{ route('configuracion.cuentas.update') }}">
        @csrf
        @method('PUT')
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" role="switch" id="registro_requiere_codigo" name="registro_requiere_codigo" value="1" @checked($ajustes['registro_requiere_codigo'])>
            <label class="form-check-label fw-semibold" for="registro_requiere_codigo">Pedir código de alta para crear la cuenta</label>
        </div>
        <p class="form-text">
            Además del DNI y la fecha de nacimiento, el alumno tiene que escribir un código de 6 letras o números
            que le das desde su ficha (botón «Código de alta»). El código vence a los 7 días y sirve una sola vez.
            Si está apagado, el código es opcional.
        </p>
        <ul class="small text-muted">
            <li>Los menores de 13 años no pueden crear la cuenta solos: dales acceso desde su ficha.</li>
            <li>Las contraseñas que eligen tienen que tener al menos 8 letras o números.</li>
        </ul>
        <button class="btn btn-primary">Guardar</button>
    </form>
</div>
@endsection
