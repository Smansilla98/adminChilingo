import { Stack, useLocalSearchParams } from 'expo-router';

import { FormPago } from '@/features/finanzas/FormPago';

/** Registrar pago. Con ?alumno_id= ofrece sus cuotas pendientes; con ?cuota_id= la preselecciona. */
export default function NuevoPago() {
  const { alumno_id, cuota_id } = useLocalSearchParams<{ alumno_id?: string; cuota_id?: string }>();
  return (
    <>
      <Stack.Screen options={{ title: 'Registrar pago' }} />
      <FormPago alumnoId={alumno_id ? Number(alumno_id) : undefined} cuotaId={cuota_id ? Number(cuota_id) : undefined} />
    </>
  );
}
