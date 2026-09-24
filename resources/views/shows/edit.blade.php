@extends('layouts.app')

@section('title', 'Editar show')
@section('page-title', 'Editar show')

@section('content')
<x-ito.shell-page :title="'Editar '.$show->titulo" :subtitle="$show->fecha?->format('d/m/Y')" :plain="true">
    <form action="{{ route('shows.update', $show) }}" method="POST">
        @csrf
        @method('PUT')
        @include('shows._form', ['show' => $show])
        <x-ito.form-actions :cancel="route('shows.show', $show)" submit="Guardar cambios" />
    </form>
</x-ito.shell-page>
@endsection
