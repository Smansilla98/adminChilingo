import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Linking } from 'react-native';

import { Boton, Tarjeta, Tenue, Texto } from '@/components/ui';
import { errorRegistroPush, permisoPush, pushDisponible, registrarPush } from '@/lib/push';
import { C } from '@/lib/theme';

/**
 * Si el celular no tiene permiso para mostrar notificaciones, lo explica y ofrece activarlo.
 * Con permiso, registra el celular en el servidor y, si falla, muestra el motivo y permite reintentar.
 */
export function ActivarPush() {
  const qc = useQueryClient();
  const q = useQuery({ queryKey: ['push', 'permiso'], queryFn: permisoPush, enabled: pushDisponible(), staleTime: 0, gcTime: 0 });
  const conPermiso = q.data?.estado === 'granted';
  const registro = useQuery({
    queryKey: ['push', 'registro'],
    queryFn: async () => ({ token: await registrarPush(), error: errorRegistroPush() }),
    enabled: conPermiso,
    staleTime: Infinity,
  });

  if (!q.data) return null;

  if (conPermiso) {
    if (!registro.data || registro.data.token) return null;
    return (
      <Tarjeta acento={C.acento}>
        <Texto style={{ fontWeight: '800' }}>No se pudieron activar las notificaciones</Texto>
        <Tenue>{registro.data.error ?? 'Motivo desconocido.'}</Tenue>
        <Boton titulo="Reintentar" icono="refresh" onPress={() => void registro.refetch()} />
      </Tarjeta>
    );
  }

  const activar = async () => {
    if (q.data.sePuedePreguntar) await registrarPush();
    else await Linking.openSettings();
    void qc.invalidateQueries({ queryKey: ['push'] });
  };

  return (
    <Tarjeta acento={C.acento}>
      <Texto style={{ fontWeight: '800' }}>Activá las notificaciones</Texto>
      <Tenue>Así te llegan al celular los avisos de cuotas nuevas, vencimientos y pagos, aunque no tengas la app abierta.</Tenue>
      <Boton titulo={q.data.sePuedePreguntar ? 'Activar' : 'Abrir ajustes del celular'} icono="notifications-active" onPress={() => void activar()} />
    </Tarjeta>
  );
}
