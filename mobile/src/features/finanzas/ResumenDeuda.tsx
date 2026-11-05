import { View } from 'react-native';

import { Chip, Fila, Tarjeta, Tenue, Texto } from '@/components/ui';
import { ESTADO_CUOTA } from '@/lib/formato';
import { C, E, moneda } from '@/lib/theme';
import type { EstadoCuenta } from '@/lib/types';

const ADEUDADAS = ['vencida', 'pendiente', 'parcial'] as const;

/** «Debés N cuotas»: cuáles, cuánto de cada una y cuánto en total, las vencidas primero. */
export function ResumenDeuda({ cuenta }: { cuenta: EstadoCuenta }) {
  const deuda = cuenta.items
    .filter((i) => (ADEUDADAS as readonly string[]).includes(i.estado) && i.saldo > 0)
    .sort((a, b) => Number(b.estado === 'vencida') - Number(a.estado === 'vencida'));

  if (deuda.length === 0) {
    return (
      <Tarjeta acento={C.exito}>
        <Texto style={{ fontWeight: '800' }}>Estás al día</Texto>
        <Tenue>No tenés cuotas pendientes en {cuenta.anio}.</Tenue>
      </Tarjeta>
    );
  }

  const total = deuda.reduce((s, i) => s + i.saldo, 0);
  const vencidas = deuda.filter((i) => i.estado === 'vencida').length;

  return (
    <Tarjeta acento={vencidas > 0 ? C.peligro : C.alerta}>
      <Texto style={{ fontWeight: '800', fontSize: 18 }} accessibilityRole="header">
        Debés {deuda.length} {deuda.length === 1 ? 'cuota' : 'cuotas'} · {moneda(total)}
      </Texto>
      {vencidas > 0 && <Tenue>{vencidas === 1 ? '1 está vencida' : `${vencidas} están vencidas`}</Tenue>}
      <View style={{ gap: E.xs, marginTop: E.s }}>
        {deuda.map((i) => (
          <Fila key={i.cuota_id} style={{ justifyContent: 'space-between' }}>
            <View style={{ flex: 1 }}>
              <Texto>{i.nombre}</Texto>
              <Tenue>{i.vencimiento ? `Vence ${i.vencimiento}` : 'Sin vencimiento'}{i.pagado > 0 ? ` · pagaste ${moneda(i.pagado)}` : ''}</Tenue>
            </View>
            <View style={{ alignItems: 'flex-end', gap: 2 }}>
              <Texto style={{ fontWeight: '700' }}>{moneda(i.saldo)}</Texto>
              <Chip texto={ESTADO_CUOTA[i.estado].texto} color={ESTADO_CUOTA[i.estado].color} />
            </View>
          </Fila>
        ))}
      </View>
    </Tarjeta>
  );
}
