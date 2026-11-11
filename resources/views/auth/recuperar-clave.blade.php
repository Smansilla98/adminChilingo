<!DOCTYPE html>
<html lang="es">
<head>
    @include('layouts.partials.head-ito', ['headTitle' => 'Olvidé mi contraseña · La Chilinga'])
</head>
<body class="auth-page-ito">
<a class="ito-skip" href="#contenido-principal">Ir al formulario</a>
<div class="auth-split">
    @include('auth.partials.aside')

    <main class="auth-main" id="contenido-principal" tabindex="-1">
        <div class="auth-card auth-card--ito">
            <div class="auth-card-ito__head">
                <h1 class="auth-card-ito__title">Olvidé mi contraseña</h1>
                <p class="auth-card-ito__sub">Escribí tu DNI (o tu usuario o tu correo). Te mandamos un código por correo o por WhatsApp para elegir una contraseña nueva.</p>
            </div>
            <div class="auth-card-ito__body">
                @if($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                <form method="POST" action="{{ route('recuperar.store') }}" novalidate>
                    @csrf
                    <div class="auth-field">
                        <label class="auth-field-label" for="usuario">Tu DNI, usuario o correo</label>
                        <div class="auth-infield @error('usuario') is-invalid @enderror">
                            <input type="text" class="auth-infield__input" id="usuario" name="usuario" value="{{ old('usuario') }}" required autofocus autocomplete="username" autocapitalize="none">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Mandarme el código</button>
                </form>
                <p class="mt-3 mb-0"><a href="{{ route('login') }}">Volver a entrar</a></p>
            </div>
        </div>
    </main>
</div>
</body>
</html>
