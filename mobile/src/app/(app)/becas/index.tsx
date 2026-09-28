import { router, Stack } from 'expo-router';
import { useState } from 'react';

import { FiltrosChips, ItemLista, ListaPaginada } from '@/components/lista';
import { Chip } from '@/components/ui';
import { EditarBeca } from '@/features/personas/EditarBeca';
import { type Beca, ESTADOS_BECA } from '@/features/personas/tipos';
import { C } from '@/lib/theme';

const TIPOS = [
  { valor: 'porcentaje', etiqueta: 'Porcentaje' },
  { valor: 'monto_fijo', etiqueta: 'Monto fijo' },
  { valor: 'total', etiqueta: 'Total' },
];

/**
 * Becas del alcance. Se otorgan desde la ficha de la persona (Personas → Becas),
 * porque cada beca se asocia a una ficha de alumno concreta.
 */
export default function Becas() {
  const [estado, setEstado] = useState<string | null>('activa');
  const [tipo, setTipo] = useState<string | null>(null);
  const [editando, setEditando] = useState<Beca | null>(null);

  return (
    <>
      <Stack.Screen options={{ title: 'Becas' }} />
      <ListaPaginada<Beca>
        ruta="becas"
        filtros={{ estado, tipo }}
        buscar
        placeholderBusqueda="Alumno o motivo"
        vacio="No hay becas con esos filtros."
        iconoVacio="volunteer-activism"
        cabecera={
          <>
            <FiltrosChips opciones={[...ESTADOS_BECA]} valor={estado} onChange={setEstado} />
            <FiltrosChips opciones={TIPOS} valor={tipo} onChange={setTipo} todos="Todo tipo" />
          </>
        }
        onCrear={() => router.push('/personas' as never)}
        textoCrear="Otorgar (elegir persona)"
        render={(b) => (
          <ItemLista
            titulo={b.alumno?.nombre ?? 'Alumno'}
            subtitulo={`${b.etiqueta} · desde ${b.desde}${b.hasta ? ` hasta ${b.hasta}` : ''}`}
            detalle={[b.motivo, b.bloque?.nombre ?? b.sede?.nombre, b.otorgada_por ? `otorgó ${b.otorgada_por}` : null].filter(Boolean).join(' · ') || null}
            acento={b.estado === 'activa' ? C.exito : C.tenue}
            derecha={<Chip texto={b.estado_nombre} color={b.estado === 'activa' ? C.exito : b.estado === 'suspendida' ? C.alerta : C.tenue} />}
            onPress={() => (b.puede_gestionar ? setEditando(b) : b.alumno?.persona_id ? router.push({ pathname: '/personas/[id]', params: { id: String(b.alumno.persona_id) } } as never) : undefined)}
          />
        )}
      />
      <EditarBeca beca={editando} onCerrar={() => setEditando(null)} />
    </>
  );
}
