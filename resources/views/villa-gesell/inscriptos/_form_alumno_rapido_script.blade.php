<script>
(function () {
    const modoExistente = document.getElementById('vg_modo_existente');
    const modoNuevo = document.getElementById('vg_modo_nuevo');
    const boxExistente = document.getElementById('vg_box_existente');
    const boxNuevo = document.getElementById('vg_box_nuevo');
    const selectAlumno = document.getElementById('vg_alumno_id');
    const formNuevo = document.getElementById('vgFormAlumnoNuevo');
    const errBox = document.getElementById('vgAlumnoErrores');
    const statusEl = document.getElementById('vg_nuevo_status');
    const btnCrear = document.getElementById('vgBtnCrearAlumno');
    const urlRapido = @json(route('villa-gesell.alumnos-rapidos.store'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function syncModo() {
        const esNuevo = modoNuevo && modoNuevo.checked;
        if (boxExistente) boxExistente.hidden = !!esNuevo;
        if (boxNuevo) boxNuevo.hidden = !esNuevo;
        if (selectAlumno) {
            selectAlumno.required = !esNuevo;
            // Evita que un select oculto bloquee el submit HTML5.
            if (esNuevo && !selectAlumno.value) {
                selectAlumno.setCustomValidity('Creá el alumno nuevo o volvé a “Del padrón”.');
            } else {
                selectAlumno.setCustomValidity('');
            }
        }
        if (esNuevo && typeof bootstrap !== 'undefined') {
            const modalEl = document.getElementById('vgModalAlumnoNuevo');
            if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }

    if (modoExistente) modoExistente.addEventListener('change', syncModo);
    if (modoNuevo) modoNuevo.addEventListener('change', syncModo);

    if (formNuevo) {
        formNuevo.addEventListener('submit', async function (e) {
            e.preventDefault();
            if (errBox) {
                errBox.hidden = true;
                errBox.textContent = '';
            }
            if (btnCrear) {
                btnCrear.disabled = true;
                btnCrear.textContent = 'Creando…';
            }

            const payload = {
                nombre_apellido: document.getElementById('vg_nuevo_nombre')?.value || '',
                dni: document.getElementById('vg_nuevo_dni')?.value || '',
                telefono: document.getElementById('vg_nuevo_tel')?.value || '',
                fecha_nacimiento: document.getElementById('vg_nuevo_fn')?.value || '',
                sede_id: document.getElementById('vg_nuevo_sede')?.value || '',
                instrumento_principal: document.getElementById('vg_nuevo_instrumento')?.value || 'Otro',
            };

            try {
                const res = await fetch(urlRapido, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(payload),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    let msg = data.message || 'No se pudo crear el alumno.';
                    if (data.errors) {
                        msg = Object.values(data.errors).flat().join(' ');
                    }
                    throw new Error(msg);
                }

                const a = data.alumno;
                if (selectAlumno && a) {
                    let opt = Array.from(selectAlumno.options).find(o => o.value === String(a.id));
                    if (!opt) {
                        opt = document.createElement('option');
                        opt.value = a.id;
                        opt.textContent = a.nombre_apellido + (a.dni ? ' · DNI ' + a.dni : '');
                        selectAlumno.appendChild(opt);
                    }
                    selectAlumno.value = String(a.id);
                }

                if (modoExistente) modoExistente.checked = true;
                syncModo();
                if (statusEl) {
                    statusEl.hidden = false;
                    statusEl.textContent = data.message || 'Alumno seleccionado.';
                }

                const modalEl = document.getElementById('vgModalAlumnoNuevo');
                if (modalEl && typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                }
                formNuevo.reset();
            } catch (err) {
                if (errBox) {
                    errBox.hidden = false;
                    errBox.textContent = err.message || 'Error al crear.';
                }
            } finally {
                if (btnCrear) {
                    btnCrear.disabled = false;
                    btnCrear.textContent = 'Crear y seleccionar';
                }
            }
        });
    }
})();
</script>
