import { router, Stack } from 'expo-router';
import { useState } from 'react';

import { FiltrosChips, ItemLista, ListaPaginada } from '@/components/lista';
import { Chip } from '@/components/ui';
import { usePuede } from '@/lib/permisos';
import { useCatalogo } from '@/lib/recursos';
import { C } from '@/lib/theme';
import type { Bloque } from '@/lib/types';

export default function Bloques() {
  const [sede, setSede] = useState<number | null>(null);
  const [anio, setAnio] = useState<number | null>(null);
  const [inactivos, setInactivos] = useState<string | null>(null);
  const sedes = useCatalogo<{ data: { id: number; nombre: string }[] }>('sedes');
  const puedeCrear = usePuede('bloques.manage');

  return (
    <>
      <Stack.Screen options={{ title: 'Bloques' }} />
      <ListaPaginada<Bloque & { activo?: boolean }>
        ruta="bloques"
        filtros={{ sede_id: sede, anio, incluir_inactivos: inactivos ? 1 : undefined }}
        buscar
        placeholderBusqueda="Nombre del bloque"
        vacio="No hay bloques en tu alcance."
        iconoVacio="groups"
        cabecera={
          <>
            <FiltrosChips opciones={(sedes.data?.data ?? []).map((s) => ({ valor: s.id, etiqueta: s.nombre }))} valor={sede} onChange={setSede} todos="Todas las sedes" />
            <FiltrosChips opciones={[1, 2, 3, 4, 5, 6].map((n) => ({ valor: n, etiqueta: `${n}° año` }))} valor={anio} onChange={setAnio} todos="Todos los años" />
            <FiltrosChips opciones={[{ valor: '1', etiqueta: 'Incluir inactivos' }]} valor={inactivos} onChange={setInactivos} todos={false} />
          </>
        }
        onCrear={puedeCrear ? () => router.push('/bloques/nuevo' as never) : undefined}
        textoCrear="Nuevo bloque"
        render={(b) => (
          <ItemLista
            icono="groups"
            colorIcono={b.activo === false ? C.tenue : C.acento}
            titulo={b.nombre}
            subtitulo={`${b.anio}° año${b.sede ? ` · ${b.sede.nombre}` : ''}`}
            detalle={b.horarios?.map((h) => `${h.dia_nombre.slice(0, 3)} ${h.inicio}`).join(' · ') || 'Sin horario'}
            derecha={<Chip texto={b.activo === false ? 'Inactivo' : `${b.cantidad_alumnos ?? 0} alumnos`} color={b.activo === false ? C.tenue : C.info} />}
            onPress={() => router.push({ pathname: '/bloques/[id]', params: { id: String(b.id) } } as never)}
          />
        )}
      />
    </>
  );
}
