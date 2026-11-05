import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Linking } from 'react-native';

import { Boton, Tarjeta, Tenue, Texto } from '@/components/ui';
import { permisoPush, pushDisponible, registrarPush } from '@/lib/push';
import { C } from '@/lib/theme';

/** Si el celular no tiene permiso para mostrar notificaciones, lo explica y ofrece activarlo. */
export function ActivarPush() {
  const qc = useQueryClient();
  const q = useQuery({ queryKey: ['push', 'permiso'], queryFn: permisoPush, enabled: pushDisponible(), staleTime: 0, gcTime: 0 });

  if (!q.data || q.data.estado === 'granted') return null;

  const activar = async () => {
    if (q.data.sePuedePreguntar) await registrarPush();
    else await Linking.openSettings();
    void qc.invalidateQueries({ queryKey: ['push', 'permiso'] });
  };

  return (
    <Tarjeta acento={C.acento}>
      <Texto style={{ fontWeight: '800' }}>Activá las notificaciones</Texto>
      <Tenue>Así te llegan al celular los avisos de cuotas nuevas, vencimientos y pagos, aunque no tengas la app abierta.</Tenue>
      <Boton titulo={q.data.sePuedePreguntar ? 'Activar' : 'Abrir ajustes del celular'} icono="notifications-active" onPress={() => void activar()} />
    </Tarjeta>
  );
}
