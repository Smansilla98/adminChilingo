import { router, Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';

import { DialogoTexto } from '@/components/feedback';
import { Progreso } from '@/components/form';
import { ItemLista } from '@/components/lista';
import { Acciones, Aviso, Boton, Cargando, Chip, Dato, Encabezado, ErrorVista, Pantalla, Segmentos, Subtitulo, Tarjeta, Tenue } from '@/components/ui';
import { HistorialAuditoria } from '@/features/auditoria/HistorialAuditoria';
import type { Pago } from '@/features/finanzas/tipos';
import { api } from '@/lib/api';
import { descargarYAbrir } from '@/lib/archivos';
import { usePuede } from '@/lib/permisos';
import { useDetalle, useOperacion } from '@/lib/recursos';
import { C, moneda } from '@/lib/theme';

export default function FichaPago() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<Pago>('pagos', id);
  const verAuditoria = usePuede('auditoria.view');
  const [anulando, setAnulando] = useState(false);
  const [pestana, setPestana] = useState<'detalle' | 'historial'>('detalle');
  const [descarga, setDescarga] = useState<number | null>(null);
  const [errorDescarga, setErrorDescarga] = useState<string | null>(null);
  const anular = useOperacion((motivo: string) => api(`pagos/${id}/anular`, { method: 'POST', body: { motivo } }), {
    exito: 'Pago anulado. Ya no cuenta para saldos ni reportes',
    invalidar: ['pagos', 'cuotas', 'alumnos', 'personas', 'comprobantes'],
    alTerminar: () => setAnulando(false),
  });

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const p = q.data;

  const verComprobante = async () => {
    setErrorDescarga(null);
    setDescarga(0);
    try {
      await descargarYAbrir(`pagos/${p.id}/comprobante`, { nombre: `comprobante-pago-${p.id}`, onProgreso: setDescarga });
    } catch (e) {
      setErrorDescarga(e instanceof Error ? e.message : 'No se pudo abrir el comprobante.');
    } finally {
      setDescarga(null);
    }
  };

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: `Pago #${p.id}` }} />
      <Encabezado icono="payments" titulo={moneda(p.monto_total)} subtitulo={`${p.fecha}${p.registrado_por ? ` · registró ${p.registrado_por}` : ''}`}
        chips={p.anulado ? <Chip texto="Anulado" color={C.peligro} /> : <Chip texto="Vigente" color={C.exito} />} />
      {p.anulado && <Aviso tono="peligro" texto={`Anulado${p.anulado_por ? ` por ${p.anulado_por}` : ''}: ${p.motivo_anulacion ?? ''}`} />}
      <Acciones>
        {p.tiene_comprobante && <Boton titulo="Ver comprobante" icono="description" variante="secundario" cargando={descarga !== null} onPress={() => void verComprobante()} />}
        {p.acciones?.editar && <Boton titulo="Editar" icono="edit" variante="secundario" onPress={() => router.push({ pathname: '/pagos/[id]/editar', params: { id: String(p.id) } } as never)} />}
        {p.acciones?.anular && <Boton titulo="Anular" icono="block" variante="peligro" onPress={() => setAnulando(true)} />}
      </Acciones>
      {descarga !== null && <Progreso fraccion={descarga} texto="Descargando…" />}
      {errorDescarga && <Aviso tono="peligro" texto={errorDescarga} />}
      {verAuditoria && <Segmentos opciones={[{ valor: 'detalle', etiqueta: 'Detalle' }, { valor: 'historial', etiqueta: 'Historial' }]} valor={pestana} onChange={setPestana} />}
      {pestana === 'detalle' && (
        <>
          <Subtitulo>Líneas</Subtitulo>
          {p.detalles.map((d, i) => (
            <ItemLista
              key={d.id ?? i}
              icono="school"
              titulo={`${d.alumno ?? 'Alumno'} · ${moneda(d.monto)}`}
              subtitulo={d.cuota}
              detalle={d.abono_profesor != null ? `Abono docente ${moneda(d.abono_profesor)}` : null}
              onPress={d.persona_id ? () => router.push({ pathname: '/personas/[id]', params: { id: String(d.persona_id) } } as never) : undefined}
            />
          ))}
          <Tarjeta>
            <Dato etiqueta="Total abono docente" valor={p.total_abono_profesor ? moneda(p.total_abono_profesor) : null} />
            <Dato etiqueta="Notas" valor={p.notas} />
          </Tarjeta>
          {!p.tiene_comprobante && <Tenue>Sin comprobante adjunto.</Tenue>}
        </>
      )}
      {pestana === 'historial' && <HistorialAuditoria entidad="Pago" id={p.id} />}
      <DialogoTexto
        visible={anulando}
        titulo="¿Anular este pago?"
        mensaje="Deja de contar para saldos y reportes, pero queda en el historial. Esta acción queda registrada en auditoría."
        etiqueta="Motivo de la anulación"
        minimo={5}
        accion="Anular"
        destructiva
        cargando={anular.isPending}
        error={anular.isError ? (anular.error as Error).message : null}
        onConfirmar={(motivo) => anular.mutate(motivo)}
        onCerrar={() => setAnulando(false)}
      />
    </Pantalla>
  );
}
