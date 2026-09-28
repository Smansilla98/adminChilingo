import { ApiError, api, configurarSesion, procesarRespuesta, qs } from '@/lib/api';

describe('qs', () => {
  it('omite vacíos y codifica valores', () => {
    expect(qs({ q: 'ana maría', sede_id: undefined, activo: true, vacio: '', nulo: null, page: 2 })).toBe('?q=ana%20mar%C3%ADa&activo=1&page=2');
    expect(qs({})).toBe('');
  });
});

describe('procesarRespuesta', () => {
  it('devuelve el JSON en 2xx', () => {
    expect(procesarRespuesta<{ ok: boolean }>(200, '{"ok":true}')).toEqual({ ok: true });
    expect(procesarRespuesta(204, '')).toBeNull();
  });

  it('422: expone el primer error y los errores por campo', () => {
    try {
      procesarRespuesta(422, JSON.stringify({ message: 'The given data was invalid.', errors: { monto: ['El monto es obligatorio.'], fecha: ['Fecha inválida.'] } }));
      fail('debía lanzar');
    } catch (e) {
      const err = e as ApiError;
      expect(err.esValidacion).toBe(true);
      expect(err.message).toBe('El monto es obligatorio.');
      expect(err.campo('fecha')).toBe('Fecha inválida.');
    }
  });

  it('500: nunca muestra el mensaje técnico del servidor y es reintentable', () => {
    const err = captura(() => procesarRespuesta(500, JSON.stringify({ message: 'SQLSTATE[42S22]: Column not found' })));
    expect(err.message).toBe('El servidor tuvo un problema. Probá de nuevo en un rato.');
    expect(err.reintentable).toBe(true);
  });

  it('403 y 404 usan el mensaje del servidor si es entendible, si no uno propio', () => {
    expect(captura(() => procesarRespuesta(403, '{"message":"No podés registrar gastos en esa sede."}')).message).toBe('No podés registrar gastos en esa sede.');
    expect(captura(() => procesarRespuesta(403, '{"message":"This action is unauthorized."}')).message).toBe('No tenés permiso para hacer esto.');
    expect(captura(() => procesarRespuesta(404, '{"message":"Not Found"}')).message).toMatch(/No encontramos/);
    expect(captura(() => procesarRespuesta(409, '')).message).toMatch(/modificó este dato/);
  });

  it('401 avisa a la sesión para volver al login', () => {
    const onNoAutorizado = jest.fn();
    configurarSesion({ onNoAutorizado });
    const err = captura(() => procesarRespuesta(401, '{"message":"Unauthenticated."}'));
    expect(onNoAutorizado).toHaveBeenCalled();
    expect(err.message).toBe('Tu sesión venció. Ingresá de nuevo.');
  });
});

describe('api', () => {
  it('sin red lanza ApiError de status 0', async () => {
    globalThis.fetch = jest.fn(() => Promise.reject(new TypeError('Network request failed'))) as jest.Mock;
    await expect(api('me')).rejects.toMatchObject({ status: 0, esDeRed: true });
  });

  it('envía token, contexto y JSON', async () => {
    configurarSesion({ token: 'tok', contexto: 'profesor:1' });
    const fetchMock = jest.fn(async () => ({ status: 201, text: async () => '{"data":{"id":3}}' }));
    globalThis.fetch = fetchMock as unknown as typeof fetch;
    await expect(api('gastos', { method: 'POST', body: { monto: 10 } })).resolves.toEqual({ data: { id: 3 } });
    const [, init] = fetchMock.mock.calls[0] as unknown as [string, RequestInit];
    expect(init.headers).toMatchObject({ Authorization: 'Bearer tok', 'X-Contexto': 'profesor:1', 'Content-Type': 'application/json' });
    expect(init.body).toBe('{"monto":10}');
  });
});

function captura(fn: () => unknown): ApiError {
  try {
    fn();
  } catch (e) {
    return e as ApiError;
  }
  throw new Error('no lanzó');
}
