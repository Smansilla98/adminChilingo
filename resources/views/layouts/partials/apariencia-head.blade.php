{{-- Preferencias de apariencia del usuario: tema (claro/oscuro/sistema), acento y fuentes. --}}
@php
    $aparienciaTema = \App\Support\AparienciaTema::DEFAULTS;
    if (auth()->check() && \Illuminate\Support\Facades\Schema::hasColumn('users', 'apariencia_json') && is_array(auth()->user()->apariencia_json ?? null)) {
        $aparienciaTema = \App\Support\AparienciaTema::normalizar(auth()->user()->apariencia_json);
    }
    $temaForzado = $temaForzado ?? null;
@endphp
<script>
(function () {
    var pref = @json($temaForzado ?: $aparienciaTema['tema']);
    var oscuro = pref === 'oscuro' || (pref === 'sistema' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
    var html = document.documentElement;
    html.setAttribute('data-bs-theme', oscuro ? 'dark' : 'light');
    html.setAttribute('data-tema', pref);
    try {
        if (localStorage.getItem('ito-sb-collapsed') === '1') html.classList.add('sb-collapsed');
        if (localStorage.getItem('ito-a11y-lg') === '1') html.classList.add('ito-text-lg');
        if (localStorage.getItem('ito-a11y-hc') === '1') html.classList.add('ito-contrast');
    } catch (e) { /* modo privado */ }
})();
</script>
@if(\App\Support\AparienciaTema::estiloPersonalizado($aparienciaTema))
<link rel="stylesheet" href="{{ \App\Support\AparienciaTema::googleFontsUrl($aparienciaTema) }}">
<style id="ito-apariencia-user">
{!! \App\Support\AparienciaTema::cssVariables($aparienciaTema) !!}
</style>
@endif
