@extends('layouts.partitura-editor')

@section('title', 'Editor · '.$programaRitmo->nombre)

@section('content')
    <div
        data-partitura-editor
        data-score="{{ json_encode($score, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
        data-save-url="{{ route('programa.toque.editor.guardar', $programaRitmo) }}"
        data-back-url="{{ route('programa.toque.show', $programaRitmo) }}"
        data-parte-url="{{ route('programa.toque.parte', ['programaRitmo' => $programaRitmo, 'instrumento' => '__INST__']) }}"
        data-readonly="0"
    ></div>
@endsection
