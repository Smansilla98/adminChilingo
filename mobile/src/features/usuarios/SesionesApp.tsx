import { Pressable } from 'react-native';

import { confirmar } from '@/components/feedback';
import { ItemLista } from '@/components/lista';
import { Boton, Icon, Tenue } from '@/components/ui';
import { api } from '@/lib/api';
import { useOperacion } from '@/lib/recursos';
import { C } from '@/lib/theme';

import type { Usuario } from './tipos';

const fecha = (iso: string | null) => (iso ? new Date(iso).toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' }) : '—');

/**
 * Sesiones de la app de una cuenta (administración). Cerrar una sesión deja ese celular
 * afuera y deja de recibir avisos; «todas» hace lo mismo en cada celular de la cuenta.
 * Solo se pueden cerrar si quien mira puede editar la cuenta (UserPolicy::update), igual que el backend.
 */
export function SesionesApp({ usuario, puedeCerrar }: { usuario: Pick<Usuario, 'id' | 'nombre' | 'dispositivos'>; puedeCerrar: boolean }) {
  const cerrar = useOperacion(
    (sesion: number | null) => api(sesion ? `usuarios/${usuario.id}/sesiones/${sesion}` : `usuarios/${usuario.id}/sesiones`, { method: 'DELETE' }),
    { exito: (r) => ((r as { cerradas: number }).cerradas === 1 ? 'Sesión cerrada' : 'Sesiones cerradas'), invalidar: ['usuarios'] },
  );

  if (usuario.dispositivos.length === 0) return <Tenue>No tiene sesiones abiertas en la app.</Tenue>;

  return (
    <>
      {usuario.dispositivos.map((d) => (
        <ItemLista
          key={d.id}
          icono="smartphone"
          titulo={d.nombre}
          subtitulo={`Último uso: ${fecha(d.ultimo_uso)}`}
          detalle={`Desde ${fecha(d.desde)}${d.vence ? ` · vence ${fecha(d.vence)}` : ''}`}
          derecha={
            puedeCerrar ? (
              <Pressable
                accessibilityRole="button"
                accessibilityLabel={`Cerrar la sesión ${d.nombre}`}
                hitSlop={10}
                onPress={async () => {
                  if (await confirmar({ titulo: '¿Cerrar esta sesión?', mensaje: `${d.nombre}. Tendrá que volver a ingresar en ese celular. Queda registrado en auditoría.`, accion: 'Cerrar sesión' })) cerrar.mutate(d.id);
                }}>
                <Icon name="logout" size={24} color={C.peligro} />
              </Pressable>
            ) : undefined
          }
        />
      ))}
      {puedeCerrar && (
        <Boton
          titulo="Cerrar todas las sesiones de la app"
          icono="phonelink-erase"
          variante="peligro"
          cargando={cerrar.isPending}
          onPress={async () => {
            if (await confirmar({ titulo: `¿Cerrar todas las sesiones de ${usuario.nombre}?`, mensaje: 'Se cierra la app en todos sus celulares y dejan de llegarle los avisos hasta que vuelva a ingresar. Queda registrado en auditoría.', accion: 'Cerrar todas' })) cerrar.mutate(null);
          }}
        />
      )}
    </>
  );
}
