@extends('layouts.app')

@section('title', 'Editar profesor')
@section('page-title', 'Editar profesor')

@section('content')
<x-ito.shell-page :title="'Editar '.$profesor->nombre" subtitle="Datos, cuenta de acceso, bloques y sedes." :plain="true">
    <form action="{{ route('profesores.update', $profesor) }}" method="POST">
        @csrf
        @method('PUT')
        @include('profesores._form_datos', ['profesor' => $profesor])
        @include('profesores._form_usuario', ['profesor' => $profesor])
        @include('profesores._form_bloques', ['bloquesParaAsignar' => $bloquesParaAsignar, 'profesor' => $profesor])
        @include('profesores._form_sedes_roles', ['sedes' => $sedes ?? collect(), 'profesor' => $profesor])
        <x-ito.form-actions :cancel="route('profesores.show', $profesor)" submit="Guardar cambios" />
    </form>
</x-ito.shell-page>
@endsection

@push('scripts')
@include('profesores._form_usuario_script')
@endpush
