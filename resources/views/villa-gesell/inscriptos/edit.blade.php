@extends('layouts.app')

@section('title', 'Editar inscripción')
@section('page-title', 'Editar inscripción')

@section('content')
@include('villa-gesell.partials.nav')
<x-ito.shell-page
    :plain="true"
    title="Editar inscripción"
    subtitle="{{ $inscripto->alumno?->nombre_apellido }}"
>
        <p class="alert alert-info mb-0">Se puede cambiar plaza, pagos, días, talle y tambores aunque el cupo ya esté definido o la plaza ocupada (si movés a otra plaza libre).</p>
        <form action="{{ route('villa-gesell.inscriptos.update', $inscripto) }}" method="POST">
            @csrf
            @method('PUT')
            @include('villa-gesell.inscriptos._form')
            <x-ito.form-actions :cancel="route('villa-gesell.inscriptos.index')" submit="Guardar cambios" />
        </form>
</x-ito.shell-page>
@include('villa-gesell.inscriptos._modal_alumno')
@endsection

@push('scripts')
@include('villa-gesell.inscriptos._form_aporte_script')
@include('villa-gesell.inscriptos._form_alumno_rapido_script')
@endpush
