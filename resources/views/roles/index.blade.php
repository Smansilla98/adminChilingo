@extends('layouts.app')

@section('title', 'Perfiles y roles')
@section('page-title', 'Perfiles y roles')

@section('content')
<x-ito.shell-page
    title="Qué gestiona cada perfil"
    eyebrow="Roles"
    subtitle="{{ $guia['intro'] }}"
    plain
>
    <div class="mb-3">
        <label for="rolesBuscar" class="form-label">Buscar un perfil o algo que gestione</label>
        <input type="search" id="rolesBuscar" class="form-control" placeholder="Contador, cuotas, inventario…">
    </div>

    @foreach($guia['casos'] as $caso)
        <section class="mb-4" data-rol-caso>
            <h2 class="h5 mb-3">{{ $caso['titulo'] }}</h2>
            <div class="row g-3">
                @foreach($caso['roles'] as $rol)
                    <div class="col-lg-6" data-rol-item>
                        <article class="ito-card h-100">
                            <div class="card-header d-flex flex-wrap justify-content-between gap-2 align-items-center">
                                <span>{{ $rol['nombre'] }}</span>
                                <span class="badge text-bg-secondary fw-normal">{{ $rol['derivado'] ? 'Aparece solo' : 'Se asigna' }}</span>
                            </div>
                            <div class="card-body">
                                <p class="mb-2">{{ $rol['descripcion'] }}</p>
                                <p class="small text-muted mb-3">Alcance: {{ implode(' · ', $rol['ambitos']) }}.</p>

                                @if($rol['todo'])
                                    <p class="mb-2"><strong>Gestiona todo el sistema.</strong></p>
                                @elseif($rol['grupos'] === [])
                                    <p class="mb-0 text-muted">No abre módulos. Sirve como marca del perfil.</p>
                                @else
                                    @foreach($rol['grupos'] as $grupo => $acciones)
                                        <p class="fw-semibold mb-1">{{ $grupo }}</p>
                                        <ul class="mb-3">
                                            @foreach($acciones as $accion)
                                                <li>{{ $accion }}</li>
                                            @endforeach
                                        </ul>
                                    @endforeach
                                @endif

                                @if($rol['no_puede'] !== [])
                                    <p class="mb-1"><strong>No puede</strong></p>
                                    <ul class="mb-0">
                                        @foreach($rol['no_puede'] as $accion)
                                            <li>{{ $accion }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</x-ito.shell-page>
@endsection

@push('scripts')
<script>
(function () {
    const input = document.getElementById('rolesBuscar');
    const casos = Array.from(document.querySelectorAll('[data-rol-caso]'));
    if (!input || casos.length === 0) return;

    function norm(s) {
        return (s || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
    }

    input.addEventListener('input', function () {
        const q = norm(input.value);
        casos.forEach(function (caso) {
            let visibles = 0;
            caso.querySelectorAll('[data-rol-item]').forEach(function (item) {
                const ok = q === '' || norm(item.textContent).includes(q);
                item.classList.toggle('d-none', !ok);
                if (ok) visibles += 1;
            });
            caso.classList.toggle('d-none', visibles === 0);
        });
    });
})();
</script>
@endpush
