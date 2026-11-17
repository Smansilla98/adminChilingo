import { useQuery } from '@tanstack/react-query';
import { FlatList, RefreshControl, View } from 'react-native';

import { Esqueleto } from '@/components/lista';
import { Aviso, Chip, ErrorVista, Fila, Tarjeta, Tenue, Texto, Vacio } from '@/components/ui';
import { api, ApiError } from '@/lib/api';
import { C, E } from '@/lib/theme';

export interface CicloLectivo {
  id: number;
  anio: number;
  nombre: string | null;
  inicio: string;
  fin: string;
  cerrado: boolean;
}

export interface ActividadMes {
  tipo: string;
  etiqueta: string;
}

export interface MesCalendario {
  anio: number;
  mes: number;
  nombre: string;
  actividades: ActividadMes[];
  cuotas: boolean;
}

/** Color de cada tipo de actividad (los badges). Lo que no esté acá se ve como clases. */
export const COLOR_ACTIVIDAD: Record<string, string> = {
  clases: C.info,
  taller_verano: C.exito,
  gira_gesell: C.acento,
};

/** El ciclo que se muestra: el primero abierto (no cerrado); si no hay, el más reciente. */
export function cicloVigente(ciclos: CicloLectivo[]): CicloLectivo | null {
  return ciclos.find((c) => !c.cerrado) ?? ciclos[0] ?? null;
}

export const useCiclos = () =>
  useQuery({ queryKey: ['ciclo', 'lista'], queryFn: () => api<{ data: CicloLectivo[] }>('ciclos-lectivos').then((r) => r.data) });

export const useCalendarioCiclo = (cicloId: number | null) =>
  useQuery({
    queryKey: ['ciclo', 'calendario', cicloId],
    queryFn: () => api<{ data: MesCalendario[] }>(`ciclos-lectivos/${cicloId}/calendario`).then((r) => r.data),
    enabled: cicloId !== null,
  });

/** Calendario académico del ciclo: cada mes con sus actividades (taller de verano, gira, clases). */
export function CalendarioCiclo() {
  const ciclos = useCiclos();
  const ciclo = ciclos.data ? cicloVigente(ciclos.data) : null;
  const meses = useCalendarioCiclo(ciclo?.id ?? null);

  if (ciclos.isPending) return <Esqueleto />;
  if (ciclos.isError) {
    const sinPermiso = ciclos.error instanceof ApiError && ciclos.error.sinPermiso;
    return sinPermiso ? (
      <Vacio icono="lock" texto="No tenés acceso al calendario académico. Consultá en la escuela." />
    ) : (
      <ErrorVista error={ciclos.error} onReintentar={() => void ciclos.refetch()} />
    );
  }
  if (!ciclo) return <Vacio icono="event-busy" texto="Todavía no hay un ciclo lectivo cargado." />;

  const cabecera = (
    <Tarjeta acento={C.acento}>
      <Tenue>Calendario académico</Tenue>
      <Texto style={{ fontSize: 24, fontWeight: '800' }}>{ciclo.nombre ?? `Ciclo ${ciclo.anio}`}</Texto>
      <Tenue>{ciclo.cerrado ? 'Ciclo cerrado' : 'Ciclo en curso'}</Tenue>
      <Leyenda />
    </Tarjeta>
  );

  return (
    <FlatList
      style={{ flex: 1, backgroundColor: C.fondo }}
      data={meses.data ?? []}
      keyExtractor={(m) => `${m.anio}-${m.mes}`}
      contentContainerStyle={{ padding: E.l, gap: E.s, paddingBottom: E.xxl * 2 }}
      refreshControl={<RefreshControl refreshing={meses.isRefetching} onRefresh={() => void meses.refetch()} tintColor={C.acento} />}
      ListHeaderComponent={<View style={{ marginBottom: E.s }}>{cabecera}</View>}
      ListEmptyComponent={
        meses.isPending ? <Esqueleto /> : meses.isError ? <ErrorVista error={meses.error} onReintentar={() => void meses.refetch()} /> : <Vacio icono="event" texto="Este ciclo todavía no tiene meses calendarizados." />
      }
      renderItem={({ item: m }) => <MesCard mes={m} />}
    />
  );
}

function Leyenda() {
  return (
    <Fila style={{ flexWrap: 'wrap', marginTop: E.s }}>
      {Object.entries({ taller_verano: 'Taller de verano', gira_gesell: 'Gira a Villa Gesell', clases: 'Clases' }).map(([tipo, etiqueta]) => (
        <Chip key={tipo} texto={etiqueta} color={COLOR_ACTIVIDAD[tipo]} />
      ))}
    </Fila>
  );
}

export function MesCard({ mes }: { mes: MesCalendario }) {
  return (
    <View accessible accessibilityLabel={`${mes.nombre}: ${mes.actividades.map((a) => a.etiqueta).join(', ')}`}>
    <Tarjeta>
      <Texto style={{ fontWeight: '800', fontSize: 17 }}>{mes.nombre}</Texto>
      <Fila style={{ flexWrap: 'wrap' }}>
        {mes.actividades.map((a) => (
          <Chip key={a.tipo} texto={a.etiqueta} color={COLOR_ACTIVIDAD[a.tipo] ?? C.tenue} fondo={C.superficie2} />
        ))}
        {mes.cuotas && <Chip texto="Cuota mensual" color={C.tenue} fondo={C.superficie2} />}
      </Fila>
      {mes.actividades.length > 1 && <Aviso tono="info" texto="Actividades en paralelo: se pueden cursar a la vez." />}
    </Tarjeta>
    </View>
  );
}
