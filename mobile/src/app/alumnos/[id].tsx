import { useQuery } from '@tanstack/react-query';
import { Stack, useLocalSearchParams } from 'expo-router';
import { Linking } from 'react-native';

import { Boton, Cargando, Chip, ErrorVista, Fila, Pantalla, Subtitulo, Tarjeta, Tenue, Texto, Titulo } from '@/components/ui';
import { api, ApiError } from '@/lib/api';
import { ESTADO_CUOTA } from '@/lib/formato';
import { C, moneda } from '@/lib/theme';
import type { EstadoCuenta } from '@/lib/types';


interface Alumno {
  id: number;
  nombre: string;
  telefono?: string | null;
  dni?: string;
  instrumento: string | null;
  sede: { nombre: string } | null;
  bloques: { id: number; nombre: string }[];
}

export default function AlumnoDetalle() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const alumno = useQuery({ queryKey: ['alumno', id], queryFn: () => api<{ data: Alumno }>(`alumnos/${id}`).then((r) => r.data) });
  // El estado de cuenta solo se muestra si tiene permiso financiero sobre el alumno.
  const cuenta = useQuery({
    queryKey: ['alumno', id, 'cuenta'],
    queryFn: () => api<EstadoCuenta>(`alumnos/${id}/estado-cuenta`),
    retry: false,
  });

  if (alumno.isPending && !alumno.data) return <Cargando />;
  if (alumno.isError && !alumno.data) return <ErrorVista error={alumno.error} onReintentar={() => alumno.refetch()} />;
  const a = alumno.data!;
  const sinPermisoCuenta = cuenta.error instanceof ApiError && cuenta.error.status === 403;

  return (
    <Pantalla refrescando={alumno.isRefetching} onRefrescar={() => { void alumno.refetch(); void cuenta.refetch(); }}>
      <Stack.Screen options={{ title: a.nombre }} />
      <Titulo>{a.nombre}</Titulo>
      <Tenue>{[a.sede?.nombre, a.instrumento, a.dni ? `DNI ${a.dni}` : null].filter(Boolean).join(' · ')}</Tenue>
      <Fila style={{ flexWrap: 'wrap' }}>{a.bloques.map((b) => <Chip key={b.id} texto={b.nombre} />)}</Fila>
      {a.telefono && <Boton titulo={`Llamar ${a.telefono}`} icono="call" variante="secundario" onPress={() => Linking.openURL(`tel:${a.telefono}`)} />}

      {cuenta.data && (
        <>
          <Subtitulo>Cuotas {cuenta.data.anio}</Subtitulo>
          <Tarjeta acento={cuenta.data.totales.saldo > 0 ? C.alerta : C.exito}>
            <Texto style={{ fontWeight: '800', fontSize: 22 }}>Saldo {moneda(cuenta.data.totales.saldo)}</Texto>
            <Tenue>Vencido {moneda(cuenta.data.totales.vencido)} · pagado {moneda(cuenta.data.totales.pagado)}</Tenue>
          </Tarjeta>
          {cuenta.data.items.map((i) => (
            <Fila key={i.cuota_id} style={{ justifyContent: 'space-between' }}>
              <Texto style={{ flex: 1 }}>{i.periodo} · {moneda(i.neto)}</Texto>
              <Chip texto={ESTADO_CUOTA[i.estado].texto} color={ESTADO_CUOTA[i.estado].color} />
            </Fila>
          ))}
        </>
      )}
      {sinPermisoCuenta && <Tenue>No tenés acceso a la información financiera de este alumno.</Tenue>}
    </Pantalla>
  );
}
