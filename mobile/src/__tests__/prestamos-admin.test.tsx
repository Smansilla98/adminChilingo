import { fireEvent, screen, waitFor } from '@testing-library/react-native';
import { router } from 'expo-router';

import { cuerpoPrestamo, FormDevolver } from '@/features/prestamos/FormPrestamo';
import type { DatosPrestamo } from '@/features/prestamos/tipos';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());

const base: DatosPrestamo = {
  responsable_tipo: 'alumno', alumno_id: 9, persona_id: null, responsable_nombre: '',
  entregado_el: '2026-11-10', devolucion_prevista: '2026-11-24', deposito: '', condicion_entrega: 'bueno', notas: '',
};

it('arma el alta según el responsable elegido y deja vacía la seña si no se cargó', () => {
  expect(cuerpoPrestamo(base)).toEqual({
    alumno_id: 9, persona_id: null, responsable_nombre: null, entregado_el: '2026-11-10', devolucion_prevista: '2026-11-24',
    deposito: null, condicion_entrega: 'bueno', notas: null,
  });
  expect(cuerpoPrestamo({ ...base, responsable_tipo: 'nombre', alumno_id: null, responsable_nombre: ' Juan ', deposito: '2500,5' })).toMatchObject({
    alumno_id: null, responsable_nombre: 'Juan', deposito: 2500.5,
  });
  expect(cuerpoPrestamo({ ...base, responsable_tipo: 'persona', alumno_id: null, persona_id: 4 })).toMatchObject({ persona_id: 4, alumno_id: null });
});

it('la devolución va al préstamo abierto con la condición con que vuelve', async () => {
  const llamadas = simularApi({
    'GET inventario/catalogos': { estados: { bueno: 'Bueno', reparacion: 'En reparación' }, movimientos: {} },
    'POST inventario/prestamos/7/devolucion': { data: { id: 7, estado: 'devuelto' } },
  });
  await renderizar(<FormDevolver prestamoId={7} condicionActual="bueno" deposito={3000} nombreItem="Surdo 22" />);

  await fireEvent.press(await screen.findByLabelText('Registrar devolución'));
  await waitFor(() => expect(router.back).toHaveBeenCalled());
  expect(llamadas.find((l) => l.metodo === 'POST')?.body).toMatchObject({ condicion_devolucion: 'bueno', deposito_devuelto: false });
});
