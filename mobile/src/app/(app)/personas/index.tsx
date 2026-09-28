import { router, Stack } from 'expo-router';
import { useState } from 'react';

import { FiltrosChips, ItemLista, ListaPaginada } from '@/components/lista';
import { Chip } from '@/components/ui';
import type { PersonaResumen } from '@/features/personas/tipos';
import { usePuede } from '@/lib/permisos';
import { C } from '@/lib/theme';

const FUNCIONES = [
  { valor: 'alumno', etiqueta: 'Alumnos' },
  { valor: 'profesor', etiqueta: 'Docentes' },
  { valor: 'con_cuenta', etiqueta: 'Con cuenta' },
  { valor: 'sin_cuenta', etiqueta: 'Sin cuenta' },
];
const ESTADOS = [
  { valor: 'activo', etiqueta: 'Activas' },
  { valor: 'inactivo', etiqueta: 'Inactivas' },
  { valor: 'baja', etiqueta: 'De baja' },
];

export default function Personas() {
  const [funcion, setFuncion] = useState<string | null>(null);
  const [estado, setEstado] = useState<string | null>(null);
  const puedeCrear = usePuede('personas.create');

  return (
    <>
      <Stack.Screen options={{ title: 'Personas' }} />
      <ListaPaginada<PersonaResumen>
        ruta="personas"
        filtros={{ funcion, estado }}
        buscar
        placeholderBusqueda="Nombre, DNI, email o teléfono"
        vacio="No hay personas en tu alcance."
        iconoVacio="badge"
        cabecera={
          <>
            <FiltrosChips opciones={FUNCIONES} valor={funcion} onChange={setFuncion} />
            <FiltrosChips opciones={ESTADOS} valor={estado} onChange={setEstado} todos="Cualquier estado" />
          </>
        }
        onCrear={puedeCrear ? () => router.push('/personas/nueva' as never) : undefined}
        textoCrear="Nueva persona"
        render={(p) => (
          <ItemLista
            titulo={p.nombre_completo}
            subtitulo={[p.dni ? `DNI ${p.dni}` : null, p.telefono].filter(Boolean).join(' · ') || null}
            detalle={[p.es_alumno && 'Alumno/a', p.es_docente && 'Docente', p.tiene_cuenta && 'Con cuenta'].filter(Boolean).join(' · ') || null}
            acento={p.estado === 'activo' ? undefined : C.tenue}
            derecha={p.estado !== 'activo' ? <Chip texto={p.estado === 'baja' ? 'Baja' : 'Inactiva'} /> : undefined}
            onPress={() => router.push({ pathname: '/personas/[id]', params: { id: String(p.id) } } as never)}
          />
        )}
      />
    </>
  );
}
