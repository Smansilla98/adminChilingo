import { useQuery } from '@tanstack/react-query';
import { Stack, useLocalSearchParams } from 'expo-router';

import { Cargando, ErrorVista, Vacio } from '@/components/ui';
import { FormComprobante } from '@/features/comprobantes/FormComprobante';
import { api } from '@/lib/api';
import { useMiEstadoCuenta } from '@/lib/queries';

/** El alumno envía el comprobante de su cuota (reemplaza al formulario web público). */
export default function EnviarComprobante() {
  const { alumno_id } = useLocalSearchParams<{ alumno_id?: string }>();
  const cuentas = useMiEstadoCuenta();
  const id = alumno_id ? Number(alumno_id) : cuentas.data?.[0]?.alumno_id;
  const alumno = useQuery({ queryKey: ['alumnos', 'nombre', id], queryFn: () => api<{ data: { nombre: string } }>(`alumnos/${id}`).then((r) => r.data.nombre), enabled: !!id });

  if (cuentas.isPending || (id && alumno.isPending)) return <Cargando />;
  if (cuentas.isError) return <ErrorVista error={cuentas.error} onReintentar={() => cuentas.refetch()} />;
  if (!id) return <Vacio icono="receipt-long" texto="No tenés inscripciones como alumno." />;
  return (
    <>
      <Stack.Screen options={{ title: 'Enviar comprobante' }} />
      <FormComprobante propio alumnoInicial={{ id, nombre: alumno.data ?? '' }} />
    </>
  );
}
