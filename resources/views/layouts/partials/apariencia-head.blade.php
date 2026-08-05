{{-- Inyecta preferencia de apariencia del usuario autenticado (server-side). --}}
@auth
@php
    $aparienciaTema = \App\Support\AparienciaTema::DEFAULTS;
    if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'apariencia_json') && is_array(auth()->user()->apariencia_json ?? null)) {
        $aparienciaTema = \App\Support\AparienciaTema::normalizar(auth()->user()->apariencia_json);
    }
    $aparienciaEsCustom = ! \App\Support\AparienciaTema::esDefault($aparienciaTema);
@endphp
@if($aparienciaEsCustom)
<link rel="stylesheet" href="{{ \App\Support\AparienciaTema::googleFontsUrl($aparienciaTema) }}">
<style id="ito-apariencia-user">
{!! \App\Support\AparienciaTema::cssVariables($aparienciaTema) !!}
</style>
@endif
@endauth
