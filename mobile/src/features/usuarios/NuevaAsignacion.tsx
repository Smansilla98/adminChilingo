import { Campo, CampoFecha, ErrorFormulario, Selector, SelectorLista, useFormulario } from '@/components/form';
import { Hoja } from '@/components/feedback';
import { Boton, Tenue } from '@/components/ui';
import { api } from '@/lib/api';
import { useCatalogo, useOperacion } from '@/lib/recursos';

import type { CatalogoUsuarios } from './tipos';

/** Asignar un rol o un permiso adicional con alcance (toda la escuela, una sede o un bloque). */
export function NuevaAsignacion({ usuarioId, visible, onCerrar }: { usuarioId: number; visible: boolean; onCerrar: () => void }) {
  return (
    <Hoja visible={visible} titulo="Agregar rol o permiso" onCerrar={onCerrar}>
      {visible && <Formulario usuarioId={usuarioId} onCerrar={onCerrar} />}
    </Hoja>
  );
}

function Formulario({ usuarioId, onCerrar }: { usuarioId: number; onCerrar: () => void }) {
  const cat = useCatalogo<CatalogoUsuarios>('usuarios/catalogo', 1);
  const f = useFormulario({ tipo: 'rol' as 'rol' | 'permiso', nombre: '', ambito: 'global', sede_id: null as number | null, bloque_id: null as number | null, desde: '', hasta: '', notas: '' });
  const { valores: v, set, errores: e } = f;
  const op = useOperacion(
    (d: typeof v) => api(`usuarios/${usuarioId}/asignaciones`, { method: 'POST', body: { ...d, sede_id: d.ambito === 'sede' ? d.sede_id : null, bloque_id: d.ambito === 'bloque' ? d.bloque_id : null, desde: d.desde || null, hasta: d.hasta || null, notas: d.notas || null } }),
    { exito: 'Asignación agregada', invalidar: ['usuarios', 'personas', 'me'], alTerminar: onCerrar },
  );
  if (!cat.data) return <Tenue>Cargando opciones…</Tenue>;
  const c = cat.data;
  const ambitosRol = v.tipo === 'rol' && v.nombre ? c.roles_ambitos[v.nombre] ?? ['global', 'sede', 'bloque'] : ['global', 'sede', 'bloque'];
  const permisos = Object.entries(c.permisos).flatMap(([grupo, ps]) => Object.entries(ps).map(([clave, nombre]) => ({ valor: clave, etiqueta: nombre, detalle: grupo })));

  return (
    <>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Selector opciones={[{ valor: 'rol', etiqueta: 'Rol' }, { valor: 'permiso', etiqueta: 'Permiso adicional' }]} valor={v.tipo} onChange={(x) => { set('tipo', (x ?? 'rol') as 'rol' | 'permiso'); set('nombre', ''); }} />
      {v.tipo === 'rol'
        ? <SelectorLista etiqueta="Rol" requerido valor={v.nombre || null} onChange={(x) => { set('nombre', x ?? ''); set('ambito', (c.roles_ambitos[x ?? ''] ?? ['global'])[0]); }} error={e.nombre}
            opciones={Object.entries(c.roles).map(([valor, etiqueta]) => ({ valor, etiqueta }))} />
        : <SelectorLista etiqueta="Permiso" requerido valor={v.nombre || null} onChange={(x) => set('nombre', x ?? '')} error={e.nombre} opciones={permisos} />}
      <Selector etiqueta="Dónde" opciones={[{ valor: 'global', etiqueta: 'Toda la escuela' }, { valor: 'sede', etiqueta: 'Una sede' }, { valor: 'bloque', etiqueta: 'Un bloque' }].filter((o) => ambitosRol.includes(o.valor))}
        valor={v.ambito} onChange={(x) => set('ambito', x ?? 'global')} error={e.ambito} />
      {v.ambito === 'sede' && <SelectorLista<number> etiqueta="Sede" requerido valor={v.sede_id} onChange={(x) => set('sede_id', x)} error={e.sede_id} opciones={c.sedes.map((s) => ({ valor: s.id, etiqueta: s.nombre }))} />}
      {v.ambito === 'bloque' && <SelectorLista<number> etiqueta="Bloque" requerido valor={v.bloque_id} onChange={(x) => set('bloque_id', x)} error={e.bloque_id} opciones={c.bloques.map((b) => ({ valor: b.id, etiqueta: b.nombre, detalle: b.sede ?? undefined }))} />}
      <CampoFecha etiqueta="Desde (opcional)" valor={v.desde} onChange={(x) => set('desde', x)} error={e.desde} opcional />
      <CampoFecha etiqueta="Hasta (opcional)" valor={v.hasta} onChange={(x) => set('hasta', x)} error={e.hasta} opcional minimo={v.desde || undefined} />
      <Campo etiqueta="Notas" valor={v.notas} onChange={(x) => set('notas', x)} error={e.notas} />
      <Boton titulo="Asignar" icono="add-moderator" cargando={f.enviando}
        onPress={() => void f.enviar((d) => op.mutateAsync(d), (d): Record<string, string> => ({
          ...(!d.nombre ? { nombre: d.tipo === 'rol' ? 'Elegí el rol.' : 'Elegí el permiso.' } : {}),
          ...(d.ambito === 'sede' && !d.sede_id ? { sede_id: 'Elegí la sede.' } : {}),
          ...(d.ambito === 'bloque' && !d.bloque_id ? { bloque_id: 'Elegí el bloque.' } : {}),
        }))} />
    </>
  );
}
