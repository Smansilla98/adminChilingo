@php
    $collapseId = $collapseId ?? 'partituraLecturaCollapse';
    $expanded = $expanded ?? false;
    $bare = $bare ?? false;
    $leyendaImg = asset('images/programa/leyenda-lectura-partitura.png');
@endphp

<div class="programa-partitura-lectura {{ $bare ? '' : 'card border-secondary mb-3' }}">
    @if(!$bare)
    <div class="card-header py-2">
        <button
            class="btn btn-link btn-sm text-decoration-none text-start w-100 d-flex justify-content-between align-items-center gap-2 p-0 programa-partitura-lectura-toggle"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#{{ $collapseId }}"
            aria-expanded="{{ $expanded ? 'true' : 'false' }}"
            aria-controls="{{ $collapseId }}"
        >
            <span class="fw-semibold"><i class="bi bi-book text-warning"></i> ¿Cómo leer la partitura?</span>
            <i class="bi bi-chevron-down programa-partitura-lectura-chevron" aria-hidden="true"></i>
        </button>
    </div>
    @endif
    <div id="{{ $collapseId }}" class="{{ $bare ? '' : 'collapse' }} {{ (!$bare && $expanded) || $bare ? 'show' : '' }}">
        <div class="{{ $bare ? 'p-3' : 'card-body pt-2' }}">
            @if($bare)
            <p class="small text-muted mb-3">
                En las partituras de batería cada símbolo en el pentagrama indica un tambor o platillo.
            </p>
            @endif
            @if(!$bare)
            <p class="small text-muted mb-3">
                Mirá la leyenda y buscá el mismo dibujo en tu partitura.
            </p>
            @endif

            <figure class="programa-partitura-leyenda-fig mb-3 mb-md-4">
                <img
                    src="{{ $leyendaImg }}"
                    alt="Leyenda de lectura: redoblante, toms, hi-hat, platillos y bombos en el pentagrama"
                    class="img-fluid rounded border programa-partitura-leyenda-img"
                    width="900"
                    height="520"
                    loading="lazy"
                >
                <figcaption class="form-text mt-2 mb-0 text-center">
                    Referencia de lectura (Netodrumm3r). Podés ampliar la imagen con clic derecho → abrir en nueva pestaña.
                </figcaption>
            </figure>

            <div class="row g-2 g-md-3 small">
                <div class="col-md-4">
                    <div class="fw-semibold mb-1">Tambores</div>
                    <ul class="mb-0 ps-3 text-muted">
                        <li><strong>Redoblante</strong> — nota en el centro del pentagrama</li>
                        <li><strong>Tom chico / mediano / de pie</strong> — más arriba en el pentagrama</li>
                        <li><strong>Bombo</strong> — abajo (derecho e izquierdo si hay dos)</li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <div class="fw-semibold mb-1">Hi-hat y platillos</div>
                    <ul class="mb-0 ps-3 text-muted">
                        <li><strong>Hi-hat cerrado</strong> — cruz con <strong>+</strong></li>
                        <li><strong>Hi-hat abierto</strong> — cruz con <strong>o</strong></li>
                        <li><strong>Crash</strong> — cruz en círculo o con asterisco</li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <div class="fw-semibold mb-1">Tips para practicar</div>
                    <ul class="mb-0 ps-3 text-muted">
                        <li>Leé de izquierda a derecha, como un libro.</li>
                        <li>Primero mirá qué tambor toca cada línea.</li>
                        <li>Si hay duda, preguntale a tu profe en clase.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
