import { useQuery } from '@tanstack/react-query';
import { router, Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';

import { confirmar, Hoja } from '@/components/feedback';
import { Campo, CampoFecha, CampoMonto, ErrorFormulario, Interruptor, requeridos, Seccion, Selector, SelectorLista, useFormulario } from '@/components/form';
import { Boton, Cargando, ErrorVista, Pantalla, Tenue } from '@/components/ui';
import type { CatalogoGira, Inscripto } from '@/features/villa-gesell/tipos';
import { api, qs } from '@/lib/api';
import { useCatalogo, useOperacion } from '@/lib/recursos';
import { moneda } from '@/lib/theme';

/** Inscribir a la gira o editar una inscripción (?id=). */
export default function Inscripcion() {
  const { id } = useLocalSearchParams<{ id?: string }>();
  const cat = useCatalogo<CatalogoGira>('villa-gesell/catalogo', 1);
  const existente = useQuery({ queryKey: ['villa-gesell', 'inscripto', id], queryFn: () => api<{ data: Inscripto }>(`villa-gesell/inscriptos/${id}`).then((r) => r.data), enabled: !!id });
  const sugerida = useQuery({ queryKey: ['villa-gesell', 'nueva'], queryFn: () => api<{ data: { plaza: number | null; fecha_desde: string | null; fecha_hasta: string | null; monto_esperado: number; valor_por_dia: number } }>('villa-gesell/inscriptos/nueva').then((r) => r.data), enabled: !id });

  if (cat.isPending || (id ? existente.isPending : sugerida.isPending)) return <Cargando />;
  const error = cat.error ?? existente.error ?? sugerida.error;
  if (error) return <ErrorVista error={error} onReintentar={() => { void cat.refetch(); void existente.refetch(); void sugerida.refetch(); }} />;
  return <Formulario catalogo={cat.data!} inscripto={existente.data} sugerida={sugerida.data} />;
}

function Formulario({ catalogo: c, inscripto: i, sugerida }: { catalogo: CatalogoGira; inscripto?: Inscripto; sugerida?: { plaza: number | null; fecha_desde: string | null; fecha_hasta: string | null; monto_esperado: number } }) {
  const [altaRapida, setAltaRapida] = useState(false);
  const f = useFormulario({
    alumno_id: (i?.alumno?.id ?? null) as number | null,
    alumno: i?.alumno?.nombre ?? '',
    estado_pago: i?.estado_pago ?? 'pendiente',
    monto_esperado: String(i?.monto_esperado ?? sugerida?.monto_esperado ?? 0),
    monto_pagado: String(i?.monto_pagado ?? 0),
    calcular_aporte: !i,
    plaza: i?.plaza != null ? String(i.plaza) : sugerida?.plaza != null ? String(sugerida.plaza) : '',
    lista_espera: i?.lista_espera ?? false,
    fecha_desde: i?.fecha_desde ?? sugerida?.fecha_desde ?? '',
    fecha_hasta: i?.fecha_hasta ?? sugerida?.fecha_hasta ?? '',
    talle_remera: i?.talle_remera ?? '',
    tambor_principal: i?.tambor_principal ?? '',
    tambor_principal_origen: i?.tambor_principal_origen ?? '',
    tambor_secundario: i?.tambor_secundario ?? '',
    tambor_secundario_origen: i?.tambor_secundario_origen ?? '',
    notas: i?.notas ?? '',
  });
  const { valores: v, set, errores: e } = f;
  const guardar = useOperacion(
    (d: typeof v) => {
      const { alumno: _, ...resto } = d;
      const vacioANull = (x: string) => (x === '' ? null : x);
      return api<{ data: Inscripto }>(i ? `villa-gesell/inscriptos/${i.id}` : 'villa-gesell/inscriptos', {
        method: i ? 'PUT' : 'POST',
        body: {
          ...resto,
          monto_esperado: Number(d.monto_esperado) || 0,
          monto_pagado: Number(d.monto_pagado) || 0,
          plaza: d.lista_espera || d.plaza === '' ? null : Number(d.plaza),
          fecha_desde: vacioANull(d.fecha_desde), fecha_hasta: vacioANull(d.fecha_hasta),
          talle_remera: vacioANull(d.talle_remera),
          tambor_principal: vacioANull(d.tambor_principal), tambor_principal_origen: vacioANull(d.tambor_principal_origen),
          tambor_secundario: vacioANull(d.tambor_secundario), tambor_secundario_origen: vacioANull(d.tambor_secundario_origen),
          notas: vacioANull(d.notas),
        },
      });
    },
    { exito: i ? 'Inscripción actualizada' : 'Alumno inscripto en la gira', invalidar: ['villa-gesell'], alTerminar: () => router.back() },
  );
  const eliminar = useOperacion(() => api(`villa-gesell/inscriptos/${i?.id}`, { method: 'DELETE' }), { exito: 'Inscripción eliminada', invalidar: ['villa-gesell'], alTerminar: () => router.back() });
  const tambores = c.tambores.map((t) => ({ valor: t, etiqueta: t }));
  const origenes = Object.entries(c.origenes_tambor).map(([valor, etiqueta]) => ({ valor, etiqueta }));

  return (
    <Pantalla>
      <Stack.Screen options={{ title: i ? 'Inscripción' : 'Inscribir a la gira' }} />
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Seccion titulo="Alumno">
        <SelectorLista<number>
          etiqueta="Alumno"
          requerido
          valor={v.alumno_id}
          valorEtiqueta={v.alumno}
          error={e.alumno_id}
          claveBusqueda={`vg-alumnos-${i?.id ?? 0}`}
          buscar={async (t) => (await api<{ data: { id: number; nombre: string; sede: string | null }[] }>(`villa-gesell/alumnos-disponibles${qs({ q: t, excepto: i?.alumno?.id })}`)).data.map((a) => ({ valor: a.id, etiqueta: a.nombre, detalle: a.sede ?? undefined }))}
          onChange={(x, o) => { set('alumno_id', x); set('alumno', o?.etiqueta ?? ''); }}
        />
        {!i && <Boton titulo="No está en el padrón: alta rápida" icono="person-add-alt" variante="secundario" onPress={() => setAltaRapida(true)} />}
      </Seccion>
      <Seccion titulo="Lugar">
        <Interruptor etiqueta="Lista de espera" ayuda="No ocupa plaza hasta que se libere una." valor={v.lista_espera} onChange={(x) => set('lista_espera', x)} />
        {!v.lista_espera && <Campo etiqueta="Plaza" valor={v.plaza} onChange={(x) => set('plaza', x.replace(/\D/g, ''))} teclado="number-pad" error={e.plaza} />}
        <CampoFecha etiqueta="Desde" valor={v.fecha_desde} onChange={(x) => set('fecha_desde', x)} error={e.fecha_desde} opcional />
        <CampoFecha etiqueta="Hasta" valor={v.fecha_hasta} onChange={(x) => set('fecha_hasta', x)} error={e.fecha_hasta} opcional minimo={v.fecha_desde || undefined} />
      </Seccion>
      <Seccion titulo="Aporte">
        <Selector etiqueta="Estado" opciones={Object.entries(c.estados_pago).map(([valor, etiqueta]) => ({ valor, etiqueta }))} valor={v.estado_pago} onChange={(x) => set('estado_pago', x ?? 'pendiente')} error={e.estado_pago} />
        <Interruptor etiqueta="Calcular aporte según días" valor={v.calcular_aporte} onChange={(x) => set('calcular_aporte', x)} />
        {!v.calcular_aporte && <CampoMonto etiqueta="Aporte esperado ($)" valor={v.monto_esperado} onChange={(x) => set('monto_esperado', x)} error={e.monto_esperado} />}
        <CampoMonto etiqueta="Pagado ($)" valor={v.monto_pagado} onChange={(x) => set('monto_pagado', x)} error={e.monto_pagado} />
        {!v.calcular_aporte && <Tenue>Saldo: {moneda(Math.max(0, (Number(v.monto_esperado) || 0) - (Number(v.monto_pagado) || 0)))}</Tenue>}
      </Seccion>
      <Seccion titulo="Remera y tambores">
        <Selector etiqueta="Talle de remera" permitirVacio="Sin dato" opciones={c.talles.map((t) => ({ valor: t, etiqueta: t }))} valor={v.talle_remera || null} onChange={(x) => set('talle_remera', x ?? '')} error={e.talle_remera} />
        <Selector etiqueta="Tambor principal" permitirVacio="Sin dato" opciones={tambores} valor={v.tambor_principal || null} onChange={(x) => set('tambor_principal', x ?? '')} error={e.tambor_principal} />
        {!!v.tambor_principal && <Selector etiqueta="Origen del tambor principal" permitirVacio="Sin dato" opciones={origenes} valor={v.tambor_principal_origen || null} onChange={(x) => set('tambor_principal_origen', x ?? '')} />}
        <Selector etiqueta="Tambor secundario" permitirVacio="Sin dato" opciones={tambores} valor={v.tambor_secundario || null} onChange={(x) => set('tambor_secundario', x ?? '')} error={e.tambor_secundario} />
        {!!v.tambor_secundario && <Selector etiqueta="Origen del tambor secundario" permitirVacio="Sin dato" opciones={origenes} valor={v.tambor_secundario_origen || null} onChange={(x) => set('tambor_secundario_origen', x ?? '')} />}
        <Campo etiqueta="Notas" valor={v.notas} onChange={(x) => set('notas', x)} error={e.notas} multilinea />
      </Seccion>
      <Boton titulo={i ? 'Guardar cambios' : 'Inscribir'} icono="save" grande cargando={f.enviando} onPress={() => void f.enviar((d) => guardar.mutateAsync(d), (d) => requeridos(d, { alumno_id: 'El alumno' }))} />
      {i && (
        <Boton titulo="Eliminar inscripción" icono="delete" variante="peligro" cargando={eliminar.isPending} onPress={async () => {
          if (await confirmar({ titulo: `¿Eliminar la inscripción de ${i.alumno?.nombre ?? 'este alumno'}?`, mensaje: 'Libera su plaza. No borra al alumno.', accion: 'Eliminar' })) eliminar.mutate();
        }} />
      )}
      <Hoja visible={altaRapida} titulo="Alta rápida al padrón" onCerrar={() => setAltaRapida(false)}>
        {altaRapida && <AltaRapida catalogo={c} onCreado={(a) => { set('alumno_id', a.id); set('alumno', a.nombre); setAltaRapida(false); }} />}
      </Hoja>
    </Pantalla>
  );
}

function AltaRapida({ catalogo, onCreado }: { catalogo: CatalogoGira; onCreado: (a: { id: number; nombre: string }) => void }) {
  const f = useFormulario({ nombre_apellido: '', dni: '', telefono: '', bloque_id: null as number | null });
  const op = useOperacion(
    (d: typeof f.valores) => api<{ data: { id: number; nombre: string } }>('villa-gesell/alumnos-rapidos', { method: 'POST', body: { ...d, dni: d.dni || null, telefono: d.telefono || null } }),
    { exito: 'Alumno creado. Ya lo podés inscribir a la gira', invalidar: ['alumnos', 'villa-gesell'], alTerminar: (r) => onCreado(r.data) },
  );
  return (
    <>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Campo etiqueta="Nombre y apellido" requerido valor={f.valores.nombre_apellido} onChange={(x) => f.set('nombre_apellido', x)} error={f.errores.nombre_apellido} autoCapitalize="words" />
      <Campo etiqueta="DNI" valor={f.valores.dni} onChange={(x) => f.set('dni', x)} error={f.errores.dni} teclado="number-pad" />
      <Campo etiqueta="Teléfono" valor={f.valores.telefono} onChange={(x) => f.set('telefono', x)} error={f.errores.telefono} teclado="phone-pad" />
      <SelectorLista<number> etiqueta="Bloque" valor={f.valores.bloque_id} onChange={(x) => f.set('bloque_id', x)} permitirVacio placeholder="Sin bloque"
        opciones={catalogo.bloques.map((b) => ({ valor: b.id, etiqueta: b.nombre, detalle: [b.sede, b.profesor].filter(Boolean).join(' · ') }))} />
      <Boton titulo="Crear alumno" icono="person-add" cargando={f.enviando} onPress={() => void f.enviar((d) => op.mutateAsync(d), (d) => requeridos(d, { nombre_apellido: 'El nombre' }))} />
    </>
  );
}
