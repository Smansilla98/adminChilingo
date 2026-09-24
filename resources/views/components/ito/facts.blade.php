{{-- Tira de datos clave de una ficha. Usar con <x-ito.fact>. --}}
<dl {{ $attributes->merge(['class' => 'ito-facts']) }}>
    {{ $slot }}
</dl>
