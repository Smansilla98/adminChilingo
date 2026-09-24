import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { View } from 'react-native';

import { Boton, Cargando, Chip, ErrorVista, Fila, Pantalla, Tarjeta, Tenue, Texto, Vacio } from '@/components/ui';
import { api } from '@/lib/api';
import { C, moneda } from '@/lib/theme';

interface Pago {
  id: number;
  fecha: string;
  monto_total: number;
  anulado: boolean;
  detalles?: { alumno: string | null; cuota: string | null; monto: number }[];
}
interface Cuota {
  id: number;
  nombre: string;
  monto: number;
  vencimiento: string | null;
  alcance: string;
  sede: string | null;
  bloque: string | null;
  pagos: number;
}

export default function Finanzas() {
  const [vista, setVista] = useState<'pagos' | 'cuotas'>('pagos');
  const pagos = useQuery({ queryKey: ['pagos'], queryFn: () => api<{ data: Pago[] }>('pagos').then((r) => r.data), enabled: vista === 'pagos' });
  const cuotas = useQuery({ queryKey: ['cuotas'], queryFn: () => api<{ data: Cuota[] }>('cuotas').then((r) => r.data), enabled: vista === 'cuotas' });
  const q = vista === 'pagos' ? pagos : cuotas;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Fila>
        <View style={{ flex: 1 }}><Boton titulo="Pagos" variante={vista === 'pagos' ? 'primario' : 'secundario'} onPress={() => setVista('pagos')} /></View>
        <View style={{ flex: 1 }}><Boton titulo="Cuotas" variante={vista === 'cuotas' ? 'primario' : 'secundario'} onPress={() => setVista('cuotas')} /></View>
      </Fila>
      {q.isPending && !q.data && <Cargando />}
      {q.isError && !q.data && <ErrorVista error={q.error} onReintentar={() => q.refetch()} />}

      {vista === 'pagos' && pagos.data?.length === 0 && <Vacio texto="No hay pagos para mostrar." />}
      {vista === 'pagos' && pagos.data?.map((p) => (
        <Tarjeta key={p.id} acento={p.anulado ? C.peligro : C.exito}>
          <Fila style={{ justifyContent: 'space-between' }}>
            <Texto style={{ fontWeight: '800' }}>{moneda(p.monto_total)}</Texto>
            {p.anulado ? <Chip texto="Anulado" color={C.peligro} /> : <Tenue>{p.fecha}</Tenue>}
          </Fila>
          {p.detalles?.slice(0, 3).map((d, i) => <Tenue key={i}>{d.alumno} · {d.cuota} · {moneda(d.monto)}</Tenue>)}
          {(p.detalles?.length ?? 0) > 3 && <Tenue>+{(p.detalles?.length ?? 0) - 3} más</Tenue>}
        </Tarjeta>
      ))}

      {vista === 'cuotas' && cuotas.data?.length === 0 && <Vacio texto="No hay cuotas este año." />}
      {vista === 'cuotas' && cuotas.data?.map((c) => (
        <Tarjeta key={c.id}>
          <Fila style={{ justifyContent: 'space-between' }}>
            <Texto style={{ fontWeight: '700', flex: 1 }}>{c.nombre}</Texto>
            <Texto style={{ fontWeight: '800' }}>{moneda(c.monto)}</Texto>
          </Fila>
          <Tenue>{c.alcance === 'general' ? 'Toda la escuela' : c.sede ?? c.bloque} · {c.pagos} pagos{c.vencimiento ? ` · vence ${c.vencimiento}` : ''}</Tenue>
        </Tarjeta>
      ))}
    </Pantalla>
  );
}
