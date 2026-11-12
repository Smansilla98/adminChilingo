@extends('layouts.guest')

@section('title', 'Cargar comprobante de cuota')
@section('guest-title', 'Cargar comprobante de cuota')
@section('guest-subtitle', 'Ahora el comprobante se manda desde tu cuenta.')

@section('content')
<div class="alert alert-info" role="status">
    Entrá con tu cuenta en la app de La Chilinga o en esta web y mandá el comprobante desde <strong>Mi cuota</strong>.
    Si todavía no tenés cuenta, podés crearla con tu DNI y tu fecha de nacimiento.
</div>
<div class="d-flex flex-wrap gap-2">
    <a href="{{ route('login') }}" class="btn btn-primary">Entrar</a>
    <a href="{{ route('registro.alumno') }}" class="btn btn-outline-primary">Crear mi cuenta</a>
</div>
@endsection
