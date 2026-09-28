import { useQuery } from '@tanstack/react-query';
import { router, Stack } from 'expo-router';
import { useState } from 'react';

import { FiltrosChips, ItemLista, ListaPaginada } from '@/components/lista';
import { Boton, Chip, Fila, Tarjeta, Tenue, Texto } from '@/components/ui';
import type { CatalogoFacturacion, Facturacion } from '@/features/finanzas/FormFacturacion';
import { api, qs } from '@/lib/api';
import { usePuede } from '@/lib/permisos';
import { useCatalogo } from '@/lib/recursos';
import { C, moneda } from '@/lib/theme';

export default function FacturacionMensual() {
  const anioActual = new Date().getFullYear();
  const [anio, setAnio] = useState<number | null>(anioActual);
  const [sede, setSede] = useState<number | null>(null);
  const cat = useCatalogo<CatalogoFacturacion>('facturacion/catalogo', 1);
  const puedeCargar = usePuede('facturacion.manage');
  const totales = useQuery({ queryKey: ['facturacion', 'totales', anio, sede], queryFn: () => api<{ totales: { facturado: number; previsto: number } }>(`facturacion${qs({ anio, sede_id: sede })}`).then((r) => r.totales) });

  return (
    <>
      <Stack.Screen options={{ title: 'Facturación mensual' }} />
      <ListaPaginada<Facturacion>
        ruta="facturacion"
        filtros={{ anio, sede_id: sede }}
        vacio="No hay facturación cargada."
        iconoVacio="request-quote"
        cabecera={
          <>
            {cat.data?.puede_cierre && <Boton titulo="Cierre de mes" icono="task-alt" variante="secundario" onPress={() => router.push('/facturacion/cierre' as never)} />}
            {totales.data && (
              <Tarjeta acento={C.acento}>
                <Fila style={{ justifyContent: 'space-between' }}>
                  <Tenue>Facturado</Tenue>
                  <Texto style={{ fontWeight: '800', fontSize: 20 }}>{moneda(totales.data.facturado)}</Texto>
                </Fila>
                <Fila style={{ justifyContent: 'space-between' }}>
                  <Tenue>Previsto</Tenue>
                  <Texto>{moneda(totales.data.previsto)}</Texto>
                </Fila>
              </Tarjeta>
            )}
            <FiltrosChips opciones={[anioActual - 1, anioActual].map((a) => ({ valor: a, etiqueta: String(a) }))} valor={anio} onChange={setAnio} todos="Todos los años" />
            <FiltrosChips opciones={(cat.data?.sedes ?? []).map((s) => ({ valor: s.id, etiqueta: s.nombre }))} valor={sede} onChange={setSede} todos="Todas las sedes" />
          </>
        }
        onCrear={puedeCargar ? () => router.push('/facturacion/nueva' as never) : undefined}
        textoCrear="Cargar mes"
        render={(f) => (
          <ItemLista
            icono="request-quote"
            titulo={`${f.mes_nombre} ${f.anio} · ${moneda(f.monto_facturado)}`}
            subtitulo={`${f.sede?.nombre ?? 'Toda la escuela'} · ${f.cantidad_alumnos} alumnos`}
            detalle={f.monto_previsto != null ? `Previsto ${moneda(f.monto_previsto)}` : null}
            derecha={f.diferencia != null ? <Chip texto={`${f.diferencia >= 0 ? '+' : ''}${moneda(f.diferencia)}`} color={f.diferencia >= 0 ? C.exito : C.alerta} /> : undefined}
            onPress={f.puede_editar ? () => router.push({ pathname: '/facturacion/[id]', params: { id: String(f.id) } } as never) : undefined}
          />
        )}
      />
    </>
  );
}
