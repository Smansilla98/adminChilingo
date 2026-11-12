<!DOCTYPE html>
<html lang="es">
<head>
    @include('layouts.partials.head-ito', ['headTitle' => 'Elegí una contraseña nueva · La Chilinga'])
</head>
<body class="auth-page-ito">
<a class="ito-skip" href="#contenido-principal">Ir al formulario</a>
<div class="auth-split">
    @include('auth.partials.aside')

    <main class="auth-main" id="contenido-principal" tabindex="-1">
        <div class="auth-card auth-card--ito">
            <div class="auth-card-ito__head">
                <h1 class="auth-card-ito__title">Elegí una contraseña nueva</h1>
                <p class="auth-card-ito__sub">Escribí el código que te mandamos. Vence a los 15 minutos. Después de cambiarla, se cierran las sesiones abiertas.</p>
            </div>
            <div class="auth-card-ito__body">
                @if(session('info'))
                    <div class="alert alert-info" role="status">{{ session('info') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                <form method="POST" action="{{ route('recuperar.confirmar.store') }}" novalidate>
                    @csrf
                    <div class="auth-field">
                        <label class="auth-field-label" for="usuario">Tu DNI, usuario o correo</label>
                        <input type="text" class="form-control" id="usuario" name="usuario" value="{{ old('usuario', $usuario) }}" required autocomplete="username" autocapitalize="none">
                    </div>
                    <div class="auth-field">
                        <label class="auth-field-label" for="codigo">Código de 6 números</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="{{ old('codigo') }}" required inputmode="numeric" maxlength="12" autocomplete="one-time-code">
                    </div>
                    <div class="auth-field">
                        <label class="auth-field-label" for="password">Contraseña nueva</label>
                        <input type="password" class="form-control" id="password" name="password" required minlength="8" autocomplete="new-password">
                        <div class="form-text">Al menos 8 letras o números. No uses tu DNI.</div>
                    </div>
                    <div class="auth-field">
                        <label class="auth-field-label" for="password_confirmation">Repetila</label>
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Guardar contraseña</button>
                </form>
                <p class="mt-3 mb-0"><a href="{{ route('recuperar.create') }}">No me llegó el código</a></p>
            </div>
        </div>
    </main>
</div>
</body>
</html>
