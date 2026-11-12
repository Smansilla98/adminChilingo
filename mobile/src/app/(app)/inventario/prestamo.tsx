import { Stack, useLocalSearchParams } from 'expo-router';

import { Aviso, Cargando, ErrorVista, Pantalla, Vacio } from '@/components/ui';
import { FormDevolver, FormPrestar } from '@/features/prestamos/FormPrestamo';
import { useInventarioItem } from '@/lib/queries';

/** Prestar un instrumento (modo=prestar) o registrar su devolución (modo=devolver). */
export default function PrestamoInstrumento() {
  const { item, modo } = useLocalSearchParams<{ item: string; modo?: string }>();
  const q = useInventarioItem(Number(item));

  if (q.isPending && !q.data) return <Cargando />;
  if (q.isError && !q.data) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const i = q.data!;
  const titulo = modo === 'devolver' ? 'Registrar devolución' : 'Prestar instrumento';

  if (modo === 'devolver') {
    if (!i.prestado || !i.prestamo_id) {
      return (
        <Pantalla>
          <Stack.Screen options={{ title: titulo }} />
          <Vacio icono="inventory-2" texto="Este instrumento no tiene un préstamo abierto." />
        </Pantalla>
      );
    }
    return (
      <>
        <Stack.Screen options={{ title: titulo }} />
        <FormDevolver prestamoId={i.prestamo_id} condicionActual={i.estado} deposito={i.prestamo_deposito ?? null} nombreItem={i.nombre} />
      </>
    );
  }

  const bloqueado = !!i.es_consumible || i.estado === 'baja';
  return (
    <>
      <Stack.Screen options={{ title: titulo }} />
      {i.prestado && <Pantalla><Aviso tono="peligro" texto={`${i.nombre} está prestado a ${i.prestamo ?? 'alguien'}. Registrá la devolución antes de prestarlo de nuevo.`} /></Pantalla>}
      {!i.prestado && bloqueado && <Pantalla><Aviso tono="peligro" texto="Este ítem no se puede prestar: es consumible o está dado de baja." /></Pantalla>}
      {!i.prestado && !bloqueado && <FormPrestar itemId={i.id} condicionActual={i.estado} nombreItem={i.nombre} />}
    </>
  );
}
