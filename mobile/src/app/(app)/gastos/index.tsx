import { useQuery } from '@tanstack/react-query';
import { router, Stack } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import { CampoFecha } from '@/components/form';
import { FiltrosChips, ItemLista, ListaPaginada } from '@/components/lista';
import { Chip, Fila, Tarjeta, Tenue, Texto } from '@/components/ui';
import type { CatalogoGastos, Gasto } from '@/features/finanzas/FormGasto';
import { api, qs } from '@/lib/api';
import { usePuede } from '@/lib/permisos';
import { useCatalogo } from '@/lib/recursos';
import { C, moneda } from '@/lib/theme';

const COLOR = { pendiente: C.alerta, aprobado: C.exito, rechazado: C.peligro } as const;

export default function Gastos() {
  const [estado, setEstado] = useState<string | null>(null);
  const [tipo, setTipo] = useState<string | null>(null);
  const [sede, setSede] = useState<number | null>(null);
  const [desde, setDesde] = useState('');
  const [hasta, setHasta] = useState('');
  const cat = useCatalogo<CatalogoGastos>('gastos/catalogo', 1);
  const puedeCrear = usePuede('gastos.create');
  const filtros = { estado, tipo, sede_id: sede, desde, hasta };
  // Total del filtro (lo calcula el servidor sobre todo el resultado, no solo la página).
  const total = useQuery({ queryKey: ['gastos', 'total', filtros], queryFn: () => api<{ total_monto: number; meta: { total: number } }>(`gastos${qs({ ...filtros, page: 1 })}`) });

  return (
    <>
      <Stack.Screen options={{ title: 'Gastos' }} />
      <ListaPaginada<Gasto>
        ruta="gastos"
        filtros={filtros}
        buscar
        placeholderBusqueda="Descripción, proveedor o notas"
        vacio="No hay gastos con esos filtros."
        iconoVacio="account-balance-wallet"
        cabecera={
          <>
            {total.data && (
              <Tarjeta acento={C.acento}>
                <Tenue>Total del filtro · {total.data.meta.total} gastos</Tenue>
                <Texto style={{ fontSize: 24, fontWeight: '800' }}>{moneda(total.data.total_monto)}</Texto>
              </Tarjeta>
            )}
            <FiltrosChips opciones={Object.entries(cat.data?.estados ?? {}).map(([valor, etiqueta]) => ({ valor, etiqueta: etiqueta.replace(' de aprobación', '') }))} valor={estado} onChange={setEstado} />
            <FiltrosChips opciones={Object.entries(cat.data?.tipos ?? {}).map(([valor, etiqueta]) => ({ valor, etiqueta }))} valor={tipo} onChange={setTipo} todos="Toda categoría" />
            <FiltrosChips opciones={(cat.data?.sedes ?? []).map((s) => ({ valor: s.id, etiqueta: s.nombre }))} valor={sede} onChange={setSede} todos="Todas las sedes" />
            <Fila style={{ alignItems: 'flex-start' }}>
              <View style={{ flex: 1 }}><CampoFecha etiqueta="Desde" valor={desde} onChange={setDesde} opcional /></View>
              <View style={{ flex: 1 }}><CampoFecha etiqueta="Hasta" valor={hasta} onChange={setHasta} opcional /></View>
            </Fila>
          </>
        }
        onCrear={puedeCrear ? () => router.push('/gastos/nuevo' as never) : undefined}
        textoCrear="Registrar gasto"
        render={(g) => (
          <ItemLista
            icono="account-balance-wallet"
            colorIcono={COLOR[g.estado]}
            titulo={`${moneda(g.monto)} · ${g.tipo_nombre}`}
            subtitulo={[g.fecha, g.descripcion ?? g.subtipo_nombre].filter(Boolean).join(' · ')}
            detalle={[g.sede?.nombre ?? 'Toda la escuela', g.proveedor].filter(Boolean).join(' · ')}
            derecha={g.estado !== 'aprobado' ? <Chip texto={g.estado === 'pendiente' ? 'Pendiente' : 'Rechazado'} color={COLOR[g.estado]} /> : undefined}
            onPress={() => router.push({ pathname: '/gastos/[id]', params: { id: String(g.id) } } as never)}
          />
        )}
      />
    </>
  );
}
