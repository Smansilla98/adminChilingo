import { fireEvent, screen, waitFor } from '@testing-library/react-native';

import { FichaFoto } from '@/features/archivo/FichaFoto';
import { camposMultipart, cuerpoFoto, valoresIniciales } from '@/features/archivo/FormFoto';
import type { FotoGestion } from '@/features/archivo/tipos';

import { renderizar, simularApi } from './utils';

jest.mock('@/lib/api', () => require('./utils').mockApi());
jest.mock('expo-image', () => ({ Image: () => null }));

const catalogo = {
  tipos: { ensayo: 'Ensayo' }, fuentes: { archivo_personal: 'Archivo personal' }, precisiones: {}, estados: {},
  sedes: [], capitulos: [], acontecimientos: [], max_mb: 40,
  permisos: { aportar: true, gestionar: true, subir: true, moderar: true, publicar: true, capitulos: true },
};

function foto(extra: Partial<FotoGestion> = {}): FotoGestion {
  return {
    id: 5, slug: 'ensayo', titulo: 'Ensayo en Banfield', titulo_original: 'Ensayo en Banfield', anio: 1998, fecha: '1998', descripcion: null, alt: 'Ensayo',
    ancho: 1200, alto: 800, color: '#333', url: null, acontecimiento: null, sede: null,
    imagen: { chica: 'a', media: 'b', grande: 'c', completa: 'd', srcset: null },
    estado: 'pendiente', estado_etiqueta: 'En revisión', contexto: null, tipo: null, fuente: null, lugar: null, aportante: null,
    tipo_clave: null, fuente_clave: null, fuente_detalle: null, fotografo: null, credito: null, licencia: null, precision: 'anio', fecha_iso: null,
    lugar_detalle: { lugar: 'Banfield', ciudad: null, pais: null, latitud: null, longitud: null }, alt_text: null, notas_aportante: null,
    sede_id: null, capitulo_id: null, acontecimiento_id: null, destacada: false, mostrar_aportante: false, aportante_nombre: 'Juan', es_aporte: true,
    notas_revision: null, motivo_rechazo: null, tags: [], personas_editables: [{ persona_id: 9, nombre: 'Dani Buira', detalle: 'al centro' }],
    tecnico: { nombre_original: null, mime: null, bytes: null, ancho: null, alto: null }, revisiones: [],
    acciones: { editar: false, editar_equipo: true, enviar: false, moderar: true, publicar: true, eliminar: true },
    ...extra,
  };
}

beforeEach(() => jest.clearAllMocks());

it('arma el multipart del aporte con personas del sistema y nombres libres', () => {
  const v = { ...valoresIniciales(), anio: '1998', titulo: ' Ensayo ', personas: [{ persona_id: 9, nombre: 'Dani', detalle: 'al centro' }, { persona_id: null, nombre: 'María', detalle: null }] };
  expect(camposMultipart(v)).toMatchObject({
    titulo: 'Ensayo', anio: 1998, 'personas[0][persona_id]': 9, 'personas[0][detalle]': 'al centro', 'personas[1][nombre]': 'María',
  });
  expect(cuerpoFoto(v, false)).not.toHaveProperty('credito');
  expect(cuerpoFoto({ ...v, credito: 'Archivo La Chilinga' }, true)).toMatchObject({ credito: 'Archivo La Chilinga' });
});

it('el equipo modera un aporte: pedir cambios exige texto y lo envía', async () => {
  const llamadas = simularApi({
    'GET archivo/gestion/fotos/5': { data: foto() },
    'GET archivo/catalogo': catalogo,
    'POST archivo/gestion/fotos/5/estado': { data: foto({ estado: 'cambios', estado_etiqueta: 'Cambios solicitados' }) },
  });
  await renderizar(<FichaFoto id="5" modo="equipo" />);

  expect(await screen.findByText('Aprobar y publicar')).toBeTruthy();
  expect(screen.getByText(/Dani Buira \(al centro\)/)).toBeTruthy();
  await fireEvent.press(screen.getByText('Pedir cambios'));
  await fireEvent.changeText(screen.getByLabelText('Qué necesitás'), 'Indicá el año');
  await fireEvent.press(screen.getByText('Enviar pedido'));

  await waitFor(() => expect(llamadas.find((l) => l.metodo === 'POST')?.body).toEqual({ accion: 'cambios', notas: 'Indicá el año' }));
});

it('quien aportó no ve acciones de moderación', async () => {
  simularApi({
    'GET archivo/aportes/5': { data: foto({ estado: 'cambios', estado_etiqueta: 'Cambios solicitados', notas_revision: '¿Dónde fue?', acciones: { editar: true, editar_equipo: false, enviar: true, moderar: false, publicar: false, eliminar: true } }) },
    'GET archivo/catalogo': catalogo,
  });
  await renderizar(<FichaFoto id="5" modo="aporte" />);

  expect(await screen.findByText('Reenviar al archivo')).toBeTruthy();
  expect(screen.getByText(/El equipo del archivo te pide: ¿Dónde fue\?/)).toBeTruthy();
  expect(screen.queryByText('Aprobar y publicar')).toBeNull();
  expect(screen.queryByText('Publicar')).toBeNull();
});
