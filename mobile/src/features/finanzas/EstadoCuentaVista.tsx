import { View } from 'react-native';

import { Chip, Fila, Tarjeta, Tenue, Texto } from '@/components/ui';
import { ESTADO_CUOTA } from '@/lib/formato';
import { C, E, moneda } from '@/lib/theme';
import type { EstadoCuenta } from '@/lib/types';

/** Saldo y cuotas de un alumno (con becas aplicadas). Lo calcula el backend. */
export function EstadoCuentaVista({ cuenta, onCuota }: { cuenta: EstadoCuenta; onCuota?: (cuotaId: number) => void }) {
  return (
    <View style={{ gap: E.s }}>
      <Tarjeta acento={cuenta.totales.saldo > 0 ? C.alerta : C.exito}>
        <Tenue>Saldo {cuenta.anio}</Tenue>
        <Texto style={{ fontSize: 28, fontWeight: '800' }}>{moneda(cuenta.totales.saldo)}</Texto>
        <Tenue>
          Pagado {moneda(cuenta.totales.pagado)} de {moneda(cuenta.totales.neto)}
          {cuenta.totales.descuento > 0 ? ` · becas ${moneda(cuenta.totales.descuento)}` : ''}
          {cuenta.totales.vencido > 0 ? ` · vencido ${moneda(cuenta.totales.vencido)}` : ''}
        </Tenue>
      </Tarjeta>
      {cuenta.items.length === 0 && <Tenue>No hay cuotas para este año.</Tenue>}
      {cuenta.items.map((i) => (
        <Tarjeta key={i.cuota_id} acento={ESTADO_CUOTA[i.estado].color} onPress={onCuota ? () => onCuota(i.cuota_id) : undefined}>
          <Fila style={{ justifyContent: 'space-between' }}>
            <Texto style={{ fontWeight: '700', flex: 1 }}>{i.nombre}</Texto>
            <Chip texto={ESTADO_CUOTA[i.estado].texto} color={ESTADO_CUOTA[i.estado].color} />
          </Fila>
          <Tenue>
            {moneda(i.neto)}{i.descuento > 0 ? ` (antes ${moneda(i.bruto)} · ${i.beca?.etiqueta ?? 'beca'})` : ''}{i.vencimiento ? ` · vence ${i.vencimiento}` : ''}
          </Tenue>
          {i.saldo > 0 && i.pagado > 0 && <Tenue>Pagado {moneda(i.pagado)} · faltan {moneda(i.saldo)}</Tenue>}
        </Tarjeta>
      ))}
    </View>
  );
}
