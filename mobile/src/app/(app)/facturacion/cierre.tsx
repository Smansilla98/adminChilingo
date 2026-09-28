import { useQuery } from '@tanstack/react-query';
import { router, Stack } from 'expo-router';
import { useState } from 'react';

import { FiltrosChips } from '@/components/lista';
import { Cargando, Chip, ErrorVista, Pantalla, Tarjeta, Tenue, Texto } from '@/components/ui';
import { MESES } from '@/features/finanzas/tipos';
import { api, qs } from '@/lib/api';
import { C } from '@/lib/theme';

interface Cierre { mes: number; anio: number; ok: number; total: number; items: { clave: string; titulo: string; ok: boolean | null; detalle: string }[] }

const DESTINO: Record<string, { ruta: string; params?: Record<string, string>; texto: string }> = {
  asistencias: { ruta: '/asistencia', texto: 'Ir a asistencia' },
  comprobantes: { ruta: '/comprobantes', params: { estado: 'pendiente' }, texto: 'Revisar comprobantes' },
  cuotas: { ruta: '/cuotas', texto: 'Ver cuotas' },
  facturacion: { ruta: '/facturacion', texto: 'Abrir facturación' },
};

/** Checklist del cierre de mes de toda la escuela. */
export default function CierreMes() {
  const hoy = new Date();
  const [mes, setMes] = useState(hoy.getMonth() + 1);
  const q = useQuery({ queryKey: ['facturacion', 'cierre', mes, hoy.getFullYear()], queryFn: () => api<Cierre>(`facturacion/cierre-mes${qs({ mes, anio: hoy.getFullYear() })}`) });

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Cierre de mes' }} />
      <FiltrosChips opciones={MESES.map((m, i) => ({ valor: i + 1, etiqueta: m.slice(0, 3) }))} valor={mes} onChange={(v) => setMes(v ?? hoy.getMonth() + 1)} todos={false} />
      {q.isPending && <Cargando />}
      {q.isError && <ErrorVista error={q.error} onReintentar={() => q.refetch()} />}
      {q.data && (
        <>
          <Texto style={{ fontSize: 22, fontWeight: '800' }}>{MESES[q.data.mes - 1]} {q.data.anio}: {q.data.ok} de {q.data.total} controles OK</Texto>
          {q.data.items.map((i) => {
            const d = DESTINO[i.clave];
            return (
              <Tarjeta key={i.clave} acento={i.ok === null ? C.info : i.ok ? C.exito : C.alerta} onPress={d ? () => router.push({ pathname: d.ruta, params: d.params ?? {} } as never) : undefined}>
                <Texto style={{ fontWeight: '800' }}>{i.titulo}</Texto>
                <Tenue>{i.detalle}</Tenue>
                <Chip texto={i.ok === null ? 'Revisar' : i.ok ? 'OK' : 'Pendiente'} color={i.ok === null ? C.info : i.ok ? C.exito : C.alerta} />
                {d && <Tenue style={{ color: C.acento }}>{d.texto} ›</Tenue>}
              </Tarjeta>
            );
          })}
        </>
      )}
    </Pantalla>
  );
}
