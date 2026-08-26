@extends('layouts.app')

@section('title', 'Nuevo ítem')
@section('page-title', 'Inventario — Nuevo ítem')

@section('content')
<x-ito.shell-page
    title="Nuevo ítem"
    subtitle="Registrá un tambor, parche u otro elemento en tres pasos."
    eyebrow="Inventario"
>
    <x-slot:actions>
        <a href="{{ route('inventarios.index') }}" class="btn btn-outline-secondary btn-sm">Volver al listado</a>
    </x-slot:actions>

    <form action="{{ route('inventarios.store') }}" method="POST" class="ito-form">
        @csrf
        <x-ito.form-steps
            :steps="['Identidad', 'Especificaciones', 'Adquisición']"
            submit-label="Guardar ítem"
        >
            <x-slot:cancel>
                <a href="{{ route('inventarios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </x-slot:cancel>
            @include('inventarios._form', ['item' => null, 'values' => $defaults])
        </x-ito.form-steps>
    </form>
</x-ito.shell-page>
@endsection
