{{-- Permisos efectivos agrupados por módulo: qué, dónde y por qué. --}}
<div class="row g-3">
    @foreach($permisos as $grupo => $lista)
        <div class="col-md-6 col-xl-4">
            <div class="border rounded p-2 h-100">
                <h3 class="h6 mb-2">{{ $grupo }}</h3>
                <ul class="list-unstyled small mb-0">
                    @foreach($lista as $p)
                        <li class="mb-1">
                            <span class="fw-semibold">{{ $p['etiqueta'] }}</span>
                            <span class="d-block text-muted">{{ $p['alcance'] }} · vía {{ implode(', ', $p['via']) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endforeach
</div>
