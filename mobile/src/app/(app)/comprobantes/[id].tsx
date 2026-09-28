import { router, Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';

import { confirmar } from '@/components/feedback';
import { Interruptor, Progreso } from '@/components/form';
import { ItemLista } from '@/components/lista';
import { Acciones, Aviso, Boton, Cargando, Chip, Dato, Encabezado, ErrorVista, Pantalla, Subtitulo, Tarjeta } from '@/components/ui';
import { COLOR_COMPROBANTE, type Comprobante } from '@/features/comprobantes/tipos';
import { api } from '@/lib/api';
import { descargarYAbrir } from '@/lib/archivos';
import { useDetalle, useOperacion } from '@/lib/recursos';
import { moneda } from '@/lib/theme';

export default function DetalleComprobante() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<Comprobante>('comprobantes', id);
  const [liquidar, setLiquidar] = useState(true);
  const [descarga, setDescarga] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const invalidar = ['comprobantes', 'pagos', 'alumnos', 'personas', 'cuotas'];
  const visto = useOperacion(() => api(`comprobantes/${id}/visto`, { method: 'POST' }), { exito: 'Marcado como visto (sin registrar pago)', invalidar });
  const aprobar = useOperacion((l: boolean) => api<{ mensaje: string; pago_id: number }>(`comprobantes/${id}/aprobar`, { method: 'POST', body: { liquidar_profesor: l } }), {
    exito: (r) => r.mensaje || 'Pago registrado correctamente',
    invalidar,
    alTerminar: (r) => router.replace({ pathname: '/pagos/[id]', params: { id: String(r.pago_id) } } as never),
  });

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const c = q.data;

  const verArchivo = async () => {
    setError(null);
    setDescarga(0);
    try {
      await descargarYAbrir(`comprobantes/${c.id}/archivo`, { nombre: `comprobante-${c.id}`, onProgreso: setDescarga });
    } catch (e) {
      setError(e instanceof Error ? e.message : 'No se pudo abrir el comprobante.');
    } finally {
      setDescarga(null);
    }
  };

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Comprobante' }} />
      <Encabezado icono="receipt" titulo={moneda(c.monto_total)} subtitulo={`${c.alumno?.nombre ?? ''}${c.fecha_pago ? ` · pagó el ${c.fecha_pago}` : ''}`}
        chips={<Chip texto={c.estado_nombre} color={COLOR_COMPROBANTE[c.estado]} />} />
      <Acciones>
        {c.tiene_archivo && <Boton titulo="Ver comprobante" icono="description" variante="secundario" cargando={descarga !== null} onPress={() => void verArchivo()} />}
        {c.pago_id && <Boton titulo="Ver pago" icono="payments" variante="secundario" onPress={() => router.push({ pathname: '/pagos/[id]', params: { id: String(c.pago_id) } } as never)} />}
        {c.alumno?.persona_id && <Boton titulo="Ficha del alumno" icono="badge" variante="secundario" onPress={() => router.push({ pathname: '/personas/[id]', params: { id: String(c.alumno!.persona_id) } } as never)} />}
        {c.acciones?.marcar_visto && <Boton titulo="Marcar visto" icono="visibility" variante="secundario" cargando={visto.isPending} onPress={() => visto.mutate()} />}
      </Acciones>
      {descarga !== null && <Progreso fraccion={descarga} texto="Descargando…" />}
      {error && <Aviso tono="peligro" texto={error} />}
      <Subtitulo>Cuotas</Subtitulo>
      {c.items.map((i, n) => <ItemLista key={n} icono="receipt-long" titulo={i.cuota ?? 'Cuota'} subtitulo={i.bloque} derecha={<Chip texto={moneda(i.monto)} />} />)}
      <Tarjeta>
        <Dato etiqueta="Sede" valor={c.sede} />
        <Dato etiqueta="Enviado" valor={c.enviado_at ? new Date(c.enviado_at).toLocaleString('es-AR') : null} />
        <Dato etiqueta="Notas" valor={c.notas} />
      </Tarjeta>
      {c.acciones?.aprobar && (
        <Tarjeta>
          <Interruptor etiqueta="Liquidar al docente" ayuda="Registra el abono docente según la regla de la sede." valor={liquidar} onChange={setLiquidar} />
          <Boton titulo="Aprobar y registrar pago" icono="check-circle" grande cargando={aprobar.isPending}
            onPress={async () => {
              if (await confirmar({ titulo: `¿Registrar el pago de ${moneda(c.monto_total)}?`, mensaje: 'Se crea el pago con estas cuotas y el comprobante queda pagado. Queda registrado en auditoría.', accion: 'Registrar pago', destructiva: false })) aprobar.mutate(liquidar);
            }} />
        </Tarjeta>
      )}
    </Pantalla>
  );
}
