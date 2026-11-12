import { router, Stack } from 'expo-router';

import { ItemLista } from '@/components/lista';
import { Cargando, ErrorVista, Pantalla, Tenue, Vacio } from '@/components/ui';
import { useBloquesProgreso } from '@/features/progreso/tipos';

/** Bloques para registrar el progreso por toque (docente / coordinación). */
export default function ProgresoBloques() {
  const q = useBloquesProgreso();
  if (q.isPending && !q.data) return <Cargando />;
  if (q.isError && !q.data) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const bloques = q.data?.data ?? [];

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Progreso por toque' }} />
      <Tenue>No es una nota: sirve para acompañar a cada alumno con los toques de su año.</Tenue>
      {bloques.length === 0 && <Vacio icono="groups" texto="No tenés bloques para mirar." />}
      {bloques.map((b) => (
        <ItemLista
          key={b.id}
          icono="groups"
          titulo={b.nombre}
          subtitulo={[b.sede, b.anio ? `${b.anio}° año` : null].filter(Boolean).join(' · ')}
          onPress={() => router.push({ pathname: '/progreso/[bloque]', params: { bloque: String(b.id), nombre: b.nombre } } as never)}
        />
      ))}
    </Pantalla>
  );
}
