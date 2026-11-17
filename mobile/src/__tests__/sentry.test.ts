import * as Sentry from '@sentry/react-native';

jest.mock('@sentry/react-native', () => ({
  init: jest.fn(),
  wrap: jest.fn((c: unknown) => c),
}));

type Modulo = typeof import('@/lib/sentry');

/** Carga el módulo con (o sin) DSN, como lo ve el build. */
function cargar(dsn: string | undefined): Modulo {
  const previo = process.env.EXPO_PUBLIC_SENTRY_DSN;
  if (dsn === undefined) delete process.env.EXPO_PUBLIC_SENTRY_DSN;
  else process.env.EXPO_PUBLIC_SENTRY_DSN = dsn;
  let modulo!: Modulo;
  jest.isolateModules(() => {
    modulo = require('@/lib/sentry');
  });
  if (previo === undefined) delete process.env.EXPO_PUBLIC_SENTRY_DSN;
  else process.env.EXPO_PUBLIC_SENTRY_DSN = previo;
  return modulo;
}

beforeEach(() => jest.clearAllMocks());

it('sin DSN no inicia Sentry ni envuelve la raíz', () => {
  const m = cargar(undefined);
  expect(m.sentryActivo()).toBe(false);
  m.iniciarSentry();
  expect(Sentry.init).not.toHaveBeenCalled();
  const raiz = () => null;
  expect(m.envolverRaiz(raiz)).toBe(raiz);
});

it('con DSN inicia Sentry sin PII y envuelve la raíz', () => {
  const m = cargar('https://clave@o1.ingest.sentry.io/1');
  m.iniciarSentry();
  m.iniciarSentry(); // dos veces no inicia dos veces
  expect(Sentry.init).toHaveBeenCalledTimes(1);
  expect(Sentry.init).toHaveBeenCalledWith(expect.objectContaining({ dsn: 'https://clave@o1.ingest.sentry.io/1', sendDefaultPii: false, tracesSampleRate: 0 }));
  const raiz = () => null;
  m.envolverRaiz(raiz);
  expect(Sentry.wrap).toHaveBeenCalledWith(raiz);
});

it('redacta emails, teléfonos y DNI de los textos', () => {
  const m = cargar('https://x@y.io/1');
  expect(m.redactar('Mail ana.perez@gmail.com, DNI 30123456')).toBe('Mail [email], DNI [dni]');
  expect(m.redactar('Tel +54 11 1234-5678 llamar')).toBe('Tel [número] llamar');
  expect(m.redactar('Alumno 12 sin cambios')).toBe('Alumno 12 sin cambios');
});

it('al enviar un evento quita usuario, cabeceras y datos personales de los mensajes', () => {
  const m = cargar('https://x@y.io/1');
  const limpio = m.limpiarEvento({
    message: 'Falló con DNI 30123456',
    user: { email: 'a@b.c', ip_address: '1.2.3.4' },
    request: { url: 'https://admin/x?q=ana', headers: { Authorization: 'Bearer t' } },
    exception: { values: [{ value: 'Mail a@b.c' }] },
    contexts: { app: { app_name: 'ITO' } },
  } as unknown as Sentry.ErrorEvent);
  expect(limpio).not.toBeNull();
  expect(limpio!.user).toBeUndefined();
  expect(limpio!.request).toBeUndefined();
  expect(limpio!.message).toBe('Falló con DNI [dni]');
  expect(limpio!.exception!.values![0].value).toBe('Mail [email]');
  expect(limpio!.contexts).toMatchObject({ app: { app_name: 'ITO' } });
});

it('los breadcrumbs no guardan consola ni cuerpos y las URLs pierden su query', () => {
  const m = cargar('https://x@y.io/1');
  expect(m.limpiarBreadcrumb({ category: 'console', message: 'log' } as never)).toBeNull();
  const b = m.limpiarBreadcrumb({
    category: 'http',
    message: 'GET alumnos',
    data: { url: 'https://admin/api/v1/alumnos?q=Ana%20Perez', method: 'GET', status_code: 200, request_body: { dni: 1 }, response_body: { x: 1 } },
  } as never);
  expect(b!.data).toEqual({ url: 'https://admin/api/v1/alumnos', method: 'GET', status_code: 200 });
});
