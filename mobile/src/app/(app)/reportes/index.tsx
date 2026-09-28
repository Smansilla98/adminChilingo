import { useQuery } from '@tanstack/react-query';
import { Stack } from 'expo-router';
import { useState } from 'react';
import { StyleSheet, View } from 'react-native';

import { Progreso } from '@/components/form';
import { FiltrosChips } from '@/components/lista';
import { Acciones, Aviso, Boton, Cargando, Chip, ErrorVista, Fila, Pantalla, Segmentos, Subtitulo, Tarjeta, Tenue, Texto, Vacio } from '@/components/ui';
import { MESES } from '@/features/finanzas/tipos';
import { api, qs } from '@/lib/api';
import { descargarYAbrir, pdfDesdeServidor } from '@/lib/archivos';
import { C, E, moneda } from '@/lib/theme';

interface Reporte {
  mes: number;
  anio: number;
  anios_disponibles: number[];
  alcance: 'global' | 'sedes';
  global: { ingresos: number; gastos: number; resultado: number };
  financiero_sede: { sede: { id: number; nombre: string }; ingresos: number; gastos: number; gastos_detalle: Record<string, number>; resultado: number }[];
  ingresos_profesor: { profesor: { id: number; nombre: string }; alumnos: number; emitido: number; cobrado: number; porcentaje_cobrado: number | null }[];
  actividad_profesor: { profesor: { id: number | null; nombre: string }; clases_dictadas: number; promedio_presentes: number | null; ultimo_bloque: string | null; ultima_fecha: string | null }[];
  alumnos_bloque: { bloque: { id: number; nombre: string }; sede: string | null; profesor: string | null; alumnos: number; ingresos: number }[];
}

type Vista = 'finanzas' | 'profesores' | 'actividad' | 'bloques';

