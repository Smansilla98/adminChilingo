@extends('layouts.app')

@section('title', 'Inscribir a la gira')
@section('page-title', 'Inscribir alumno')

@section('content')
@include('villa-gesell.partials.nav')
<x-ito.shell-page
    title="Inscribir alumno"
    eyebrow="Villa Gesell"
    subtitle="Inscripción a la gira 2027."
>
        <form action="{{ route('villa-gesell.inscriptos.store') }}" method="POST">
            @csrf
            @include('villa-gesell.inscriptos._form')
            <div class="mt-3">
                <button class="btn btn-primary" type="submit">Inscribir</button>
                <a href="{{ route('villa-gesell.inscriptos.index') }}" class="btn btn-link">Cancelar</a>
            </div>
        </form>
</x-ito.shell-page>
@include('villa-gesell.inscriptos._modal_alumno')
@endsection

@push('scripts')
@include('villa-gesell.inscriptos._form_aporte_script')
@include('villa-gesell.inscriptos._form_alumno_rapido_script')
@endpush
