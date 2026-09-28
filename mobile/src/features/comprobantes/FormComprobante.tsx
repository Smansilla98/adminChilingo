import { useQuery } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useState } from 'react';

import { Campo, CampoArchivo, CampoFecha, ErrorFormulario, hoyIso, Progreso, Seccion, Selector, SelectorLista, SelectorMultiple, useFormulario } from '@/components/form';
import { Aviso, Boton, Pantalla, Tenue } from '@/components/ui';
import { MESES } from '@/features/finanzas/tipos';
import { api, qs } from '@/lib/api';
import { type ArchivoLocal, subirArchivo } from '@/lib/archivos';
import { useOperacion } from '@/lib/recursos';
import { moneda } from '@/lib/theme';

import type { Comprobante } from './tipos';

interface Opciones { alumno: { id: number; nombre: string }; bloques: { id: number; nombre: string; cuota_id: number | null; cuota: string | null; monto: number | null; ya_pagada: boolean }[] }

/**
 * Enviar un comprobante de pago de cuota. `propio`: lo envía el alumno (su ficha);
 * si no, lo carga la escuela eligiendo el alumno. Queda pendiente de revisión.
 */
export function FormComprobante({ propio, alumnoInicial }: { propio: boolean; alumnoInicial?: { id: number; nombre: string } }) {
  const hoy = new Date();
  const [progreso, setProgreso] = useState<number | null>(null);
  const f = useFormulario({
    alumno_id: (alumnoInicial?.id ?? null) as number | null,
    alumno: alumnoInicial?.nombre ?? '',
    anio: hoy.getFullYear(),
    mes: hoy.getMonth() + 1,
    fecha_pago: hoyIso(),
    bloque_ids: [] as number[],
    comprobante: null as ArchivoLocal | null,
    notas: '',
  });
  const { valores: v, set, errores: e } = f;
  const opciones = useQuery({
    queryKey: ['comprobantes', 'opciones', v.alumno_id, v.anio, v.mes],
    queryFn: () => api<Opciones>(`comprobantes/opciones${qs({ alumno_id: v.alumno_id, anio: v.anio, mes: v.mes })}`),
    enabled: !!v.alumno_id,
  });
  const conCuota = (opciones.data?.bloques ?? []).filter((b) => b.cuota_id && !b.ya_pagada);
  const pagadas = (opciones.data?.bloques ?? []).filter((b) => b.ya_pagada);
  const total = conCuota.filter((b) => v.bloque_ids.includes(b.id)).reduce((s, b) => s + (b.monto ?? 0), 0);

  const enviar = useOperacion(
    async (d: typeof v) => {
      setProgreso(0);
      try {
        return await subirArchivo<{ data: Comprobante }>(propio ? 'mi/comprobantes' : 'comprobantes', d.comprobante, {
          campo: 'comprobante',
          datos: { alumno_id: d.alumno_id, anio: d.anio, mes: d.mes, fecha_pago: d.fecha_pago, notas: d.notas || null, ...Object.fromEntries(d.bloque_ids.map((id, i) => [`bloque_ids[${i}]`, id])) },
          onProgreso: setProgreso,
        });
      } finally {
        setProgreso(null);
      }
    },
    {
      exito: 'Comprobante enviado. Queda pendiente de revisión',
      invalidar: ['comprobantes', 'mi', 'alumnos'],
      alTerminar: (r) => (propio ? router.back() : router.replace({ pathname: '/comprobantes/[id]', params: { id: String(r.data.id) } } as never)),
    },
  );

  return (
    <Pantalla>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Seccion titulo="Qué pagaste">
        {!propio && (
          <SelectorLista<number>
            etiqueta="Alumno"
            requerido
            valor={v.alumno_id}
            valorEtiqueta={v.alumno}
            error={e.alumno_id}
            claveBusqueda="alumnos-comprobante"
            buscar={async (t) => (t.length < 2 ? [] : (await api<{ data: { id: number; nombre: string; sede?: { nombre: string } | null }[] }>(`alumnos${qs({ q: t })}`)).data.map((a) => ({ valor: a.id, etiqueta: a.nombre, detalle: a.sede?.nombre })))}
            onChange={(id, o) => { set('alumno_id', id); set('alumno', o?.etiqueta ?? ''); set('bloque_ids', []); }}
          />
        )}
        {propio && alumnoInicial && <Tenue>{alumnoInicial.nombre}</Tenue>}
        <Selector etiqueta="Año" opciones={[hoy.getFullYear() - 1, hoy.getFullYear()].map((a) => ({ valor: a, etiqueta: String(a) }))} valor={v.anio} onChange={(x) => { set('anio', x ?? hoy.getFullYear()); set('bloque_ids', []); }} />
        <Selector etiqueta="Mes de la cuota" opciones={MESES.map((m, i) => ({ valor: i + 1, etiqueta: m.slice(0, 3) }))} valor={v.mes} onChange={(x) => { set('mes', x ?? 1); set('bloque_ids', []); }} error={e.mes} />
        {opciones.isFetching && <Tenue>Buscando cuotas…</Tenue>}
        {opciones.isError && <Aviso tono="peligro" texto={(opciones.error as Error).message} />}
        {opciones.data && conCuota.length === 0 && <Aviso tono="info" texto={pagadas.length ? 'Las cuotas de ese mes ya figuran pagadas.' : 'No hay cuotas cargadas para ese mes en tus bloques.'} />}
        {conCuota.length > 0 && (
          <SelectorMultiple etiqueta="Cuotas (bloques)" opciones={conCuota.map((b) => ({ valor: b.id, etiqueta: `${b.nombre} · ${moneda(b.monto ?? 0)}` }))} valores={v.bloque_ids} onChange={(x) => set('bloque_ids', x)} error={e.bloque_ids ?? e.alumno_id} />
        )}
        {pagadas.length > 0 && <Tenue style={{ fontSize: 13 }}>Ya pagadas: {pagadas.map((b) => b.nombre).join(', ')}</Tenue>}
        {total > 0 && <Tenue>Total: {moneda(total)}</Tenue>}
      </Seccion>
      <Seccion titulo="Comprobante">
        <CampoFecha etiqueta="Fecha del pago" requerido valor={v.fecha_pago} onChange={(x) => set('fecha_pago', x)} error={e.fecha_pago} maximo={hoyIso()} />
        <CampoArchivo etiqueta="Foto o PDF del comprobante" requerido archivo={v.comprobante} onChange={(a) => set('comprobante', a)} error={e.comprobante} maxMb={10} tipos={['application/pdf', 'image/jpeg', 'image/png']} />
        {progreso !== null && <Progreso fraccion={progreso} texto="Subiendo comprobante…" />}
        <Campo etiqueta="Notas (opcional)" valor={v.notas} onChange={(x) => set('notas', x)} error={e.notas} multilinea maxLength={1000} />
      </Seccion>
      <Boton titulo="Enviar comprobante" icono="upload-file" grande cargando={f.enviando}
        onPress={() => void f.enviar((d) => enviar.mutateAsync(d), (d): Record<string, string> => ({
          ...(!d.alumno_id ? { alumno_id: 'Elegí el alumno.' } : {}),
          ...(d.bloque_ids.length === 0 ? { bloque_ids: 'Elegí qué cuota pagaste.' } : {}),
          ...(!d.comprobante ? { comprobante: 'Adjuntá la foto o el PDF del comprobante.' } : {}),
        }))} />
    </Pantalla>
  );
}
