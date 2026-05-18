@php
    use App\Support\ProgramaRitmoMedios;
    $embed = ProgramaRitmoMedios::urlEmbed($url ?? null);
@endphp
@if($embed)
<div class="programa-video-embed">
    <iframe src="{{ $embed }}" title="Video" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
</div>
@elseif(!empty($url))
<p class="mb-0"><a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary"><i class="bi bi-play-btn"></i> Ver video</a></p>
@endif
