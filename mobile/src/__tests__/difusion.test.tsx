import { fireEvent, screen, waitFor } from '@testing-library/react-native';
import { router } from 'expo-router';
import { Alert } from 'react-native';

import { FormDifusion } from '@/features/difusion/FormDifusion';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());

const limite = { cantidad: 0, confirmar_desde: 25, maximo: 300, requiere_confirmacion: false, supera_maximo: false };
const catalogo = {
  alcances: { bloques: 'Uno o varios bloques', sedes: 'Una o varias sedes' },
  canales: { interna: 'Bandeja de avisos', push: 'Notificación en el celular', mail: 'Correo', whatsapp: 'WhatsApp' },
  sedes: [{ id: 1, nombre: 'Palomar' }],
  bloques: [{ id: 7, nombre: 'Bloque A', sede: 'Palomar' }],
  shows: [],
  whatsapp: limite,
};
const previa = { cantidad: 12, con_app: 8, con_email: 10, con_telefono: 11, muestra: ['Ana', 'Beto'], whatsapp: limite };

beforeEach(() => jest.clearAllMocks());

it('muestra a cuántos les llega y manda el mensaje al bloque elegido', async () => {
  jest.spyOn(Alert, 'alert').mockImplementation((_t, _m, botones) => botones?.[1]?.onPress?.());
  const llamadas = simularApi({
    'GET difusiones/catalogo': catalogo,
    'POST difusiones/vista-previa': { data: previa },
    'POST difusiones': { data: { id: 9, estado: 'enviada', destinatarios: 12 } },
  });
  await renderizar(<FormDifusion />);

  await fireEvent.press(await screen.findByText('Bloque A · Palomar'));
  await fireEvent.changeText(screen.getByLabelText('Título'), 'Ensayo');
  await fireEvent.changeText(screen.getByLabelText('Mensaje'), 'El sábado ensayamos a las 15');
  await fireEvent.press(screen.getByLabelText('Ver a cuántos les llega'));
  expect(await screen.findByText('Le llega a 12 personas')).toBeTruthy();

  await fireEvent.press(screen.getByLabelText('Mandar mensaje'));
  await waitFor(() => expect(router.replace).toHaveBeenCalledWith({ pathname: '/difusion/[id]', params: { id: '9' } }));
  expect(llamadas.find((l) => l.metodo === 'POST' && l.ruta === 'difusiones')?.body).toMatchObject({
    titulo: 'Ensayo', alcance: 'bloques', bloque_ids: [7], canales: ['interna', 'push'], programada_para: null,
  });
});
