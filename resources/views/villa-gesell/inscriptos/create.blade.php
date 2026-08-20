@extends('layouts.app')

@section('title', 'Inscribir a la gira')
@section('page-title', 'Inscribir alumno')

@section('content')
@include('villa-gesell.partials.nav')
<div class="card">
    <div class="card-header">Inscribir alumno · Villa Gesell 2027</div>
    <div class="card-body">
        <form action="{{ route('villa-gesell.inscriptos.store') }}" method="POST">
            @csrf
            @include('villa-gesell.inscriptos._form')
            <div class="mt-3">
                <button class="btn btn-primary" type="submit">Inscribir</button>
                <a href="{{ route('villa-gesell.inscriptos.index') }}" class="btn btn-link">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@include('villa-gesell.inscriptos._modal_alumno')
@endsection

@push('scripts')
@include('villa-gesell.inscriptos._form_aporte_script')
@include('villa-gesell.inscriptos._form_alumno_rapido_script')
@endpush
