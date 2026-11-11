import { useQuery } from '@tanstack/react-query';
import { Stack } from 'expo-router';
import { useState } from 'react';

import { Aviso, Boton, Cargando, Chip, ErrorVista, Fila, Pantalla, Tarjeta, Tenue, Texto, Vacio } from '@/components/ui';
import { api } from '@/lib/api';
import { pdfDesdeServidor } from '@/lib/archivos';
import { C, moneda } from '@/lib/theme';

interface Liquidacion {
  id: number;
  numero: string;
  periodo: string;
  estado: 'aprobada' | 'pagada';
  estado_nombre: string;
  modalidad: 'hora' | 'porcentaje';
  horas_total: number;
  monto_horas: number;
  monto_porcentaje: number;
  monto_total: number;
}

/** El docente ve lo que se le liquidó cada mes y descarga el recibo en PDF. */
export default function MisLiquidaciones() {
  const q = useQuery({ queryKey: ['mi', 'liquidaciones'], queryFn: () => api<{ data: Liquidacion[] }>('mi/liquidaciones') });
  const [bajando, setBajando] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  const recibo = async (id: number) => {
    setError(null);
    setBajando(id);
    try {
      await pdfDesdeServidor(`liquidaciones/${id}/recibo`);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'No se pudo armar el recibo.');
    } finally {
      setBajando(null);
    }
  };

  if (q.isPending && !q.data) return <Cargando />;
  if (q.isError && !q.data) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const lista = q.data?.data ?? [];

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Mis liquidaciones' }} />
      {error && <Aviso tono="peligro" texto={error} />}
      {lista.length === 0 && <Vacio icono="request-quote" texto="Todavía no tenés liquidaciones aprobadas." />}
      {lista.map((l) => (
        <Tarjeta key={l.id} acento={l.estado === 'pagada' ? C.exito : C.info}>
          <Fila style={{ justifyContent: 'space-between' }}>
            <Texto style={{ fontWeight: '800', flex: 1 }}>{l.periodo}</Texto>
            <Chip texto={l.estado_nombre} color={l.estado === 'pagada' ? C.exito : C.info} />
          </Fila>
          <Texto style={{ fontSize: 24, fontWeight: '800' }}>{moneda(l.monto_total)}</Texto>
          <Tenue>{l.modalidad === 'hora' ? `Por horas: ${l.horas_total} h dictadas` : 'Por porcentaje de cuotas cobradas'}</Tenue>
          <Tenue>Por horas: {moneda(l.monto_horas)} · Por porcentaje: {moneda(l.monto_porcentaje)}</Tenue>
          <Boton titulo="Recibo en PDF" icono="picture-as-pdf" variante="secundario" cargando={bajando === l.id} deshabilitado={bajando !== null} onPress={() => void recibo(l.id)} />
        </Tarjeta>
      ))}
    </Pantalla>
  );
}
