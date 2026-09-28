import { router, Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';

import { confirmar } from '@/components/feedback';
import { Acciones, Boton, Cargando, Chip, Dato, Encabezado, ErrorVista, Pantalla, Segmentos, Tarjeta } from '@/components/ui';
import { HistorialAuditoria } from '@/features/auditoria/HistorialAuditoria';
import type { Gasto } from '@/features/finanzas/FormGasto';
import { api } from '@/lib/api';
import { usePuede } from '@/lib/permisos';
import { useDetalle, useOperacion } from '@/lib/recursos';
import { C, moneda } from '@/lib/theme';

const COLOR = { pendiente: C.alerta, aprobado: C.exito, rechazado: C.peligro } as const;

export default function FichaGasto() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<Gasto>('gastos', id);
  const verAuditoria = usePuede('auditoria.view');
  const [pestana, setPestana] = useState<'detalle' | 'historial'>('detalle');
  const decidir = useOperacion((decision: 'aprobado' | 'rechazado') => api(`gastos/${id}/decision`, { method: 'POST', body: { decision } }), {
    exito: (r) => ((r as { data: Gasto }).data.estado === 'aprobado' ? 'Gasto aprobado' : 'Gasto rechazado'),
    invalidar: ['gastos'],
  });
  const eliminar = useOperacion(() => api(`gastos/${id}`, { method: 'DELETE' }), { exito: 'Gasto eliminado', invalidar: ['gastos'], alTerminar: () => router.back() });

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const g = q.data;
  const ocupado = decidir.isPending;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Gasto' }} />
      <Encabezado icono="account-balance-wallet" titulo={moneda(g.monto)} subtitulo={`${g.tipo_nombre}${g.subtipo_nombre ? ` · ${g.subtipo_nombre}` : ''} · ${g.fecha}`}
        chips={<Chip texto={g.estado_nombre} color={COLOR[g.estado]} />} />
      <Acciones>
        {g.acciones?.aprobar && (
          <Boton titulo="Aprobar" icono="check-circle" cargando={ocupado} onPress={async () => {
            if (await confirmar({ titulo: `¿Aprobar el gasto de ${moneda(g.monto)}?`, mensaje: 'Queda registrado quién y cuándo lo aprobó.', accion: 'Aprobar', destructiva: false })) decidir.mutate('aprobado');
          }} />
        )}
        {g.acciones?.rechazar && (
          <Boton titulo="Rechazar" icono="cancel" variante="peligro" cargando={ocupado} onPress={async () => {
            if (await confirmar({ titulo: `¿Rechazar el gasto de ${moneda(g.monto)}?`, mensaje: 'Queda registrado en auditoría.', accion: 'Rechazar' })) decidir.mutate('rechazado');
          }} />
        )}
        {g.acciones?.editar && <Boton titulo="Editar" icono="edit" variante="secundario" onPress={() => router.push({ pathname: '/gastos/[id]/editar', params: { id: String(g.id) } } as never)} />}
        {g.acciones?.eliminar && (
          <Boton titulo="Eliminar" icono="delete" variante="peligro" cargando={eliminar.isPending} onPress={async () => {
            if (await confirmar({ titulo: '¿Eliminar este gasto?', mensaje: 'Queda registrado en auditoría.', accion: 'Eliminar' })) eliminar.mutate();
          }} />
        )}
      </Acciones>
      {verAuditoria && <Segmentos opciones={[{ valor: 'detalle', etiqueta: 'Detalle' }, { valor: 'historial', etiqueta: 'Historial' }]} valor={pestana} onChange={setPestana} />}
      {pestana === 'detalle' && (
        <Tarjeta>
          <Dato etiqueta="Descripción" valor={g.descripcion} />
          <Dato etiqueta="Proveedor" valor={g.proveedor} />
          <Dato etiqueta="Sede" valor={g.sede?.nombre ?? 'Toda la escuela'} />
          <Dato etiqueta="Bloque" valor={g.bloque?.nombre} />
          <Dato etiqueta="Registrado por" valor={g.creado_por} />
          <Dato etiqueta={g.estado === 'rechazado' ? 'Rechazado por' : 'Aprobado por'} valor={g.aprobado_por ? `${g.aprobado_por}${g.aprobado_at ? ` · ${new Date(g.aprobado_at).toLocaleString('es-AR')}` : ''}` : null} />
          <Dato etiqueta="Notas" valor={g.notas} />
        </Tarjeta>
      )}
      {pestana === 'historial' && <HistorialAuditoria entidad="Gasto" id={g.id} />}
    </Pantalla>
  );
}
