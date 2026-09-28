import { router, Stack } from 'expo-router';
import { useState } from 'react';

import { FiltrosChips, ItemLista, ListaPaginada } from '@/components/lista';
import { Chip } from '@/components/ui';
import { ALCANCE_CUOTA, type CuotaResumen, MESES } from '@/features/finanzas/tipos';
import { usePuede } from '@/lib/permisos';
import { C, moneda } from '@/lib/theme';

export default function Cuotas() {
  const hoy = new Date();
  const [anio, setAnio] = useState<number>(hoy.getFullYear());
  const [mes, setMes] = useState<number | null>(null);
  const [alcance, setAlcance] = useState<string | null>(null);
  const [activo, setActivo] = useState<string | null>('1');
  const puedeCrear = usePuede('cuotas.create');

  return (
    <>
      <Stack.Screen options={{ title: 'Cuotas' }} />
      <ListaPaginada<CuotaResumen>
        ruta="cuotas"
        filtros={{ anio, mes, alcance, activo }}
        buscar
        placeholderBusqueda="Nombre de la cuota"
        vacio="No hay cuotas con esos filtros."
        iconoVacio="receipt-long"
        cabecera={
          <>
            <FiltrosChips opciones={[hoy.getFullYear() - 1, hoy.getFullYear(), hoy.getFullYear() + 1].map((a) => ({ valor: a, etiqueta: String(a) }))} valor={anio} onChange={(v) => setAnio(v ?? hoy.getFullYear())} todos={false} />
            <FiltrosChips opciones={MESES.map((m, i) => ({ valor: i + 1, etiqueta: m.slice(0, 3) }))} valor={mes} onChange={setMes} todos="Todo el año" />
            <FiltrosChips opciones={Object.entries(ALCANCE_CUOTA).map(([valor, etiqueta]) => ({ valor, etiqueta }))} valor={alcance} onChange={setAlcance} todos="Todo alcance" />
            <FiltrosChips opciones={[{ valor: '1', etiqueta: 'Activas' }, { valor: '0', etiqueta: 'Inactivas' }]} valor={activo} onChange={setActivo} todos="Todas" />
          </>
        }
        onCrear={puedeCrear ? () => router.push('/cuotas/nueva' as never) : undefined}
        textoCrear="Nueva cuota"
        render={(c) => (
          <ItemLista
            icono="receipt-long"
            colorIcono={c.activo ? C.acento : C.tenue}
            titulo={`${c.nombre} · ${moneda(c.monto)}`}
            subtitulo={`${c.periodo} · ${c.alcance === 'general' ? 'Toda la escuela' : c.sede ?? c.bloque ?? ''}`}
            detalle={`${c.pagos} pago${c.pagos === 1 ? '' : 's'}${c.vencimiento ? ` · vence ${c.vencimiento}` : ''}`}
            derecha={!c.activo ? <Chip texto="Inactiva" /> : c.vencida ? <Chip texto="Vencida" color={C.alerta} /> : undefined}
            onPress={() => router.push({ pathname: '/cuotas/[id]', params: { id: String(c.id) } } as never)}
          />
        )}
      />
    </>
  );
}
