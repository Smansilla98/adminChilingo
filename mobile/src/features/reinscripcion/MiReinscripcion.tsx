import { useQuery } from '@tanstack/react-query';
import { View } from 'react-native';

import { confirmar } from '@/components/feedback';
import { Aviso, Boton, Cargando, Chip, ErrorVista, Pantalla, Subtitulo, Tarjeta, Tenue, Texto, Vacio } from '@/components/ui';
import { api } from '@/lib/api';
import { useOperacion } from '@/lib/recursos';
import { C, E } from '@/lib/theme';

export interface MiReinscripcionRespuesta {
  ciclo: { anio: number; nombre: string | null; inicio: string; fin: string } | null;
  alumnos: { id: number; nombre: string; estado: 'pendiente' | 'confirmada' | 'baja' | null }[];
}

const CLAVE = ['mi', 'reinscripcion'];

export const useMiReinscripcion = () => useQuery({ queryKey: CLAVE, queryFn: () => api<MiReinscripcionRespuesta>('mi/reinscripcion') });

const ESTADO: Record<string, { texto: string; color: string }> = {
  pendiente: { texto: 'Pendiente', color: C.alerta },
  confirmada: { texto: 'Confirmada', color: C.exito },
  baja: { texto: 'Dada de baja', color: C.peligro },
};

const fechaCorta = (iso: string) => {
  const [a, m, d] = iso.split('-').map(Number);
  return new Date(a, m - 1, d).toLocaleDateString('es-AR', { day: 'numeric', month: 'short', year: 'numeric' });
};

/** Reinscripción del ciclo lectivo: ver el estado de cada alumno y confirmar. */
export function MiReinscripcion() {
  const q = useMiReinscripcion();
  const confirmarOp = useOperacion(() => api<{ confirmadas: number }>('mi/reinscripcion', { method: 'POST' }), {
    exito: (r) => (r.confirmadas === 1 ? 'Reinscripción confirmada' : `${r.confirmadas} reinscripciones confirmadas`),
    invalidar: [CLAVE],
  });

  if (q.isPending && !q.data) return <Cargando />;
  if (q.isError && !q.data) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const d = q.data!;

  if (!d.ciclo) {
    return (
      <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
        <Vacio icono="event-busy" texto="Todavía no hay un ciclo lectivo abierto para reinscribirse." />
      </Pantalla>
    );
  }

  const pendientes = d.alumnos.filter((a) => a.estado !== 'confirmada' && a.estado !== 'baja');
  const alumnosSinAlta = d.alumnos.length === 0;

  const confirmarTodos = async () => {
    const nombres = pendientes.map((a) => a.nombre).join(', ');
    if (await confirmar({ titulo: `¿Confirmar la reinscripción ${d.ciclo?.anio}?`, mensaje: `Alumnos: ${nombres}.`, accion: 'Confirmar', destructiva: false })) {
      confirmarOp.mutate();
    }
  };

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Tarjeta acento={C.acento}>
        <Tenue>Ciclo lectivo</Tenue>
        <Texto style={{ fontSize: 24, fontWeight: '800' }}>{d.ciclo.nombre ?? `Ciclo ${d.ciclo.anio}`}</Texto>
        <Tenue>Del {fechaCorta(d.ciclo.inicio)} al {fechaCorta(d.ciclo.fin)}</Tenue>
      </Tarjeta>

      {alumnosSinAlta && <Vacio icono="person-off" texto="No tenés alumnos activos para reinscribir. Consultá en la escuela." />}

      {d.alumnos.length > 0 && (
        <>
          <Subtitulo>Alumnos</Subtitulo>
          {d.alumnos.map((a) => {
            const e = a.estado ? ESTADO[a.estado] : { texto: 'Sin confirmar', color: C.tenue };
            return (
              <Tarjeta key={a.id}>
                <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: E.s }}>
                  <Texto style={{ fontWeight: '700', flex: 1 }}>{a.nombre}</Texto>
                  <Chip texto={e.texto} color={e.color} />
                </View>
              </Tarjeta>
            );
          })}
        </>
      )}

      {pendientes.length > 0 && (
        <Boton titulo={pendientes.length === 1 ? 'Confirmar mi reinscripción' : 'Confirmar reinscripción'} icono="how-to-reg" grande cargando={confirmarOp.isPending} onPress={() => void confirmarTodos()} />
      )}
      {pendientes.length === 0 && d.alumnos.length > 0 && <Aviso tono="exito" texto="Todos tus alumnos están reinscriptos para este ciclo." />}
      <Tenue>Si tenés dudas sobre tu reinscripción, consultá en la escuela.</Tenue>
    </Pantalla>
  );
}
