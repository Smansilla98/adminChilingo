@extends('layouts.app')

@section('title', 'Editar ítem')
@section('page-title', 'Inventario — Editar ítem')

@section('content')
<x-ito.shell-page
    title="Editar ítem"
    subtitle="{{ $inventario->nombre }}"
    eyebrow="Inventario"
>
    <x-slot:actions>
        <a href="{{ route('inventarios.show', $inventario) }}" class="btn btn-outline-secondary btn-sm">Ver ficha</a>
    </x-slot:actions>

    <form action="{{ route('inventarios.update', $inventario) }}" method="POST" class="ito-form">
        @csrf
        @method('PUT')
        <x-ito.form-steps
            :steps="['Identidad', 'Especificaciones', 'Adquisición']"
            submit-label="Guardar cambios"
        >
            <x-slot:cancel>
                <a href="{{ route('inventarios.show', $inventario) }}" class="btn btn-outline-secondary">Cancelar</a>
            </x-slot:cancel>
            @include('inventarios._form', ['item' => $inventario])
        </x-ito.form-steps>
    </form>
</x-ito.shell-page>
@endsection
