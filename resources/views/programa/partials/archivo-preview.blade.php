@php
    $nombre = $nombre ?? 'archivo';
    $params = ['programaRitmo' => $programaRitmo, 'tipo' => $tipo];
    if (isset($i)) {
        $params['i'] = $i;
    }
    $url = route('programa.toque.archivo', $params + ['inline' => 1]);
    $dl = route('programa.toque.archivo', $params);
    $ext = strtolower(pathinfo((string) ($path ?? $nombre), PATHINFO_EXTENSION));
    $esImg = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    $esVid = in_array($ext, ['mp4', 'webm', 'mov', 'm4v'], true);
@endphp
@if($esImg)
    <img src="{{ $url }}" alt="{{ $nombre }}" class="img-fluid rounded programa-recurso-img" loading="lazy">
@elseif($esVid)
    <video src="{{ $url }}" class="w-100 rounded" controls preload="metadata" playsinline></video>
@else
    <a href="{{ $dl }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
        <i class="bi bi-download"></i> {{ $nombre }}
    </a>
@endif
