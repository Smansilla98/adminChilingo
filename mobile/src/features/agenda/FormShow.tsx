import { router } from 'expo-router';

import { Campo, CampoFecha, CampoHora, ErrorFormulario, hoyIso, Interruptor, requeridos, Seccion, SelectorMultiple, useFormulario } from '@/components/form';
import { Boton, Cargando, ErrorVista, Pantalla } from '@/components/ui';
import { api } from '@/lib/api';
import { usePermisos } from '@/lib/permisos';
import { useCatalogo, useOperacion } from '@/lib/recursos';

import type { CatalogoEventos, Show } from './tipos';

/** Alta/edición de show y bloques convocados. Sin alcance global hay que convocar bloques propios. */
export function FormShow({ show }: { show?: Show }) {
  const cat = useCatalogo<CatalogoEventos>('eventos/catalogo', 1);
  const global = usePermisos().puedeGlobal('shows.manage');
  const f = useFormulario({
    titulo: show?.titulo ?? '',
    fecha: show?.fecha ?? hoyIso(),
    hora_inicio: show?.hora_inicio ?? '',
    hora_fin: show?.hora_fin ?? '',
    lugar: show?.lugar ?? '',
    descripcion: show?.descripcion ?? '',
    convocatoria_abierta: show?.convocatoria_abierta ?? false,
    bloque_ids: show?.bloques.map((b) => b.id) ?? ([] as number[]),
  });
  const { valores: v, set, errores: e } = f;
  const guardar = useOperacion(
    (d: typeof v) => api<{ data: Show }>(show ? `shows/${show.id}` : 'shows', {
      method: show ? 'PUT' : 'POST',
      body: { ...d, hora_inicio: d.hora_inicio || null, hora_fin: d.hora_fin || null, lugar: d.lugar || null, descripcion: d.descripcion || null },
    }),
    {
      exito: show ? 'Show actualizado' : 'Show creado correctamente',
      invalidar: ['shows', 'calendario'],
      alTerminar: (r) => (show ? router.back() : router.replace({ pathname: '/shows/[id]', params: { id: String(r.data.id) } } as never)),
    },
  );

  if (cat.isPending) return <Cargando />;
  if (cat.isError) return <ErrorVista error={cat.error} onReintentar={() => cat.refetch()} />;

  return (
    <Pantalla>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Seccion titulo="Show">
        <Campo etiqueta="Título" requerido valor={v.titulo} onChange={(x) => set('titulo', x)} error={e.titulo} />
        <Campo etiqueta="Lugar" valor={v.lugar} onChange={(x) => set('lugar', x)} error={e.lugar} />
        <Campo etiqueta="Descripción" valor={v.descripcion} onChange={(x) => set('descripcion', x)} error={e.descripcion} multilinea />
      </Seccion>
      <Seccion titulo="Cuándo">
        <CampoFecha etiqueta="Fecha" requerido valor={v.fecha} onChange={(x) => set('fecha', x)} error={e.fecha} />
        <CampoHora etiqueta="Hora de inicio" valor={v.hora_inicio} onChange={(x) => set('hora_inicio', x)} error={e.hora_inicio} opcional />
        <CampoHora etiqueta="Hora de fin" valor={v.hora_fin} onChange={(x) => set('hora_fin', x)} error={e.hora_fin} opcional />
      </Seccion>
      <Seccion titulo="Convocatoria">
        <Interruptor etiqueta="Convocatoria abierta" ayuda="Lo ve toda la escuela en la agenda." valor={v.convocatoria_abierta} onChange={(x) => set('convocatoria_abierta', x)} />
        <SelectorMultiple
          etiqueta="Bloques convocados"
          ayuda={global ? undefined : 'Solo podés convocar bloques de tu alcance.'}
          opciones={cat.data.bloques.map((b) => ({ valor: b.id, etiqueta: `${b.nombre}${b.sede ? ` · ${b.sede}` : ''}` }))}
          valores={v.bloque_ids}
          onChange={(x) => set('bloque_ids', x)}
          error={e.bloque_ids}
        />
      </Seccion>
      <Boton titulo={show ? 'Guardar cambios' : 'Crear show'} icono="save" grande cargando={f.enviando}
        onPress={() => void f.enviar((d) => guardar.mutateAsync(d), (d) => ({
          ...requeridos(d, { titulo: 'El título', fecha: 'La fecha' }),
          ...(!global && d.bloque_ids.length === 0 ? { bloque_ids: 'Elegí al menos un bloque de tu alcance.' } : {}),
        }))} />
    </Pantalla>
  );
}
