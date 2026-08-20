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
    const selProfe = document.getElementById('vg_nuevo_profesor');
    const selBloque = document.getElementById('vg_nuevo_bloque');
    const selSede = document.getElementById('vg_nuevo_sede');
    const boxProfeNuevo = document.getElementById('vg_box_profe_nuevo');
    const boxBloqueNuevo = document.getElementById('vg_box_bloque_nuevo');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const urlAlumno = @json(route('villa-gesell.alumnos-rapidos.store'));
    const urlProfe = @json(route('villa-gesell.profesores-rapidos.store'));
    const urlBloque = @json(route('villa-gesell.bloques-rapidos.store'));

    async function postJson(url, payload) {
        const res = await fetch(url, {
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
            let msg = data.message || 'No se pudo guardar.';
            if (data.errors) msg = Object.values(data.errors).flat().join(' ');
            throw new Error(msg);
        }
        return data;
    }

    function showError(msg) {
        if (!errBox) return;
        errBox.hidden = false;
        errBox.textContent = msg || 'Error.';
    }

    function clearError() {
        if (!errBox) return;
        errBox.hidden = true;
        errBox.textContent = '';
    }

    function syncModo() {
        const esNuevo = modoNuevo && modoNuevo.checked;
        if (boxExistente) boxExistente.hidden = !!esNuevo;
        if (boxNuevo) boxNuevo.hidden = !esNuevo;
        if (selectAlumno) {
            selectAlumno.required = !esNuevo;
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

    function filtrarBloquesPorProfe() {
        if (!selBloque) return;
        const pid = selProfe?.value || '';
        Array.from(selBloque.options).forEach(function (opt, idx) {
            if (idx === 0) {
                opt.hidden = false;
                return;
            }
            const match = !pid || String(opt.dataset.profesor || '') === String(pid);
            opt.hidden = !match;
            if (!match && opt.selected) selBloque.value = '';
        });
    }

    function sincronizarDesdeBloque() {
        if (!selBloque) return;
        const opt = selBloque.selectedOptions[0];
        if (!opt || !opt.value) return;
        if (selSede && opt.dataset.sede && !selSede.value) {
            selSede.value = opt.dataset.sede;
        }
        if (selProfe && opt.dataset.profesor && !selProfe.value) {
            selProfe.value = opt.dataset.profesor;
            filtrarBloquesPorProfe();
            selBloque.value = opt.value;
        }
    }

    function appendOption(select, id, label, dataset) {
        if (!select) return;
        let opt = Array.from(select.options).find(o => o.value === String(id));
        if (!opt) {
            opt = document.createElement('option');
            opt.value = String(id);
            select.appendChild(opt);
        }
        opt.textContent = label;
        if (dataset) {
            Object.keys(dataset).forEach(function (k) {
                if (dataset[k] != null && dataset[k] !== '') {
                    opt.dataset[k] = String(dataset[k]);
                }
            });
        }
        select.value = String(id);
    }

    document.getElementById('vg_toggle_profe_nuevo')?.addEventListener('click', function () {
        if (!boxProfeNuevo) return;
        boxProfeNuevo.hidden = !boxProfeNuevo.hidden;
        if (!boxProfeNuevo.hidden) document.getElementById('vg_profe_nombre_rapido')?.focus();
    });
    document.getElementById('vg_toggle_bloque_nuevo')?.addEventListener('click', function () {
        if (!boxBloqueNuevo) return;
        boxBloqueNuevo.hidden = !boxBloqueNuevo.hidden;
        if (!boxBloqueNuevo.hidden) document.getElementById('vg_bloque_nombre_rapido')?.focus();
    });

    document.getElementById('vg_btn_profe_rapido')?.addEventListener('click', async function () {
        clearError();
        const nombre = (document.getElementById('vg_profe_nombre_rapido')?.value || '').trim();
        if (!nombre) {
            showError('Escribí el nombre del profesor.');
            return;
        }
        const btn = this;
        btn.disabled = true;
        try {
            const data = await postJson(urlProfe, { nombre: nombre });
            appendOption(selProfe, data.profesor.id, data.profesor.nombre);
            filtrarBloquesPorProfe();
            if (boxProfeNuevo) boxProfeNuevo.hidden = true;
            const input = document.getElementById('vg_profe_nombre_rapido');
            if (input) input.value = '';
        } catch (err) {
            showError(err.message);
        } finally {
            btn.disabled = false;
        }
    });

    document.getElementById('vg_btn_bloque_rapido')?.addEventListener('click', async function () {
        clearError();
        const nombre = (document.getElementById('vg_bloque_nombre_rapido')?.value || '').trim();
        if (!nombre) {
            showError('Escribí el nombre del bloque.');
            return;
        }
        const btn = this;
        btn.disabled = true;
        try {
            const data = await postJson(urlBloque, {
                nombre: nombre,
                sede_id: selSede?.value || '',
                profesor_id: selProfe?.value || '',
            });
            appendOption(selBloque, data.bloque.id, data.bloque.label || data.bloque.nombre, {
                sede: data.bloque.sede_id,
                profesor: data.bloque.profesor_id,
            });
            if (data.bloque.sede_id && selSede && !selSede.value) {
                selSede.value = String(data.bloque.sede_id);
            }
            if (boxBloqueNuevo) boxBloqueNuevo.hidden = true;
            const input = document.getElementById('vg_bloque_nombre_rapido');
            if (input) input.value = '';
        } catch (err) {
            showError(err.message);
        } finally {
            btn.disabled = false;
        }
    });

    if (modoExistente) modoExistente.addEventListener('change', syncModo);
    if (modoNuevo) modoNuevo.addEventListener('change', syncModo);
    if (selProfe) selProfe.addEventListener('change', filtrarBloquesPorProfe);
    if (selBloque) selBloque.addEventListener('change', sincronizarDesdeBloque);

    if (formNuevo) {
        formNuevo.addEventListener('submit', async function (e) {
            e.preventDefault();
            clearError();
            if (btnCrear) {
                btnCrear.disabled = true;
                btnCrear.textContent = 'Creando…';
            }

            const payload = {
                nombre_apellido: document.getElementById('vg_nuevo_nombre')?.value || '',
                dni: document.getElementById('vg_nuevo_dni')?.value || '',
                telefono: document.getElementById('vg_nuevo_tel')?.value || '',
                fecha_nacimiento: document.getElementById('vg_nuevo_fn')?.value || '',
                sede_id: selSede?.value || '',
                bloque_id: selBloque?.value || '',
                profesor_id: selProfe?.value || '',
                instrumento_principal: document.getElementById('vg_nuevo_instrumento')?.value || 'Otro',
            };

            try {
                const data = await postJson(urlAlumno, payload);
                const a = data.alumno;
                if (selectAlumno && a) {
                    appendOption(
                        selectAlumno,
                        a.id,
                        a.nombre_apellido + (a.dni ? ' · DNI ' + a.dni : '')
                    );
                }

                if (modoExistente) modoExistente.checked = true;
                syncModo();
                if (statusEl) {
                    statusEl.hidden = false;
                    let extra = '';
                    if (a?.bloque) extra += ' · ' + a.bloque;
                    if (a?.profesor) extra += ' · ' + a.profesor;
                    statusEl.textContent = (data.message || 'Alumno seleccionado.') + extra;
                }

                const modalEl = document.getElementById('vgModalAlumnoNuevo');
                if (modalEl && typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                }
                formNuevo.reset();
                filtrarBloquesPorProfe();
            } catch (err) {
                showError(err.message);
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
