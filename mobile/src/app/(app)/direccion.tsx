import { useQuery } from '@tanstack/react-query';
import { Stack } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import { FiltrosChips } from '@/components/lista';
import { Cargando, ErrorVista, Pantalla, Subtitulo, Tarjeta, Tenue, Texto, Vacio } from '@/components/ui';
import { api, qs } from '@/lib/api';
import { C, E, moneda } from '@/lib/theme';

interface Mes { etiqueta: string; emitido: number; cobrado: number; gastos: number; resultado: number; cobranza: number | null; altas: number; bajas: number; asistencia: number | null }
interface Tablero {
  alcance: 'global' | 'sedes';
  sedes: { id: number; nombre: string }[];
  sede_id: number | null;
  actual: Mes;
  totales_12m: { cobrado: number; emitido: number; gastos: number; resultado: number };
  alumnos: { activos: number; altas_12m: number; bajas_12m: number; retencion: number | null };
  morosidad: { total: number; alumnos: number };
  ocupacion: { inscriptos: number; cupo: number; porcentaje: number | null };
  asistencia_promedio: number | null;
  proyeccion: { etiqueta: string; ingresos: number; gastos: number; resultado: number };
  serie: Mes[];
}

const pct = (n: number | null) => (n === null ? '—' : `${n.toLocaleString('es-AR')} %`);

/** Tablero de dirección resumido: los números del mes y los últimos 12 meses (la web tiene los gráficos). */
export default function Direccion() {
  const [sede, setSede] = useState<number | null>(null);
  const q = useQuery({ queryKey: ['direccion', sede], queryFn: () => api<Tablero>(`direccion${qs({ sede_id: sede })}`) });

  if (q.isPending && !q.data) return <Cargando />;
  if (q.isError && !q.data) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const d = q.data!;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Tablero de dirección' }} />
      {d.sedes.length > 1 && (
        <FiltrosChips<number> todos="Todas mis sedes" opciones={d.sedes.map((s) => ({ valor: s.id, etiqueta: s.nombre }))} valor={sede} onChange={(v) => setSede(v ?? null)} />
      )}
      <Tenue>{d.alcance === 'global' ? 'Toda la escuela' : 'Solo tus sedes'} · últimos 12 meses</Tenue>

      <Tarjeta acento={d.actual.resultado >= 0 ? C.exito : C.peligro}>
        <Tenue>Resultado del mes</Tenue>
        <Texto style={{ fontSize: 26, fontWeight: '800' }}>{moneda(d.actual.resultado)}</Texto>
        <Tenue>Cobrado {moneda(d.actual.cobrado)} · gastos {moneda(d.actual.gastos)} · cobranza {pct(d.actual.cobranza)}</Tenue>
      </Tarjeta>

      <View style={{ flexDirection: 'row', gap: E.s }}>
        <Tarjeta>
          <Tenue>Alumnos activos</Tenue>
          <Texto style={{ fontWeight: '800', fontSize: 20 }}>{d.alumnos.activos}</Texto>
          <Tenue>Retención {pct(d.alumnos.retencion)}</Tenue>
        </Tarjeta>
        <Tarjeta>
          <Tenue>Deuda vencida</Tenue>
          <Texto style={{ fontWeight: '800', fontSize: 20 }}>{moneda(d.morosidad.total)}</Texto>
          <Tenue>{d.morosidad.alumnos} alumnos</Tenue>
        </Tarjeta>
      </View>
      <View style={{ flexDirection: 'row', gap: E.s }}>
        <Tarjeta>
          <Tenue>Ocupación</Tenue>
          <Texto style={{ fontWeight: '800', fontSize: 20 }}>{pct(d.ocupacion.porcentaje)}</Texto>
          <Tenue>{d.ocupacion.inscriptos} de {d.ocupacion.cupo || '—'} lugares</Tenue>
        </Tarjeta>
        <Tarjeta>
          <Tenue>Asistencia promedio</Tenue>
          <Texto style={{ fontWeight: '800', fontSize: 20 }}>{pct(d.asistencia_promedio)}</Texto>
        </Tarjeta>
      </View>

      <Tarjeta acento={C.info}>
        <Tenue>Proyección {d.proyeccion.etiqueta} (estimada)</Tenue>
        <Texto style={{ fontWeight: '800' }}>Resultado {moneda(d.proyeccion.resultado)}</Texto>
        <Tenue>Ingresos {moneda(d.proyeccion.ingresos)} · gastos {moneda(d.proyeccion.gastos)}</Tenue>
      </Tarjeta>

      <Subtitulo>Mes a mes</Subtitulo>
      {d.serie.length === 0 && <Vacio icono="insights" texto="Todavía no hay datos." />}
      {[...d.serie].reverse().map((m) => (
        <Tarjeta key={m.etiqueta}>
          <Texto style={{ fontWeight: '800' }}>{m.etiqueta}</Texto>
          <Tenue>Cobrado {moneda(m.cobrado)} de {moneda(m.emitido)} · gastos {moneda(m.gastos)} · resultado {moneda(m.resultado)}</Tenue>
          <Tenue>Altas {m.altas} · bajas {m.bajas} · asistencia {pct(m.asistencia)}</Tenue>
        </Tarjeta>
      ))}
    </Pantalla>
  );
}
