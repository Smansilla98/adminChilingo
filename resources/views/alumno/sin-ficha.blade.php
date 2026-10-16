@extends('layouts.app')

@section('title', 'Pagar mi cuota')
@section('page-title', 'Pagar mi cuota')

@section('content')
<div class="ito-page" style="max-width: 760px">
    <div class="ito-page-head">
        <div>
            <h1 class="ito-page-title">Tu usuario no tiene una ficha de alumno vinculada</h1>
            <p class="ito-page-sub">Para pagar cuotas desde acá, tu cuenta tiene que estar unida a tu inscripción como alumno. Hoy no encontramos ninguna inscripción a nombre de {{ $user->persona?->nombre_completo ?: $user->name }}.</p>
        </div>
    </div>

    @if($puedeUnir)
        <section class="ito-card p-3" aria-labelledby="sf-candidatas">
            <h2 class="h6" id="sf-candidatas">¿Es alguna de estas?</h2>
            @forelse($candidatas as $a)
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-top py-2">
                    <div>
                        <strong>{{ $a->nombre_apellido }}</strong>@if($a->dni) <span class="text-muted">· DNI {{ $a->dni }}</span>@endif
                        <div class="small text-muted">{{ $a->bloques->map(fn ($b) => $b->nombre.' ('.($b->sede?->nombre ?? 's/sede').')')->join(', ') ?: 'Sin bloque' }}</div>
                    </div>
                    <div class="d-flex gap-2">
                        @if($a->persona_id)
                            <a href="{{ route('personas.show', $a->persona_id) }}" class="btn btn-sm btn-outline-secondary">Ver esa persona</a>
                        @endif
                        <a href="{{ route('alumnos.show', $a) }}" class="btn btn-sm btn-outline-secondary">Ver ficha de alumno</a>
                    </div>
                </div>
            @empty
                <p class="text-muted mb-0">No encontramos ninguna ficha parecida (por nombre o DNI). Inscribite como alumno desde tu ficha de persona.</p>
            @endforelse
            <p class="small text-muted mt-3 mb-0">
                Si la inscripción quedó cargada como otra persona, abrí
                @if($user->persona_id)<a href="{{ route('personas.show', $user->persona_id) }}">tu ficha de persona</a>@else tu ficha de persona @endif
                y usá <strong>Fusionar</strong> eligiendo la persona de la inscripción: queda todo unido a tu cuenta.
            </p>
        </section>
    @else
        <div class="alert alert-info" role="status">Pedile a administración que vincule tu inscripción a tu cuenta. Mientras tanto podés pagar como siempre y mandar el comprobante por el <a href="{{ route('comprobante-cuota-public.create') }}">formulario de comprobantes</a>.</div>
    @endif
</div>
@endsection
