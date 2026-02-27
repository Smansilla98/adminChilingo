@extends('layouts.app')

@section('title', 'Programa de la escuela')
@section('page-title', 'Programa de ritmos')

@section('content')
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-music-note-list"></i> Programa oficial — Toques por año</h5>
        <p class="text-muted small mb-0 mt-1">La Chilinga — Escuela Popular del tambor. Ritmos prácticos y teóricos.</p>
    </div>
    <div class="card-body">
        @forelse([1,2,3,4,5,6] as $año)
            @php $ritmos = $porAño->get($año, collect()); @endphp
            @if($ritmos->isNotEmpty())
            <div class="mb-4">
                <h6 class="border-bottom pb-2 mb-3">{{ $años[$año] ?? $año . '° Año' }}</h6>
                <ol class="list-group list-group-numbered">
                    @foreach($ritmos as $r)
                    <li class="list-group-item d-flex justify-content-between align-items-start">
                        <div>
                            <strong>{{ $r->nombre }}</strong>
                            @if($r->opcional)
                                <span class="badge bg-secondary ms-1">Opcional</span>
                            @endif
                            @if($r->autor)
                                <div class="text-muted small">{{ $r->autor }}</div>
                            @endif
                            @if($r->notas)
                                <div class="text-muted small">{{ $r->notas }}</div>
                            @endif
                        </div>
                    </li>
                    @endforeach
                </ol>
            </div>
            @endif
        @endforelse

        @if($porAño->isEmpty())
        <p class="text-muted">No hay ritmos cargados. Ejecutá: <code>php artisan db:seed --class=ProgramaRitmosSeeder</code></p>
        @endif
    </div>
</div>
@endsection
