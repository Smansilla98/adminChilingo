import { router, Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import { CampoFecha } from '@/components/form';
import { FiltrosChips, ItemLista, ListaPaginada } from '@/components/lista';
import { Aviso, Boton, Chip, Fila } from '@/components/ui';
import type { Pago } from '@/features/finanzas/tipos';
import { usePuede } from '@/lib/permisos';
import { useMe } from '@/lib/queries';
import { C, moneda } from '@/lib/theme';

export default function Pagos() {
  const params = useLocalSearchParams<{ alumno_id?: string; cuota_id?: string }>();
  const [estado, setEstado] = useState<string | null>(null);
  const [desde, setDesde] = useState('');
  const [hasta, setHasta] = useState('');
  const puedeCrear = usePuede('pagos.create');
  const esDocente = (useMe().data?.funciones ?? []).some((f) => f.rol === 'profesor');

  return (
    <>
      <Stack.Screen options={{ title: 'Pagos' }} />
      <ListaPaginada<Pago>
        ruta="pagos"
        filtros={{ estado, desde, hasta, alumno_id: params.alumno_id, cuota_id: params.cuota_id }}
        buscar
        placeholderBusqueda="Alumno: nombre, DNI o teléfono"
        vacio="No hay pagos con esos filtros."
        iconoVacio="payments"
        cabecera={
          <>
            {(params.alumno_id || params.cuota_id) && <Aviso tono="info" texto={params.alumno_id ? 'Mostrando los pagos de un alumno.' : 'Mostrando los pagos de una cuota.'} />}
            {esDocente && <Boton titulo="Mis alumnos (liquidación docente)" icono="co-present" variante="secundario" onPress={() => router.push('/pagos/docente' as never)} />}
            <FiltrosChips opciones={[{ valor: 'vigentes', etiqueta: 'Vigentes' }, { valor: 'anulados', etiqueta: 'Anulados' }]} valor={estado} onChange={setEstado} />
            <Fila style={{ alignItems: 'flex-start' }}>
              <View style={{ flex: 1 }}><CampoFecha etiqueta="Desde" valor={desde} onChange={setDesde} opcional /></View>
              <View style={{ flex: 1 }}><CampoFecha etiqueta="Hasta" valor={hasta} onChange={setHasta} opcional /></View>
            </Fila>
          </>
        }
        onCrear={puedeCrear ? () => router.push({ pathname: '/pagos/nuevo', params: params.alumno_id ? { alumno_id: params.alumno_id } : params.cuota_id ? { cuota_id: params.cuota_id } : {} } as never) : undefined}
        textoCrear="Registrar pago"
        render={(p) => (
          <ItemLista
            icono="payments"
            colorIcono={p.anulado ? C.peligro : C.exito}
            titulo={moneda(p.monto_total)}
            subtitulo={`${p.fecha} · ${p.detalles.map((d) => d.alumno).filter(Boolean).slice(0, 2).join(', ')}${p.detalles.length > 2 ? ` +${p.detalles.length - 2}` : ''}`}
            detalle={p.detalles.map((d) => d.cuota).filter(Boolean).slice(0, 3).join(' · ')}
            derecha={p.anulado ? <Chip texto="Anulado" color={C.peligro} /> : undefined}
            onPress={() => router.push({ pathname: '/pagos/[id]', params: { id: String(p.id) } } as never)}
          />
        )}
      />
    </>
  );
}
