import { router } from 'expo-router';

import { Campo, CampoFecha, CampoMonto, ErrorFormulario, hoyIso, requeridos, Seccion, Selector, SelectorLista, useFormulario } from '@/components/form';
import { Aviso, Boton, Cargando, ErrorVista, Pantalla } from '@/components/ui';
import { api } from '@/lib/api';
import { usePermisos } from '@/lib/permisos';
import { useCatalogo, useOperacion } from '@/lib/recursos';

export interface Gasto {
  id: number;
  fecha: string;
  tipo: string;
  tipo_nombre: string;
  subtipo: string | null;
  subtipo_nombre: string | null;
  descripcion: string | null;
  monto: number;
  proveedor: string | null;
  notas: string | null;
  estado: 'pendiente' | 'aprobado' | 'rechazado';
  estado_nombre: string;
  sede: { id: number; nombre: string } | null;
  bloque: { id: number; nombre: string } | null;
  creado_por: string | null;
  aprobado_at: string | null;
  aprobado_por: string | null;
  acciones: { editar: boolean; eliminar: boolean; aprobar: boolean; rechazar: boolean } | null;
}

export interface CatalogoGastos {
  tipos: Record<string, string>;
  subtipos: Record<string, Record<string, string>>;
  estados: Record<string, string>;
  sedes: { id: number; nombre: string }[];
  bloques: { id: number; nombre: string; sede_id: number }[];
  puede_sin_sede: boolean;
}

export function FormGasto({ gasto }: { gasto?: Gasto }) {
  const cat = useCatalogo<CatalogoGastos>('gastos/catalogo', 1);
  const { puedeEnSede } = usePermisos();
  const f = useFormulario({
    fecha: gasto?.fecha ?? hoyIso(),
    tipo: gasto?.tipo ?? 'servicio',
    subtipo: gasto?.subtipo ?? '',
    monto: gasto ? String(gasto.monto) : '',
    sede_id: (gasto?.sede?.id ?? null) as number | null,
    bloque_id: (gasto?.bloque?.id ?? null) as number | null,
    proveedor: gasto?.proveedor ?? '',
    descripcion: gasto?.descripcion ?? '',
    notas: gasto?.notas ?? '',
  });
  const { valores: v, set, errores: e } = f;
  const guardar = useOperacion(
    (d: typeof v) => api<{ data: Gasto }>(gasto ? `gastos/${gasto.id}` : 'gastos', {
      method: gasto ? 'PUT' : 'POST',
      body: { ...d, monto: Number(d.monto), subtipo: d.subtipo || null, proveedor: d.proveedor || null, descripcion: d.descripcion || null, notas: d.notas || null },
    }),
    {
      exito: (r) => (gasto ? 'Gasto actualizado' : r.data.estado === 'pendiente' ? 'Gasto registrado. Queda pendiente de aprobación' : 'Gasto registrado correctamente'),
      invalidar: ['gastos'],
      alTerminar: (r) => (gasto ? router.back() : router.replace({ pathname: '/gastos/[id]', params: { id: String(r.data.id) } } as never)),
    },
  );

  if (cat.isPending) return <Cargando />;
  if (cat.isError) return <ErrorVista error={cat.error} onReintentar={() => cat.refetch()} />;
  const c = cat.data;
  const subtipos = Object.entries(c.subtipos[v.tipo] ?? {}).map(([valor, etiqueta]) => ({ valor, etiqueta }));
  const quedaPendiente = !gasto && !puedeEnSede('gastos.approve', v.sede_id);

  return (
    <Pantalla>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Seccion titulo="Gasto">
        <CampoFecha etiqueta="Fecha" requerido valor={v.fecha} onChange={(x) => set('fecha', x)} error={e.fecha} />
        <Selector etiqueta="Categoría" requerido opciones={Object.entries(c.tipos).map(([valor, etiqueta]) => ({ valor, etiqueta }))} valor={v.tipo} onChange={(x) => { set('tipo', x ?? 'otro'); set('subtipo', ''); }} error={e.tipo} />
        {subtipos.length > 0 && <Selector etiqueta="Detalle" permitirVacio="Sin detalle" opciones={subtipos} valor={v.subtipo || null} onChange={(x) => set('subtipo', x ?? '')} error={e.subtipo} />}
        <CampoMonto etiqueta="Monto ($)" requerido valor={v.monto} onChange={(x) => set('monto', x)} error={e.monto} />
        <Campo etiqueta="Descripción" valor={v.descripcion} onChange={(x) => set('descripcion', x)} error={e.descripcion} maxLength={255} />
        <Campo etiqueta="Proveedor" valor={v.proveedor} onChange={(x) => set('proveedor', x)} error={e.proveedor} />
      </Seccion>
      <Seccion titulo="Dónde" ayuda={c.puede_sin_sede ? 'Sin sede: gasto de toda la escuela.' : 'Elegí la sede del gasto.'}>
        <SelectorLista<number> etiqueta="Sede" valor={v.sede_id} onChange={(x) => { set('sede_id', x); set('bloque_id', null); }} error={e.sede_id} permitirVacio={c.puede_sin_sede}
          placeholder={c.puede_sin_sede ? 'Toda la escuela' : 'Elegir sede'} opciones={c.sedes.map((s) => ({ valor: s.id, etiqueta: s.nombre }))} valorEtiqueta={gasto?.sede?.nombre} />
        {!!v.sede_id && (
          <SelectorLista<number> etiqueta="Bloque (opcional)" valor={v.bloque_id} onChange={(x) => set('bloque_id', x)} error={e.bloque_id} permitirVacio placeholder="Sin bloque"
            opciones={c.bloques.filter((b) => b.sede_id === v.sede_id).map((b) => ({ valor: b.id, etiqueta: b.nombre }))} valorEtiqueta={gasto?.bloque?.nombre} />
        )}
      </Seccion>
      <Seccion titulo="Notas">
        <Campo etiqueta="Notas internas" valor={v.notas} onChange={(x) => set('notas', x)} error={e.notas} multilinea />
      </Seccion>
      {quedaPendiente && <Aviso tono="info" texto="Vas a registrar el gasto como pendiente: lo aprueba tesorería." />}
      <Boton titulo={gasto ? 'Guardar cambios' : 'Registrar gasto'} icono="save" grande cargando={f.enviando}
        onPress={() => void f.enviar((d) => guardar.mutateAsync(d), (d) => ({
          ...requeridos(d, { fecha: 'La fecha', tipo: 'La categoría', monto: 'El monto' }),
          ...(!c.puede_sin_sede && !d.sede_id ? { sede_id: 'Elegí la sede.' } : {}),
        }))} />
    </Pantalla>
  );
}
