import { Stack } from 'expo-router';

import { FormComprobante } from '@/features/comprobantes/FormComprobante';

/** La escuela carga un comprobante en nombre de un alumno. */
export default function CargarComprobante() {
  return (
    <>
      <Stack.Screen options={{ title: 'Cargar comprobante' }} />
      <FormComprobante propio={false} />
    </>
  );
}
