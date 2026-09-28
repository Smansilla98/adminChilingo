import { Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import { CampoFecha, SelectorLista } from '@/components/form';
import { FiltrosChips, ListaPaginada } from '@/components/lista';
import { Fila } from '@/components/ui';
import { FilaAuditoria, type RegistroAuditoria } from '@/features/auditoria/HistorialAuditoria';
import { useCatalogo } from '@/lib/recursos';

interface Catalogo { entidades: string[]; acciones: Record<string, string>; origenes: Record<string, string> }

/** Auditoría del sistema: qué cambió, quién, cuándo y desde dónde. Solo lectura. */
export default function Auditoria() {
  const params = useLocalSearchParams<{ entidad?: string; entidad_id?: string }>();
  const catalogo = useCatalogo<Catalogo>('auditoria/catalogo', 1);
  const [entidad, setEntidad] = useState<string | null>(params.entidad ?? null);
  const [accion, setAccion] = useState<string | null>(null);
  const [origen, setOrigen] = useState<string | null>(null);
  const [desde, setDesde] = useState('');
  const [hasta, setHasta] = useState('');

  return (
    <>
      <Stack.Screen options={{ title: 'Auditoría' }} />
      <ListaPaginada<RegistroAuditoria>
        ruta="auditoria"
        filtros={{ entidad, entidad_id: params.entidad_id, accion, origen, desde, hasta }}
        buscar
        placeholderBusqueda="Usuario o entidad"
        vacio="No hay registros con esos filtros."
        iconoVacio="history"
        cabecera={
          <>
            <SelectorLista
              etiqueta="Entidad"
              valor={entidad}
              onChange={(v) => setEntidad(v)}
              opciones={(catalogo.data?.entidades ?? []).map((e) => ({ valor: e, etiqueta: e }))}
              placeholder="Todas"
              permitirVacio
            />
            <FiltrosChips opciones={Object.entries(catalogo.data?.acciones ?? {}).map(([valor, etiqueta]) => ({ valor, etiqueta }))} valor={accion} onChange={setAccion} todos="Toda acción" />
            <FiltrosChips opciones={Object.entries(catalogo.data?.origenes ?? {}).map(([valor, etiqueta]) => ({ valor, etiqueta }))} valor={origen} onChange={setOrigen} todos="Todo origen" />
            <Fila style={{ alignItems: 'flex-start' }}>
              <View style={{ flex: 1 }}><CampoFecha etiqueta="Desde" valor={desde} onChange={setDesde} opcional /></View>
              <View style={{ flex: 1 }}><CampoFecha etiqueta="Hasta" valor={hasta} onChange={setHasta} opcional /></View>
            </Fila>
          </>
        }
        render={(r) => <FilaAuditoria r={r} conEntidad />}
      />
    </>
  );
}
