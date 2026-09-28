import { router, Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';

import { confirmar } from '@/components/feedback';
import { ItemLista } from '@/components/lista';
import { Acciones, Boton, Cargando, Chip, Dato, Encabezado, ErrorVista, Pantalla, Segmentos, Tarjeta, Tenue } from '@/components/ui';
import { HistorialAuditoria } from '@/features/auditoria/HistorialAuditoria';
import { ALCANCE_CUOTA, type CuotaFicha } from '@/features/finanzas/tipos';
import { api } from '@/lib/api';
import { usePuede } from '@/lib/permisos';
import { useDetalle, useOperacion } from '@/lib/recursos';
import { C, moneda } from '@/lib/theme';

export default function FichaCuota() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<CuotaFicha>('cuotas', id);
  const verAuditoria = usePuede('auditoria.view');
  const [pestana, setPestana] = useState<'cobros' | 'alumnos' | 'recordatorios' | 'historial'>('cobros');
  const invalidar = ['cuotas', 'pagos', 'alumnos', 'personas'];
  const eliminar = useOperacion(() => api(`cuotas/${id}`, { method: 'DELETE' }), { exito: 'Cuota eliminada', invalidar, alTerminar: () => router.back() });
  const alternar = useOperacion(
    (c: CuotaFicha) => api(`cuotas/${c.id}`, { method: 'PUT', body: { nombre: c.nombre, anio: c.anio, mes: c.mes, monto: c.monto, fecha_vencimiento: c.vencimiento, alcance: c.alcance, sede_id: c.sede_id, bloque_id: c.bloque_id, descripcion: c.descripcion, activo: !c.activo } }),
    { exito: 'Estado de la cuota actualizado', invalidar },
  );

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const c = q.data;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Cuota' }} />
      <Encabezado icono="receipt-long" titulo={c.nombre} subtitulo={`${c.periodo} · ${moneda(c.monto)}`} chips={<>
        <Chip texto={c.activo ? 'Activa' : 'Inactiva'} color={c.activo ? C.exito : C.tenue} />
        {c.vencida && <Chip texto="Vencida" color={C.alerta} />}
        <Chip texto={c.alcance === 'general' ? 'Toda la escuela' : `${ALCANCE_CUOTA[c.alcance]}: ${c.sede ?? c.bloque}`} />
      </>} />
      <Acciones>
        {c.acciones.registrar_pago && c.activo && <Boton titulo="Registrar pago" icono="add-card" onPress={() => router.push({ pathname: '/pagos/nuevo', params: { cuota_id: String(c.id) } } as never)} />}
        {c.acciones.editar && <Boton titulo="Editar" icono="edit" variante="secundario" onPress={() => router.push({ pathname: '/cuotas/[id]/editar', params: { id: String(c.id) } } as never)} />}
        {c.acciones.editar && (
          <Boton titulo={c.activo ? 'Desactivar' : 'Activar'} icono={c.activo ? 'block' : 'check-circle'} variante="secundario" cargando={alternar.isPending} onPress={async () => {
            if (!c.activo || await confirmar({ titulo: '¿Desactivar esta cuota?', mensaje: 'Deja de cobrarse y de contar en la deuda de los alumnos. Los pagos registrados se conservan.', accion: 'Desactivar' })) alternar.mutate(c);
          }} />
        )}
        {c.acciones.eliminar && (
          <Boton titulo="Eliminar" icono="delete" variante="peligro" cargando={eliminar.isPending} onPress={async () => {
            if (await confirmar({ titulo: `¿Eliminar "${c.nombre}"?`, mensaje: 'Solo se puede si no tiene pagos. Si tiene, desactivala.', accion: 'Eliminar' })) eliminar.mutate();
          }} />
        )}
      </Acciones>
      <Tarjeta>
        <Dato etiqueta="Cobrado" valor={`${moneda(c.total_cobrado)} · ${c.pagos} pago${c.pagos === 1 ? '' : 's'}`} />
        <Dato etiqueta="Vencimiento" valor={c.vencimiento} />
        <Dato etiqueta="Descripción" valor={c.descripcion} />
      </Tarjeta>
      <Segmentos
        opciones={[
          { valor: 'cobros', etiqueta: 'Cobros', cantidad: c.cobros.length },
          { valor: 'alumnos', etiqueta: 'Alumnos asignados', cantidad: c.alumnos.length },
          { valor: 'recordatorios', etiqueta: 'Recordatorios', cantidad: c.recordatorios.length },
          ...(verAuditoria ? [{ valor: 'historial' as const, etiqueta: 'Historial' }] : []),
        ]}
        valor={pestana}
        onChange={setPestana}
      />
      {pestana === 'cobros' && (
        <>
          {c.cobros.length === 0 && <Tenue>Todavía no hay pagos de esta cuota.</Tenue>}
          {c.cobros.map((p, i) => (
            <ItemLista key={`${p.pago_id}-${i}`} icono="payments" colorIcono={p.anulado ? C.peligro : C.exito} titulo={p.alumno?.nombre ?? 'Alumno'} subtitulo={`${moneda(p.monto)}${p.fecha ? ` · ${p.fecha}` : ''}`}
              derecha={p.anulado ? <Chip texto="Anulado" color={C.peligro} /> : undefined}
              onPress={() => router.push({ pathname: '/pagos/[id]', params: { id: String(p.pago_id) } } as never)} />
          ))}
        </>
      )}
      {pestana === 'alumnos' && (c.alumnos.length === 0
        ? <Tenue>Aplica a todos los alumnos del alcance.</Tenue>
        : c.alumnos.map((a) => <ItemLista key={a.id} icono="school" titulo={a.nombre} onPress={() => router.push({ pathname: '/alumnos/[id]', params: { id: String(a.id) } } as never)} />))}
      {pestana === 'recordatorios' && (c.recordatorios.length === 0
        ? <Tenue>No se enviaron recordatorios por WhatsApp.</Tenue>
        : c.recordatorios.map((r, i) => <ItemLista key={i} icono="chat" titulo={r.alumno ?? 'Alumno'} subtitulo={[r.estado, r.fecha ? new Date(r.fecha).toLocaleString('es-AR') : null].filter(Boolean).join(' · ')} />))}
      {pestana === 'historial' && <HistorialAuditoria entidad="Cuota" id={c.id} />}
    </Pantalla>
  );
}
