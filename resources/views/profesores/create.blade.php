@extends('layouts.app')

@section('title', 'Nuevo profesor')
@section('page-title', 'Nuevo profesor')

@section('content')
<x-ito.shell-page title="Nuevo profesor" subtitle="Datos, cuenta de acceso, bloques y sedes." :plain="true">
    <form action="{{ route('profesores.store') }}" method="POST">
        @csrf
        @isset($persona)
            @if($persona)
                <input type="hidden" name="persona_id" value="{{ $persona->id }}">
                <div class="alert alert-info mb-0">Se va a crear para <strong>{{ $persona->nombre_completo }}</strong> (ficha existente), sin duplicar la persona.@if($persona->user ?? null) Va a usar su cuenta <span class="ito-mono">{{ $persona->user->username }}</span>.@endif</div>
            @endif
        @endisset
        @include('profesores._form_datos', ['persona' => $persona ?? null])
        @include('profesores._form_usuario')
        @include('profesores._form_bloques', ['bloquesParaAsignar' => $bloquesParaAsignar])
        @include('profesores._form_sedes_roles', ['sedes' => $sedes ?? collect()])
        <x-ito.form-actions :cancel="route('profesores.index')" submit="Crear profesor" />
    </form>
</x-ito.shell-page>
@endsection

@push('scripts')
@include('profesores._form_usuario_script')
@endpush
