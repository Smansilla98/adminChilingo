import { useQuery } from '@tanstack/react-query';
import { router, Stack } from 'expo-router';
import { View } from 'react-native';

import { ItemLista } from '@/components/lista';
import { Boton, Cargando, Chip, ErrorVista, Pantalla, Subtitulo, Tarjeta, Tenue, Texto, Vacio } from '@/components/ui';
import { COLOR_COMPROBANTE, type Comprobante } from '@/features/comprobantes/tipos';
import { EstadoCuentaVista } from '@/features/finanzas/EstadoCuentaVista';
import { api } from '@/lib/api';
import { useMiEstadoCuenta } from '@/lib/queries';
import { E, moneda } from '@/lib/theme';

/** Espacio del alumno: saldo, cuotas, becas, envío de comprobantes y su estado. */
export default function MiCuenta() {
  const q = useMiEstadoCuenta();
  const enviados = useQuery({ queryKey: ['mi', 'comprobantes'], queryFn: () => api<{ data: Comprobante[] }>('mi/comprobantes').then((r) => r.data) });

  if (q.isPending && !q.data) return <Cargando />;
  if (q.isError && !q.data) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  if (!q.data?.length) return <Vacio icono="receipt-long" texto="No tenés inscripciones como alumno." />;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => { void q.refetch(); void enviados.refetch(); }}>
      <Stack.Screen options={{ title: 'Mi cuenta' }} />
      {q.data.map((cuenta) => (
        <View key={cuenta.alumno_id} style={{ gap: E.m }}>
          {cuenta.totales.saldo > 0 && (
            <Boton titulo="Enviar comprobante de pago" icono="upload-file" grande onPress={() => router.push({ pathname: '/comprobantes/enviar', params: { alumno_id: String(cuenta.alumno_id) } } as never)} />
          )}
          <EstadoCuentaVista cuenta={cuenta} />
          {cuenta.becas.length > 0 && (
            <Tarjeta>
              <Texto style={{ fontWeight: '800' }}>Becas</Texto>
              {cuenta.becas.map((b) => <Tenue key={b.id}>{b.etiqueta} · desde {b.desde}{b.hasta ? ` hasta ${b.hasta}` : ''}</Tenue>)}
            </Tarjeta>
          )}
        </View>
      ))}
      <Subtitulo>Comprobantes enviados</Subtitulo>
      {enviados.isPending && <Tenue>Cargando…</Tenue>}
      {enviados.data?.length === 0 && <Tenue>Todavía no enviaste comprobantes.</Tenue>}
      {enviados.data?.map((c) => (
        <ItemLista
          key={c.id}
          icono="receipt"
          colorIcono={COLOR_COMPROBANTE[c.estado]}
          titulo={`${moneda(c.monto_total)} · ${c.items.map((i) => i.cuota).filter(Boolean).join(', ')}`}
          subtitulo={c.enviado_at ? `Enviado el ${new Date(c.enviado_at).toLocaleDateString('es-AR')}` : null}
          derecha={<Chip texto={c.estado_nombre.replace(' de revisión', '')} color={COLOR_COMPROBANTE[c.estado]} />}
          onPress={() => router.push({ pathname: '/comprobantes/[id]', params: { id: String(c.id) } } as never)}
        />
      ))}
    </Pantalla>
  );
}
