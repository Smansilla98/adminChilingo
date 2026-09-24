@php
    /** @var \App\Models\Profesor|null $profesor */
    $profesor = $profesor ?? null;
    $persona = $persona ?? null;
@endphp
<x-ito.form-section title="Datos de contacto" icon="bi-person">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="prof-nombre">Nombre y apellido</label>
            <input type="text" id="prof-nombre" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre', $profesor?->nombre ?? $persona?->nombre_completo) }}" required autocomplete="name">
            @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="prof-telefono">Teléfono</label>
            <input type="tel" id="prof-telefono" name="telefono" class="form-control @error('telefono') is-invalid @enderror" value="{{ old('telefono', $profesor?->telefono ?? $persona?->telefono) }}" autocomplete="tel">
            @error('telefono')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="prof-email">Correo electrónico</label>
            <input type="email" id="prof-email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $profesor?->email ?? $persona?->email) }}" autocomplete="email">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 d-flex align-items-center pt-md-4">
            <div class="form-check form-switch">
                <input type="checkbox" role="switch" name="activo" class="form-check-input" id="activo" value="1" @checked(old('activo', $profesor?->activo ?? true))>
                <label class="form-check-label" for="activo">Profesor activo</label>
            </div>
        </div>
    </div>
</x-ito.form-section>
