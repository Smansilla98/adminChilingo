import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { Pressable, View } from 'react-native';

import { confirmar, Hoja } from '@/components/feedback';
import { Campo, CampoFecha, ErrorFormulario, hoyIso, Interruptor, requeridos, Selector, useFormulario } from '@/components/form';
import { Boton, Chip, Fila, Icon, Subtitulo, Tarjeta, Tenue, Texto } from '@/components/ui';
import { api } from '@/lib/api';
import { formatearFecha } from '@/lib/formato';
import { useOperacion } from '@/lib/recursos';
import { C, E } from '@/lib/theme';

interface Nota { id: number; fecha: string; tipo: string; tipo_nombre: string; eje: string | null; eje_nombre: string | null; toque: string | null; cuerpo: string; proximo_paso: string | null; visible_alumno: boolean; autor: string | null; bloque: string | null; puede_eliminar: boolean }
interface Respuesta { data: Nota[]; puede_escribir: boolean; tipos: Record<string, string>; ejes: Record<string, string> }

/** Bitácora pedagógica del alumno (notas del equipo docente). */
export function Seguimiento({ alumnoId }: { alumnoId: number }) {
  const q = useQuery({ queryKey: ['alumnos', 'seguimiento', alumnoId], queryFn: () => api<Respuesta>(`alumnos/${alumnoId}/seguimiento`), retry: false });
  const [escribiendo, setEscribiendo] = useState(false);
  const borrar = useOperacion((id: number) => api(`seguimiento/${id}`, { method: 'DELETE' }), { exito: 'Nota pedagógica eliminada', invalidar: [['alumnos', 'seguimiento', alumnoId]] });
  if (q.isError) return null; // sin acceso: no se muestra la sección
  if (!q.data) return null;

  return (
    <View style={{ gap: E.s }}>
      <Subtitulo>Seguimiento pedagógico</Subtitulo>
      {q.data.puede_escribir && <Boton titulo="Nueva nota" icono="edit-note" variante="secundario" onPress={() => setEscribiendo(true)} />}
      {q.data.data.length === 0 && <Tenue>Sin notas todavía.</Tenue>}
      {q.data.data.map((n) => (
        <Tarjeta key={n.id}>
          <Fila style={{ justifyContent: 'space-between' }}>
            <Texto style={{ fontWeight: '800', flex: 1 }}>{n.tipo_nombre}{n.eje_nombre ? ` · ${n.eje_nombre}` : ''}</Texto>
            {n.visible_alumno && <Chip texto="Visible" color={C.info} />}
            {n.puede_eliminar && (
              <Pressable accessibilityRole="button" accessibilityLabel="Eliminar nota" hitSlop={10} onPress={async () => { if (await confirmar({ titulo: '¿Eliminar esta nota?', accion: 'Eliminar' })) borrar.mutate(n.id); }}>
                <Icon name="delete-outline" size={22} color={C.peligro} />
              </Pressable>
            )}
          </Fila>
          <Texto>{n.cuerpo}</Texto>
          {n.proximo_paso && <Tenue>Próximo paso: {n.proximo_paso}</Tenue>}
          <Tenue style={{ fontSize: 13 }}>{[formatearFecha(n.fecha), n.toque, n.bloque, n.autor].filter(Boolean).join(' · ')}</Tenue>
        </Tarjeta>
      ))}
      <Hoja visible={escribiendo} titulo="Nueva nota pedagógica" onCerrar={() => setEscribiendo(false)}>
        {escribiendo && <NuevaNota alumnoId={alumnoId} tipos={q.data.tipos} ejes={q.data.ejes} onCerrar={() => setEscribiendo(false)} />}
      </Hoja>
    </View>
  );
}

function NuevaNota({ alumnoId, tipos, ejes, onCerrar }: { alumnoId: number; tipos: Record<string, string>; ejes: Record<string, string>; onCerrar: () => void }) {
  const f = useFormulario({ fecha: hoyIso(), tipo: 'clase', eje: null as string | null, toque: '', cuerpo: '', proximo_paso: '', visible_alumno: false });
  const op = useOperacion((d: typeof f.valores) => api('seguimiento', { method: 'POST', body: { ...d, alumno_id: alumnoId, toque: d.toque || null, proximo_paso: d.proximo_paso || null } }), {
    exito: 'Quedó registrada la nota pedagógica', invalidar: [['alumnos', 'seguimiento', alumnoId]], alTerminar: onCerrar,
  });
  const { valores: v, set, errores: e } = f;
  return (
    <>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <CampoFecha etiqueta="Fecha" valor={v.fecha} onChange={(x) => set('fecha', x)} error={e.fecha} />
      <Selector etiqueta="Tipo" opciones={Object.entries(tipos).map(([valor, etiqueta]) => ({ valor, etiqueta }))} valor={v.tipo} onChange={(x) => set('tipo', x ?? 'nota')} error={e.tipo} />
      <Selector etiqueta="Eje" permitirVacio="Sin eje" opciones={Object.entries(ejes).map(([valor, etiqueta]) => ({ valor, etiqueta }))} valor={v.eje} onChange={(x) => set('eje', x)} />
      <Campo etiqueta="Nota" requerido valor={v.cuerpo} onChange={(x) => set('cuerpo', x)} error={e.cuerpo} multilinea maxLength={4000} />
      <Campo etiqueta="Toque" valor={v.toque} onChange={(x) => set('toque', x)} error={e.toque} />
      <Campo etiqueta="Próximo paso" valor={v.proximo_paso} onChange={(x) => set('proximo_paso', x)} error={e.proximo_paso} />
      <Interruptor etiqueta="Visible para el alumno" valor={v.visible_alumno} onChange={(x) => set('visible_alumno', x)} />
      <Boton titulo="Guardar nota" icono="save" cargando={f.enviando} onPress={() => void f.enviar((d) => op.mutateAsync(d), (d) => requeridos(d, { cuerpo: 'La nota' }))} />
    </>
  );
}
