import { router, Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';

import { FiltrosChips, ItemLista, ListaPaginada } from '@/components/lista';
import { Chip } from '@/components/ui';
import { COLOR_COMPROBANTE, type Comprobante } from '@/features/comprobantes/tipos';
import { usePuede } from '@/lib/permisos';
import { moneda } from '@/lib/theme';

export default function Comprobantes() {
  const params = useLocalSearchParams<{ estado?: string; alumno_id?: string }>();
  const [estado, setEstado] = useState<string | null>(params.estado ?? 'pendiente');
  const puedeCargar = usePuede('comprobantes.create');

  return (
    <>
      <Stack.Screen options={{ title: 'Comprobantes de cuota' }} />
      <ListaPaginada<Comprobante>
        ruta="comprobantes"
        filtros={{ estado, alumno_id: params.alumno_id }}
        buscar
        placeholderBusqueda="Alumno: nombre o DNI"
        vacio={estado === 'pendiente' ? 'No hay comprobantes por revisar.' : 'No hay comprobantes.'}
        iconoVacio="fact-check"
        cabecera={<FiltrosChips opciones={[{ valor: 'pendiente', etiqueta: 'Por revisar' }, { valor: 'visto', etiqueta: 'Vistos' }, { valor: 'pagado', etiqueta: 'Pagados' }]} valor={estado} onChange={setEstado} />}
        onCrear={puedeCargar ? () => router.push('/comprobantes/cargar' as never) : undefined}
        textoCrear="Cargar comprobante"
        render={(c) => (
          <ItemLista
            icono="receipt"
            colorIcono={COLOR_COMPROBANTE[c.estado]}
            titulo={`${c.alumno?.nombre ?? 'Alumno'} · ${moneda(c.monto_total)}`}
            subtitulo={c.items.map((i) => i.cuota ?? i.bloque).filter(Boolean).join(' · ')}
            detalle={[c.fecha_pago ? `Pagó el ${c.fecha_pago}` : null, c.sede].filter(Boolean).join(' · ')}
            derecha={<Chip texto={c.estado_nombre.replace(' de revisión', '')} color={COLOR_COMPROBANTE[c.estado]} />}
            onPress={() => router.push({ pathname: '/comprobantes/[id]', params: { id: String(c.id) } } as never)}
          />
        )}
      />
    </>
  );
}
