{{-- Vista previa al compartir el enlace (WhatsApp, Instagram, Facebook, mensajes). Las páginas que
     traen sus propias metaetiquetas (biblioteca) las definen con @section('og-propio'). --}}
<meta name="description" content="{{ $__env->yieldContent('og-descripcion', 'Programa, partituras, biblioteca y agenda de La Chilinga.') }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="La Chilinga">
<meta property="og:locale" content="es_AR">
<meta property="og:title" content="{{ trim($__env->yieldContent('title', 'La Chilinga')) }}">
<meta property="og:description" content="{{ $__env->yieldContent('og-descripcion', 'Programa, partituras, biblioteca y agenda de La Chilinga.') }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ asset('images/brand/og-chilinga.png') }}">
<meta property="og:image:secure_url" content="{{ asset('images/brand/og-chilinga.png') }}">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="La Chilinga">
<meta name="twitter:card" content="summary_large_image">
