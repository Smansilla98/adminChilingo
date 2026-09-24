@extends('layouts.app')

@section('title', 'Editar sede')
@section('page-title', 'Editar sede')

@section('content')
<x-ito.shell-page :title="'Editar '.$sede->nombre" subtitle="Los porcentajes de reparto se usan al registrar pagos." :plain="true">
    <form action="{{ route('sedes.update', $sede) }}" method="POST">
        @csrf
        @method('PUT')
        @include('sedes._form', ['sede' => $sede])
        <x-ito.form-actions :cancel="route('sedes.show', $sede)" submit="Guardar cambios" />
    </form>
</x-ito.shell-page>
@endsection
