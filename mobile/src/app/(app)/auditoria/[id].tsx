import { Stack, useLocalSearchParams } from 'expo-router';
import { View } from 'react-native';

import { Cargando, Chip, Dato, ErrorVista, Pantalla, Subtitulo, Tarjeta, Tenue, Texto, Titulo } from '@/components/ui';
import { COLOR_ACCION, fechaHora, ORIGEN, type RegistroAuditoria } from '@/features/auditoria/HistorialAuditoria';
import { useDetalle } from '@/lib/recursos';
import { C, E } from '@/lib/theme';

const mostrar = (v: unknown) => (v === null || v === undefined || v === '' ? '—' : typeof v === 'object' ? JSON.stringify(v) : String(v));

export default function DetalleAuditoria() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<RegistroAuditoria>('auditoria', id);

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const r = q.data;

  return (
    <Pantalla>
      <Stack.Screen options={{ title: `Registro #${r.id}` }} />
      <Titulo>{r.accion_nombre} · {r.entidad}</Titulo>
      <Chip texto={r.accion_nombre} color={COLOR_ACCION[r.accion] ?? C.tenue} />
      <Tarjeta>
        <Dato etiqueta="Fecha" valor={fechaHora(r.fecha)} />
        <Dato etiqueta="Usuario" valor={r.usuario ? `${r.usuario.nombre} (@${r.usuario.username})` : 'Sistema'} />
        <Dato etiqueta="Origen" valor={r.origen ? ORIGEN[r.origen] ?? r.origen : null} />
        <Dato etiqueta="Registro" valor={r.entidad_id ? `${r.entidad} #${r.entidad_id}` : r.entidad} />
        <Dato etiqueta="IP" valor={r.ip} />
      </Tarjeta>
      <Subtitulo>Cambios</Subtitulo>
      {r.campos.length === 0 && <Tenue>Sin datos de cambios.</Tenue>}
      {r.campos.map((campo) => (
        <Tarjeta key={campo}>
          <Texto style={{ fontWeight: '800' }}>{campo}</Texto>
          <View style={{ gap: E.xs }}>
            {r.accion !== 'created' && <Tenue>Antes: <Texto style={{ color: C.peligro }}>{mostrar(r.antes?.[campo])}</Texto></Tenue>}
            {r.accion !== 'deleted' && <Tenue>Después: <Texto style={{ color: C.exito }}>{mostrar(r.despues?.[campo])}</Texto></Tenue>}
          </View>
        </Tarjeta>
      ))}
    </Pantalla>
  );
}