/** Reportes de gestión: los cálculos los hace el servidor; acá se muestran y exportan. */
export default function Reportes() {
  const hoy = new Date();
  const [mes, setMes] = useState(hoy.getMonth() + 1);
  const [anio, setAnio] = useState(hoy.getFullYear());
  const [vista, setVista] = useState<Vista>('finanzas');
  const [exportando, setExportando] = useState<'excel' | 'pdf' | null>(null);
  const [progreso, setProgreso] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const q = useQuery({ queryKey: ['reportes', mes, anio], queryFn: () => api<Reporte>(`reportes${qs({ mes, anio })}`) });

  const exportar = async (tipo: 'excel' | 'pdf') => {
    setError(null);
    setExportando(tipo);
    setProgreso(0);
    try {
      if (tipo === 'excel') await descargarYAbrir(`reportes/excel${qs({ mes, anio })}`, { nombre: `reportes-${anio}-${String(mes).padStart(2, '0')}.xlsx`, onProgreso: setProgreso });
      else await pdfDesdeServidor(`reportes/imprimible${qs({ mes, anio })}`);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'No se pudo exportar.');
    } finally {
      setExportando(null);
    }
  };

  const anios = q.data?.anios_disponibles?.length ? q.data.anios_disponibles : [hoy.getFullYear()];

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Reportes' }} />
      <FiltrosChips opciones={anios.map((a) => ({ valor: a, etiqueta: String(a) }))} valor={anio} onChange={(v) => setAnio(v ?? hoy.getFullYear())} todos={false} />
      <FiltrosChips opciones={MESES.map((m, i) => ({ valor: i + 1, etiqueta: m.slice(0, 3) }))} valor={mes} onChange={(v) => setMes(v ?? hoy.getMonth() + 1)} todos={false} />
      <Acciones>
        <Boton titulo="Excel" icono="table-view" variante="secundario" cargando={exportando === 'excel'} deshabilitado={!!exportando} onPress={() => void exportar('excel')} />
        <Boton titulo="PDF" icono="picture-as-pdf" variante="secundario" cargando={exportando === 'pdf'} deshabilitado={!!exportando} onPress={() => void exportar('pdf')} />
      </Acciones>
      {exportando === 'excel' && <Progreso fraccion={progreso} texto="Descargando…" />}
      {error && <Aviso tono="peligro" texto={error} />}

      {q.isPending && <Cargando />}
      {q.isError && <ErrorVista error={q.error} onReintentar={() => q.refetch()} />}
      {q.data && (
        <>
          {q.data.alcance === 'sedes' && <Tenue>Mostrando solo las sedes de tu alcance.</Tenue>}
          <Tarjeta acento={q.data.global.resultado >= 0 ? C.exito : C.peligro}>
            <Fila style={{ justifyContent: 'space-between' }}><Tenue>Ingresos</Tenue><Texto style={s.monto}>{moneda(q.data.global.ingresos)}</Texto></Fila>
            <Fila style={{ justifyContent: 'space-between' }}><Tenue>Gastos</Tenue><Texto style={s.monto}>{moneda(q.data.global.gastos)}</Texto></Fila>
            <Fila style={{ justifyContent: 'space-between' }}><Texto style={{ fontWeight: '800' }}>Resultado</Texto><Texto style={[s.monto, { color: q.data.global.resultado >= 0 ? C.exito : C.peligro }]}>{moneda(q.data.global.resultado)}</Texto></Fila>
          </Tarjeta>

          <Segmentos
            opciones={[{ valor: 'finanzas', etiqueta: 'Por sede' }, { valor: 'profesores', etiqueta: 'Cobranza por docente' }, { valor: 'actividad', etiqueta: 'Actividad' }, { valor: 'bloques', etiqueta: 'Por bloque' }]}
            valor={vista}
            onChange={setVista}
          />

          {vista === 'finanzas' && (q.data.financiero_sede.length === 0 ? <Vacio icono="bar-chart" texto="Sin datos para el período." /> : q.data.financiero_sede.map((f) => (
            <Tarjeta key={f.sede.id} acento={f.resultado >= 0 ? C.exito : C.alerta}>
              <Texto style={{ fontWeight: '800', fontSize: 17 }}>{f.sede.nombre}</Texto>
              <Barra etiqueta="Ingresos" valor={f.ingresos} maximo={Math.max(f.ingresos, f.gastos)} color={C.exito} />
              <Barra etiqueta="Gastos" valor={f.gastos} maximo={Math.max(f.ingresos, f.gastos)} color={C.peligro} />
              <Tenue>Resultado: {moneda(f.resultado)}</Tenue>
              <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: E.xs }}>
                {Object.entries(f.gastos_detalle).filter(([, v]) => v > 0).map(([k, v]) => <Chip key={k} texto={`${k.replace(/_/g, ' ')}: ${moneda(v)}`} />)}
              </View>
            </Tarjeta>
          )))}

          {vista === 'profesores' && (q.data.ingresos_profesor.length === 0 ? <Vacio icono="bar-chart" texto="No hay cuotas emitidas en el período." /> : q.data.ingresos_profesor.map((p) => (
            <Tarjeta key={p.profesor.id}>
              <Fila style={{ justifyContent: 'space-between' }}>
                <Texto style={{ fontWeight: '800', flex: 1 }}>{p.profesor.nombre}</Texto>
                {p.porcentaje_cobrado != null && <Chip texto={`${p.porcentaje_cobrado}% cobrado`} color={p.porcentaje_cobrado >= 80 ? C.exito : p.porcentaje_cobrado >= 50 ? C.alerta : C.peligro} />}
              </Fila>
              <Barra etiqueta={`Cobrado de ${moneda(p.emitido)}`} valor={p.cobrado} maximo={p.emitido} color={C.acento} />
              <Tenue>{p.alumnos} alumnos</Tenue>
            </Tarjeta>
          )))}

          {vista === 'actividad' && (q.data.actividad_profesor.length === 0 ? <Vacio icono="fact-check" texto="Sin clases registradas en el período." /> : q.data.actividad_profesor.map((a, i) => (
            <Tarjeta key={`${a.profesor.id}-${i}`}>
              <Texto style={{ fontWeight: '800' }}>{a.profesor.nombre}</Texto>
              <Tenue>{a.clases_dictadas} clases · promedio {a.promedio_presentes ?? '—'} presentes</Tenue>
              {a.ultimo_bloque && <Tenue>Última: {a.ultimo_bloque}{a.ultima_fecha ? ` (${a.ultima_fecha})` : ''}</Tenue>}
            </Tarjeta>
          )))}

          {vista === 'bloques' && (
            <>
              <Subtitulo>Alumnos e ingresos por bloque</Subtitulo>
              {q.data.alumnos_bloque.map((b) => (
                <Tarjeta key={b.bloque.id}>
                  <Fila style={{ justifyContent: 'space-between' }}>
                    <Texto style={{ fontWeight: '700', flex: 1 }}>{b.bloque.nombre}</Texto>
                    <Texto style={{ fontWeight: '800' }}>{moneda(b.ingresos)}</Texto>
                  </Fila>
                  <Tenue>{[b.sede, b.profesor, `${b.alumnos} alumnos`].filter(Boolean).join(' · ')}</Tenue>
                </Tarjeta>
              ))}
            </>
          )}
        </>
      )}
    </Pantalla>
  );
}

function Barra({ etiqueta, valor, maximo, color }: { etiqueta: string; valor: number; maximo: number; color: string }) {
  const pct = maximo > 0 ? Math.min(100, Math.round((valor / maximo) * 100)) : 0;
  return (
    <View style={{ gap: 4 }} accessibilityLabel={`${etiqueta}: ${moneda(valor)}`}>
      <Fila style={{ justifyContent: 'space-between' }}>
        <Tenue style={{ fontSize: 13 }}>{etiqueta}</Tenue>
        <Tenue style={{ fontSize: 13 }}>{moneda(valor)}</Tenue>
      </Fila>
      <View style={s.pista}><View style={[s.relleno, { width: `${pct}%`, backgroundColor: color }]} /></View>
    </View>
  );
}

const s = StyleSheet.create({
  monto: { fontWeight: '800', fontSize: 18 },
  pista: { height: 10, borderRadius: 5, backgroundColor: C.superficie2, overflow: 'hidden' },
  relleno: { height: 10, borderRadius: 5 },
});
