import { useQuery } from '@tanstack/react-query';
import { Stack } from 'expo-router';

import { ItemLista } from '@/components/lista';
import { Cargando, Chip, ErrorVista, Pantalla, Subtitulo, Tenue, Vacio } from '@/components/ui';
import { COLOR_PRESTAMO, type Prestamo } from '@/features/prestamos/tipos';
import { api } from '@/lib/api';
import { formatearFecha } from '@/lib/formato';

/** Alumno: instrumentos de la escuela que tiene prestados, y los que ya devolvió. */
export default function MisPrestamos() {
  const q = useQuery({ queryKey: ['mis-prestamos'], queryFn: ({ signal }) => api<{ data: Prestamo[] }>('mi/prestamos', { signal }).then((r) => r.data) });

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const abiertos = q.data.filter((p) => !p.devuelto_el);
  const devueltos = q.data.filter((p) => p.devuelto_el);

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Mis préstamos' }} />
      <Tenue>Instrumentos de la escuela que tenés a tu cargo. Si el plazo venció, coordiná la devolución con la escuela.</Tenue>
      {q.data.length === 0 && <Vacio icono="inventory-2" texto="No tenés instrumentos prestados." />}
      {abiertos.length > 0 && <Subtitulo>Prestados ({abiertos.length})</Subtitulo>}
      {abiertos.map((p) => (
        <ItemLista key={p.id} icono="inventory-2" colorIcono={COLOR_PRESTAMO[p.estado]} titulo={p.item.nombre}
          subtitulo={`${p.item.sede ?? ''}${p.item.codigo ? ` · ${p.item.codigo}` : ''}`}
          detalle={`Desde el ${formatearFecha(p.entregado_el)} · devolver antes del ${formatearFecha(p.devolucion_prevista)}`}
          derecha={<Chip texto={p.estado_nombre} color={COLOR_PRESTAMO[p.estado]} />} />
      ))}
      {devueltos.length > 0 && <Subtitulo>Devueltos</Subtitulo>}
      {devueltos.map((p) => (
        <ItemLista key={p.id} icono="check-circle" colorIcono={COLOR_PRESTAMO.devuelto} titulo={p.item.nombre}
          subtitulo={`Devuelto el ${formatearFecha(p.devuelto_el ?? '')}`}
          derecha={<Chip texto={p.estado_nombre} color={COLOR_PRESTAMO.devuelto} />} />
      ))}
    </Pantalla>
  );
}
