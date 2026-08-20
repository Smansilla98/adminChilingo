<div class="modal fade ito-modal" id="vgModalAlumnoNuevo" tabindex="-1" aria-labelledby="vgModalAlumnoNuevoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <p class="ito-modal-eyebrow mb-1">Villa Gesell · padrón</p>
                    <h2 class="modal-title h5 mb-0" id="vgModalAlumnoNuevoLabel">Alta rápida de alumno</h2>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="vgFormAlumnoNuevo">
                <div class="modal-body">
                    <p class="ito-modal-lead">Solo lo necesario para la gira. Profesor y bloque se pueden crear acá con el nombre; el detalle se completa después.</p>
                    <div id="vgAlumnoErrores" class="alert alert-danger py-2 small" hidden></div>
                    <div class="mb-3">
                        <label class="form-label" for="vg_nuevo_nombre">Nombre y apellido *</label>
                        <input type="text" class="form-control" id="vg_nuevo_nombre" name="nombre_apellido" required autocomplete="name" placeholder="Ej. Ana Pérez">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="vg_nuevo_dni">DNI</label>
                            <input type="text" class="form-control" id="vg_nuevo_dni" name="dni" autocomplete="off" placeholder="Opcional">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="vg_nuevo_tel">Teléfono</label>
                            <input type="text" class="form-control" id="vg_nuevo_tel" name="telefono" autocomplete="tel" placeholder="Opcional">
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

                        <div class="col-md-6">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                <label class="form-label mb-0" for="vg_nuevo_profesor">Profesor</label>
                                <button type="button" class="btn btn-link btn-sm p-0" id="vg_toggle_profe_nuevo">+ Crear profesor</button>
                            </div>
                            <select class="form-select" id="vg_nuevo_profesor" name="profesor_id">
                                <option value="">— Opcional —</option>
                                @foreach(($profesores ?? collect()) as $profe)
                                    <option value="{{ $profe->id }}">{{ $profe->nombre }}</option>
                                @endforeach
                            </select>
                            <div class="ito-inline-create mt-2" id="vg_box_profe_nuevo" hidden>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" id="vg_profe_nombre_rapido" placeholder="Nombre del profesor" autocomplete="off">
                                    <button type="button" class="btn btn-primary" id="vg_btn_profe_rapido">Crear</button>
                                </div>
                                <div class="form-text">Solo el nombre. Después se detalla en Profesores.</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                <label class="form-label mb-0" for="vg_nuevo_bloque">Bloque al que pertenece</label>
                                <button type="button" class="btn btn-link btn-sm p-0" id="vg_toggle_bloque_nuevo">+ Crear bloque</button>
                            </div>
                            <select class="form-select" id="vg_nuevo_bloque" name="bloque_id">
                                <option value="">— Opcional —</option>
                                @foreach(($bloques ?? collect()) as $bloque)
                                    <option
                                        value="{{ $bloque->id }}"
                                        data-sede="{{ $bloque->sede_id }}"
                                        data-profesor="{{ $bloque->profesor_id }}"
                                    >
                                        {{ $bloque->nombre }}@if($bloque->sede) · {{ $bloque->sede->nombre }}@endif@if($bloque->profesor) · {{ $bloque->profesor->nombre }}@endif
                                    </option>
                                @endforeach
                            </select>
                            <div class="ito-inline-create mt-2" id="vg_box_bloque_nuevo" hidden>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" id="vg_bloque_nombre_rapido" placeholder="Nombre del bloque" autocomplete="off">
                                    <button type="button" class="btn btn-primary" id="vg_btn_bloque_rapido">Crear</button>
                                </div>
                                <div class="form-text">Solo el nombre. Cupos y detalles se cargan después en Bloques.</div>
                            </div>
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
