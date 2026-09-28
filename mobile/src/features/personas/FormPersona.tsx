import { router } from 'expo-router';

import { Campo, CampoFecha, ErrorFormulario, requeridos, Seccion, Selector, useFormulario } from '@/components/form';
import { Boton, Pantalla } from '@/components/ui';
import { api } from '@/lib/api';
import { useOperacion } from '@/lib/recursos';

import { ESTADOS_PERSONA, type Persona } from './tipos';

const vacio = {
  nombre: '', apellido: '', dni: '', fecha_nacimiento: '', telefono: '', email: '', direccion: '',
  contacto_emergencia_nombre: '', contacto_emergencia_telefono: '', estado: 'activo' as string, observaciones: '',
};

/** Alta o edición de persona, dividido en secciones cortas. */
export function FormPersona({ persona }: { persona?: Persona }) {
  const f = useFormulario(persona ? {
    ...vacio,
    ...Object.fromEntries(Object.keys(vacio).map((k) => [k, (persona as unknown as Record<string, unknown>)[k] ?? ''])),
  } as typeof vacio : vacio);
  const { valores: v, set, errores: e } = f;

  const guardar = useOperacion(
    (datos: typeof vacio) => api<{ data: Persona }>(persona ? `personas/${persona.id}` : 'personas', {
      method: persona ? 'PUT' : 'POST',
      body: Object.fromEntries(Object.entries(datos).map(([k, x]) => [k, x === '' ? null : x])),
    }),
    {
      exito: persona ? 'Datos actualizados en todas sus fichas' : 'Persona creada correctamente',
      invalidar: ['personas'],
      alTerminar: (r) => (persona ? router.back() : router.replace({ pathname: '/personas/[id]', params: { id: String(r.data.id) } } as never)),
    },
  );

  return (
    <Pantalla>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Seccion titulo="Identidad">
        <Campo etiqueta="Nombre" requerido valor={v.nombre} onChange={(x) => set('nombre', x)} error={e.nombre} autoCapitalize="words" />
        <Campo etiqueta="Apellido" valor={v.apellido} onChange={(x) => set('apellido', x)} error={e.apellido} autoCapitalize="words" />
        <Campo etiqueta="DNI" valor={v.dni} onChange={(x) => set('dni', x)} error={e.dni} teclado="number-pad" ayuda="Sin puntos. Evita duplicar a la misma persona." />
        <CampoFecha etiqueta="Fecha de nacimiento" valor={v.fecha_nacimiento} onChange={(x) => set('fecha_nacimiento', x)} error={e.fecha_nacimiento} opcional />
        <Selector etiqueta="Estado" opciones={[...ESTADOS_PERSONA]} valor={v.estado} onChange={(x) => set('estado', x ?? 'activo')} error={e.estado} requerido />
      </Seccion>
      <Seccion titulo="Contacto">
        <Campo etiqueta="Teléfono" valor={v.telefono} onChange={(x) => set('telefono', x)} error={e.telefono} teclado="phone-pad" />
        <Campo etiqueta="Email" valor={v.email} onChange={(x) => set('email', x)} error={e.email} teclado="email-address" autoCapitalize="none" />
        <Campo etiqueta="Dirección" valor={v.direccion} onChange={(x) => set('direccion', x)} error={e.direccion} />
      </Seccion>
      <Seccion titulo="Contacto de emergencia">
        <Campo etiqueta="Nombre del contacto" valor={v.contacto_emergencia_nombre} onChange={(x) => set('contacto_emergencia_nombre', x)} error={e.contacto_emergencia_nombre} autoCapitalize="words" />
        <Campo etiqueta="Teléfono del contacto" valor={v.contacto_emergencia_telefono} onChange={(x) => set('contacto_emergencia_telefono', x)} error={e.contacto_emergencia_telefono} teclado="phone-pad" />
      </Seccion>
      <Seccion titulo="Observaciones">
        <Campo etiqueta="Notas internas" valor={v.observaciones} onChange={(x) => set('observaciones', x)} error={e.observaciones} multilinea maxLength={2000} />
      </Seccion>
      <Boton
        titulo={persona ? 'Guardar cambios' : 'Crear persona'}
        icono="save"
        grande
        cargando={f.enviando}
        onPress={() => void f.enviar((d) => guardar.mutateAsync(d), (d) => requeridos(d, { nombre: 'El nombre' }))}
      />
    </Pantalla>
  );
}
