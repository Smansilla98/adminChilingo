import { router } from 'expo-router';

import { Campo, CampoFecha, CampoHora, ErrorFormulario, hoyIso, requeridos, Seccion, SelectorLista, useFormulario } from '@/components/form';
import { Boton, Cargando, ErrorVista, Pantalla } from '@/components/ui';
import { api } from '@/lib/api';
import { useCatalogo, useOperacion } from '@/lib/recursos';

import type { CatalogoEventos, Evento } from './tipos';

/** Alta/edición de evento. El ámbito (bloque, sede o toda la escuela) define quién lo ve. */
export function FormEvento({ evento, sedeInicial }: { evento?: Evento; sedeInicial?: number }) {
  const cat = useCatalogo<CatalogoEventos>('eventos/catalogo', 1);
  const f = useFormulario({
    titulo: evento?.titulo ?? '',
    tipo_evento: evento?.tipo ?? 'show',
    fecha: evento?.fecha ?? hoyIso(),
    hora_inicio: evento?.hora_inicio ?? '',
    hora_fin: evento?.hora_fin ?? '',
    sede_id: (evento?.sede?.id ?? sedeInicial ?? null) as number | null,
    bloque_id: (evento?.bloque?.id ?? null) as number | null,
    profesor_id: (evento?.profesor?.id ?? null) as number | null,
    cantidad_personas: evento?.cantidad_personas != null ? String(evento.cantidad_personas) : '',
    descripcion: evento?.descripcion ?? '',
  });
  const { valores: v, set, errores: e } = f;
  const guardar = useOperacion(
    (d: typeof v) => api<{ data: Evento }>(evento ? `eventos/${evento.id}` : 'eventos', {
      method: evento ? 'PUT' : 'POST',
      body: {
        ...d,
        hora_inicio: d.hora_inicio || null,
        hora_fin: d.hora_fin || null,
        cantidad_personas: d.cantidad_personas === '' ? null : Number(d.cantidad_personas),
        descripcion: d.descripcion || null,
      },
    }),
    {
      exito: evento ? 'Evento actualizado' : 'Evento creado correctamente',
      invalidar: ['eventos', 'calendario', 'sedes'],
      alTerminar: (r) => (evento ? router.back() : router.replace({ pathname: '/eventos/[id]', params: { id: String(r.data.id) } } as never)),
    },
  );

  if (cat.isPending) return <Cargando />;
  if (cat.isError) return <ErrorVista error={cat.error} onReintentar={() => cat.refetch()} />;
  const c = cat.data;
  const bloques = c.bloques.filter((b) => !v.sede_id || b.sede_id === v.sede_id);

  return (
    <Pantalla>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Seccion titulo="Evento">
        <Campo etiqueta="Título" requerido valor={v.titulo} onChange={(x) => set('titulo', x)} error={e.titulo} />
        <SelectorLista etiqueta="Tipo" requerido valor={v.tipo_evento} onChange={(x) => set('tipo_evento', x ?? 'otro')} error={e.tipo_evento}
          opciones={Object.entries(c.tipos).map(([valor, etiqueta]) => ({ valor, etiqueta }))} />
        <Campo etiqueta="Descripción" valor={v.descripcion} onChange={(x) => set('descripcion', x)} error={e.descripcion} multilinea />
      </Seccion>
      <Seccion titulo="Cuándo">
        <CampoFecha etiqueta="Fecha" requerido valor={v.fecha} onChange={(x) => set('fecha', x)} error={e.fecha} />
        <CampoHora etiqueta="Hora de inicio" valor={v.hora_inicio} onChange={(x) => set('hora_inicio', x)} error={e.hora_inicio} opcional />
        <CampoHora etiqueta="Hora de fin" valor={v.hora_fin} onChange={(x) => set('hora_fin', x)} error={e.hora_fin} opcional />
      </Seccion>
      <Seccion titulo="Dónde y para quién" ayuda="Sin sede ni bloque: evento de toda la escuela.">
        <SelectorLista<number> etiqueta="Sede" valor={v.sede_id} onChange={(x) => { set('sede_id', x); set('bloque_id', null); }} error={e.sede_id} permitirVacio placeholder="Toda la escuela"
          opciones={c.sedes.map((s) => ({ valor: s.id, etiqueta: s.nombre }))} />
        <SelectorLista<number> etiqueta="Bloque" valor={v.bloque_id} onChange={(x) => set('bloque_id', x)} error={e.bloque_id} permitirVacio placeholder="Todos los bloques"
          opciones={bloques.map((b) => ({ valor: b.id, etiqueta: b.nombre, detalle: b.sede ?? undefined }))} />
        <SelectorLista<number> etiqueta="Docente a cargo" valor={v.profesor_id} onChange={(x) => set('profesor_id', x)} error={e.profesor_id} permitirVacio placeholder="Sin docente"
          opciones={c.profesores.map((p) => ({ valor: p.id, etiqueta: p.nombre }))} />
        <Campo etiqueta="Cantidad de personas" valor={v.cantidad_personas} onChange={(x) => set('cantidad_personas', x.replace(/\D/g, ''))} teclado="number-pad" error={e.cantidad_personas} />
      </Seccion>
      <Boton titulo={evento ? 'Guardar cambios' : 'Crear evento'} icono="save" grande cargando={f.enviando}
        onPress={() => void f.enviar((d) => guardar.mutateAsync(d), (d) => ({
          ...requeridos(d, { titulo: 'El título', fecha: 'La fecha', tipo_evento: 'El tipo' }),
          ...(d.hora_inicio && d.hora_fin && d.hora_fin <= d.hora_inicio ? { hora_fin: 'La hora de fin tiene que ser posterior al inicio.' } : {}),
        }))} />
    </Pantalla>
  );
}
