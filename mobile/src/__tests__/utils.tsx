import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render } from '@testing-library/react-native';
import type { ReactElement } from 'react';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { ToastProvider } from '@/components/feedback';

/**
 * Respuestas simuladas de la API por "MÉTODO ruta" (la ruta sin query string).
 * Una función recibe el body y puede lanzar ApiError para simular errores.
 */
export type RespuestasApi = Record<string, unknown | ((body: unknown, ruta: string) => unknown)>;

export function simularApi(respuestas: RespuestasApi) {
  const llamadas: { metodo: string; ruta: string; body: unknown }[] = [];
  const api = jest.requireMock('@/lib/api') as { api: jest.Mock };
  api.api.mockImplementation(async (ruta: string, op: { method?: string; body?: unknown } = {}) => {
    const metodo = op.method ?? 'GET';
    const limpia = ruta.split('?')[0];
    llamadas.push({ metodo, ruta, body: op.body });
    const r = respuestas[`${metodo} ${limpia}`];
    if (r === undefined) throw new Error(`Sin respuesta simulada para ${metodo} ${limpia}`);
    return typeof r === 'function' ? (r as (b: unknown, ruta: string) => unknown)(op.body, ruta) : r;
  });
  return llamadas;
}

export async function renderizar(ui: ReactElement) {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false, gcTime: Infinity }, mutations: { retry: false, gcTime: Infinity } } });
  return await render(
    <SafeAreaProvider initialMetrics={{ frame: { x: 0, y: 0, width: 390, height: 800 }, insets: { top: 0, left: 0, right: 0, bottom: 0 } }}>
      <QueryClientProvider client={qc}>
        <ToastProvider>{ui}</ToastProvider>
      </QueryClientProvider>
    </SafeAreaProvider>,
  );
}

/** Mock parcial de @/lib/api: `api` simulado, el resto (ApiError, qs…) real. */
export function mockApi() {
  const real = jest.requireActual('@/lib/api');
  return { ...real, api: jest.fn() };
}
