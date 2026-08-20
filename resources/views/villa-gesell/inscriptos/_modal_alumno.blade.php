<div class="modal fade" id="vgModalAlumnoNuevo" tabindex="-1" aria-labelledby="vgModalAlumnoNuevoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="vgModalAlumnoNuevoLabel">Alta rápida de alumno</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="vgFormAlumnoNuevo">
                <div class="modal-body">
                    <p class="text-muted small">Solo lo necesario para la gira. Después se puede completar en Alumnos.</p>
                    <div id="vgAlumnoErrores" class="alert alert-danger py-2 small" hidden></div>
                    <div class="mb-3">
                        <label class="form-label" for="vg_nuevo_nombre">Nombre y apellido *</label>
                        <input type="text" class="form-control" id="vg_nuevo_nombre" name="nombre_apellido" required autocomplete="name">
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label" for="vg_nuevo_dni">DNI</label>
                            <input type="text" class="form-control" id="vg_nuevo_dni" name="dni" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="vg_nuevo_tel">Teléfono</label>
                            <input type="text" class="form-control" id="vg_nuevo_tel" name="telefono" autocomplete="tel">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="vg_nuevo_fn">Fecha de nacimiento</label>
                            <input type="date" class="form-control" id="vg_nuevo_fn" name="fecha_nacimiento">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="vg_nuevo_sede">Sede</label>
                            <select class="form-select" id="vg_nuevo_sede" name="sede_id">
                                <option value="">— Opcional —</option>
                                @foreach(($sedes ?? collect()) as $sede)
                                    <option value="{{ $sede->id }}">{{ $sede->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="vg_nuevo_instrumento">Instrumento principal</label>
                            <select class="form-select" id="vg_nuevo_instrumento" name="instrumento_principal">
                                <option value="Otro">Otro / a definir</option>
                                @foreach(\App\Models\VillaGesellInscripto::TAMBORES as $t)
                                    <option value="{{ $t }}">{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="vgBtnCrearAlumno">
                        Crear y seleccionar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
