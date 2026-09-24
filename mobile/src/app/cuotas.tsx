import * as WebBrowser from 'expo-web-browser';
import { View } from 'react-native';

import { Boton, Cargando, Chip, ErrorVista, Fila, Pantalla, Subtitulo, Tarjeta, Tenue, Texto, Vacio } from '@/components/ui';
import { urlWeb } from '@/lib/modulos';
import { useMiEstadoCuenta } from '@/lib/queries';
import { C, moneda } from '@/lib/theme';
import { ESTADO_CUOTA } from '@/lib/formato';

export default function MisCuotas() {
  const q = useMiEstadoCuenta();

  if (q.isPending && !q.data) return <Cargando />;
  if (q.isError && !q.data) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  if (!q.data?.length) return <Vacio icono="receipt-long" texto="No tenés inscripciones como alumno." />;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      {q.data.map((cuenta) => (
        <View key={cuenta.alumno_id} style={{ gap: 12 }}>
          <Tarjeta acento={cuenta.totales.saldo > 0 ? C.alerta : C.exito}>
            <Tenue>Saldo {cuenta.anio}</Tenue>
            <Texto style={{ fontSize: 30, fontWeight: '800' }}>{moneda(cuenta.totales.saldo)}</Texto>
            <Tenue>Pagado {moneda(cuenta.totales.pagado)} de {moneda(cuenta.totales.neto)}{cuenta.totales.descuento > 0 ? ` · beca ${moneda(cuenta.totales.descuento)}` : ''}</Tenue>
            {cuenta.totales.saldo > 0 && (
              <Boton titulo="Enviar comprobante de pago" icono="upload-file" onPress={() => WebBrowser.openBrowserAsync(urlWeb('/pagar-cuota/comprobante'))} />
            )}
          </Tarjeta>

          {cuenta.becas.length > 0 && (
            <Tarjeta>
              <Texto style={{ fontWeight: '800' }}>Becas</Texto>
              {cuenta.becas.map((b) => <Tenue key={b.id}>{b.etiqueta} · desde {b.desde}{b.hasta ? ` hasta ${b.hasta}` : ''}</Tenue>)}
            </Tarjeta>
          )}

          <Subtitulo>Cuotas</Subtitulo>
          {cuenta.items.length === 0 && <Tenue>No hay cuotas cargadas este año.</Tenue>}
          {cuenta.items.map((i) => (
            <Tarjeta key={i.cuota_id} acento={ESTADO_CUOTA[i.estado].color}>
              <Fila style={{ justifyContent: 'space-between' }}>
                <Texto style={{ fontWeight: '700', flex: 1 }}>{i.nombre}</Texto>
                <Chip texto={ESTADO_CUOTA[i.estado].texto} color={ESTADO_CUOTA[i.estado].color} />
              </Fila>
              <Tenue>{moneda(i.neto)}{i.descuento > 0 ? ` (antes ${moneda(i.bruto)})` : ''}{i.vencimiento ? ` · vence ${i.vencimiento}` : ''}</Tenue>
              {i.saldo > 0 && i.pagado > 0 && <Tenue>Pagaste {moneda(i.pagado)} · faltan {moneda(i.saldo)}</Tenue>}
            </Tarjeta>
          ))}
        </View>
      ))}
    </Pantalla>
  );
}
