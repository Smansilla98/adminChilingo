@extends('errors.layout')

@section('code', '503')
@section('title', 'Estamos haciendo mantenimiento')
@section('message', __('El sistema vuelve en unos minutos. Gracias por la paciencia.'))
@section('actions')
    <button type="button" class="btn btn-primary" onclick="location.reload()"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Reintentar</button>
@endsection
