import { useMutation, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';

import { Boton, Cargando, ErrorVista, Pantalla, Tarjeta, Tenue, Texto, Vacio } from '@/components/ui';
import { api } from '@/lib/api';
import { useAvisos } from '@/lib/queries';
import { C } from '@/lib/theme';

const DESTINOS: Record<string, string> = { 'app://cuotas': '/cuotas', 'app://calendario': '/agenda' };

export default function Avisos() {
  const q = useAvisos();
  const qc = useQueryClient();
  const leer = useMutation({
    mutationFn: (id: string | null) => api(id ? `notificaciones/${id}/leer` : 'notificaciones/leer-todas', { method: 'POST' }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['avisos'] }),
  });

  if (q.isPending && !q.data) return <Cargando />;
  if (q.isError && !q.data) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      {(q.data?.no_leidas ?? 0) > 0 && <Boton titulo="Marcar todo como leído" variante="secundario" icono="done-all" onPress={() => leer.mutate(null)} />}
      {q.data?.data.length === 0 && <Vacio icono="notifications-none" texto="No tenés avisos." />}
      {q.data?.data.map((a) => (
        <Tarjeta
          key={a.id}
          acento={a.leida ? undefined : C.acento}
          onPress={() => {
            if (!a.leida) leer.mutate(a.id);
            const destino = a.enlace ? DESTINOS[a.enlace] : undefined;
            if (destino) router.push(destino as never);
          }}>
          <Texto style={{ fontWeight: a.leida ? '400' : '800' }}>{a.titulo}</Texto>
          <Tenue>{a.mensaje}</Tenue>
          <Tenue style={{ fontSize: 12 }}>{new Date(a.fecha).toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' })}</Tenue>
        </Tarjeta>
      ))}
    </Pantalla>
  );
}
