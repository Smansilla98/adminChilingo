import { useQuery } from '@tanstack/react-query';
import { Stack } from 'expo-router';

import { Cargando, Chip, Dato, ErrorVista, Pantalla, Tarjeta, Tenue, Texto, Vacio } from '@/components/ui';
import { api } from '@/lib/api';
import { C } from '@/lib/theme';

interface Plan {
  ratio_objetivo: number;
  parches_base: number;
  sedes: { sede: { id: number; nombre: string }; alumnos: number; sesiones_semana: number; instrumentos_escuela: number; ratio_actual: number | null; tambores_necesarios: number; tambores_faltantes: number; factor_uso: number; parches_sugeridos: number }[];
}

/** Sugerencia de compra por sede según alumnos, carga horaria e instrumentos. */
export default function PlanCompras() {
  const q = useQuery({ queryKey: ['compras', 'plan'], queryFn: () => api<Plan>('compras/plan') });
  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Plan de compras' }} />
      <Tenue>Referencia: {q.data.ratio_objetivo} alumnxs por tambor y {q.data.parches_base} parche por tambor al año, ajustado por uso.</Tenue>
      {q.data.sedes.length === 0 && <Vacio icono="insights" texto="No hay sedes en tu alcance." />}
      {q.data.sedes.map((s) => (
        <Tarjeta key={s.sede.id} acento={s.tambores_faltantes > 0 ? C.alerta : C.exito}>
          <Texto style={{ fontWeight: '800', fontSize: 18 }}>{s.sede.nombre}</Texto>
          {s.tambores_faltantes > 0 ? <Chip texto={`Faltan ${s.tambores_faltantes} tambores`} color={C.alerta} /> : <Chip texto="Instrumentos suficientes" color={C.exito} />}
          <Dato etiqueta="Alumnos activos" valor={String(s.alumnos)} />
          <Dato etiqueta="Clases por semana" valor={String(s.sesiones_semana)} />
          <Dato etiqueta="Instrumentos de la escuela" valor={`${s.instrumentos_escuela}${s.ratio_actual != null ? ` (${s.ratio_actual} alumnxs por tambor)` : ''}`} />
          <Dato etiqueta="Tambores necesarios" valor={String(s.tambores_necesarios)} />
          <Dato etiqueta="Parches sugeridos al año" valor={`${s.parches_sugeridos} (uso ×${s.factor_uso})`} />
        </Tarjeta>
      ))}
    </Pantalla>
  );
}
