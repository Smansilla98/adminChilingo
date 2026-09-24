@extends('layouts.app')

@section('title', 'Inscribir a la gira')
@section('page-title', 'Inscribir alumno')

@section('content')
@include('villa-gesell.partials.nav')
<x-ito.shell-page
    :plain="true"
    title="Inscribir alumno"
    subtitle="Inscripción a la gira 2027."
>
        <form action="{{ route('villa-gesell.inscriptos.store') }}" method="POST">
            @csrf
            @include('villa-gesell.inscriptos._form')
            <x-ito.form-actions :cancel="route('villa-gesell.inscriptos.index')" submit="Inscribir" />
        </form>
</x-ito.shell-page>
@include('villa-gesell.inscriptos._modal_alumno')
@endsection

@push('scripts')
@include('villa-gesell.inscriptos._form_aporte_script')
@include('villa-gesell.inscriptos._form_alumno_rapido_script')
@endpush
