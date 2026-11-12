import { useQuery } from '@tanstack/react-query';
import { Stack } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import { confirmar } from '@/components/feedback';
import { FiltrosChips } from '@/components/lista';
import { Aviso, Boton, Cargando, Chip, ErrorVista, Fila, Pantalla, Tarjeta, Tenue, Texto, Vacio } from '@/components/ui';
import { MESES } from '@/features/finanzas/tipos';
import { api, qs } from '@/lib/api';
import { pdfDesdeServidor } from '@/lib/archivos';
import { useOperacion } from '@/lib/recursos';
import { C, E, moneda } from '@/lib/theme';

interface Liquidacion { id: number; numero: string; periodo: string; estado: 'borrador' | 'aprobada' | 'pagada'; estado_nombre: string; modalidad: string; horas_total: number; monto_total: number; monto_horas: number; monto_porcentaje: number }
interface FilaDocente { profesor: { id: number; nombre: string }; modalidad: string; liquidacion: Liquidacion | null }
interface Respuesta { data: FilaDocente[]; anio: number; mes: number; puede_gestionar: boolean }

const COLOR: Record<string, string> = { borrador: C.alerta, aprobada: C.info, pagada: C.exito };
const MODALIDAD: Record<string, string> = { hora: 'Por horas', porcentaje: 'Porcentaje de cuotas' };

/** Gestión de liquidaciones docentes por mes (administración): calcular, aprobar y marcar pagadas. */
export default function LiquidacionesGestion() {
  const hoy = new Date();
  const [anio, setAnio] = useState(hoy.getFullYear());
  const [mes, setMes] = useState(hoy.getMonth() + 1);
  const clave = ['liquidaciones', 'gestion', anio, mes];
  const q = useQuery({ queryKey: clave, queryFn: () => api<Respuesta>(`gestion/liquidaciones${qs({ anio, mes })}`) });

  const calcular = useOperacion((profesorId: number) => api('gestion/liquidaciones/generar', { method: 'POST', body: { anio, mes, profesor_id: profesorId } }), { exito: 'Liquidación calculada. Revisala antes de aprobarla.', invalidar: [clave] });
  const aprobar = useOperacion((id: number) => api(`gestion/liquidaciones/${id}/aprobar`, { method: 'POST' }), { exito: 'Liquidación aprobada. El docente ya la ve en su app.', invalidar: [clave] });
  const pagar = useOperacion((id: number) => api(`gestion/liquidaciones/${id}/pagar`, { method: 'POST', body: { registrar_gasto: true } }), { exito: 'Marcada como pagada. Quedó el gasto de sueldo.', invalidar: [clave] });
  const [error, setError] = useState<string | null>(null);
  const [recibo, setRecibo] = useState<number | null>(null);

  const bajarRecibo = async (id: number) => {
    setError(null);
    setRecibo(id);
    try {
      await pdfDesdeServidor(`liquidaciones/${id}/recibo`);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'No se pudo armar el recibo.');
    } finally {
      setRecibo(null);
    }
  };

  const anios = [hoy.getFullYear(), hoy.getFullYear() - 1];
  if (q.isPending && !q.data) return <Cargando />;
  if (q.isError && !q.data) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const lista = q.data?.data ?? [];
  const gestiona = q.data?.puede_gestionar ?? false;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Liquidación docente' }} />
      <FiltrosChips opciones={anios.map((a) => ({ valor: a, etiqueta: String(a) }))} valor={anio} onChange={(v) => setAnio(v ?? hoy.getFullYear())} todos={false} />
      <FiltrosChips opciones={MESES.map((m, i) => ({ valor: i + 1, etiqueta: m.slice(0, 3) }))} valor={mes} onChange={(v) => setMes(v ?? hoy.getMonth() + 1)} todos={false} />
      {!gestiona && <Tenue>Podés ver las liquidaciones; aprobar y pagar lo hace administración.</Tenue>}
      {error && <Aviso tono="peligro" texto={error} />}

      {lista.length === 0 && <Vacio icono="request-quote" texto="No hay docentes en tu alcance para este mes." />}
      {lista.map((f) => {
        const l = f.liquidacion;
        const ocupado = calcular.isPending || aprobar.isPending || pagar.isPending;
        return (
          <Tarjeta key={f.profesor.id} acento={l ? COLOR[l.estado] : C.tenue}>
            <Fila style={{ justifyContent: 'space-between' }}>
              <Texto style={{ fontWeight: '800', flex: 1 }}>{f.profesor.nombre}</Texto>
              <Chip texto={l ? l.estado_nombre : 'Sin calcular'} color={l ? COLOR[l.estado] : C.tenue} />
            </Fila>
            <Tenue>{MODALIDAD[f.modalidad] ?? f.modalidad}</Tenue>
            {l && (
              <>
                <Texto style={{ fontSize: 22, fontWeight: '800' }}>{moneda(l.monto_total)}</Texto>
                <Tenue>Por horas: {moneda(l.monto_horas)} ({l.horas_total} h) · Por porcentaje: {moneda(l.monto_porcentaje)}</Tenue>
              </>
            )}
            <View style={{ gap: E.s }}>
              {gestiona && (!l || l.estado === 'borrador') && (
                <Boton titulo={l ? 'Recalcular' : 'Calcular'} icono="calculate" variante="secundario" deshabilitado={ocupado} onPress={() => calcular.mutate(f.profesor.id)} />
              )}
              {gestiona && l?.estado === 'borrador' && (
                <Boton titulo="Aprobar" icono="check" deshabilitado={ocupado} onPress={() => aprobar.mutate(l.id)} />
              )}
              {gestiona && l?.estado === 'aprobada' && (
                <Boton
                  titulo="Marcar pagada"
                  icono="payments"
                  deshabilitado={ocupado}
                  onPress={async () => {
                    if (await confirmar({ titulo: `¿Marcar pagada la liquidación de ${f.profesor.nombre}?`, mensaje: 'Se registra el gasto de sueldo en las sedes donde podés cargar gastos.', accion: 'Marcar pagada', destructiva: false })) pagar.mutate(l.id);
                  }}
                />
              )}
              {l && (
                <Boton titulo="Recibo en PDF" icono="picture-as-pdf" variante="secundario" cargando={recibo === l.id} deshabilitado={recibo !== null} onPress={() => void bajarRecibo(l.id)} />
              )}
            </View>
          </Tarjeta>
        );
      })}
    </Pantalla>
  );
}
