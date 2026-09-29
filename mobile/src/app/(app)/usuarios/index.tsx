import { router, Stack } from 'expo-router';
import { useState } from 'react';

import { FiltrosChips, ItemLista, ListaPaginada } from '@/components/lista';
import { Boton, Chip } from '@/components/ui';
import type { UsuarioResumen } from '@/features/usuarios/tipos';
import { usePermisos } from '@/lib/permisos';
import { C } from '@/lib/theme';

export default function Usuarios() {
  const [estado, setEstado] = useState<string | null>(null);
  const puedeCrear = usePermisos().puedeGlobal('usuarios.create');

  return (
    <>
      <Stack.Screen options={{ title: 'Usuarios y permisos' }} />
      <ListaPaginada<UsuarioResumen>
        ruta="usuarios"
        filtros={{ estado }}
        buscar
        placeholderBusqueda="Nombre, usuario, email o DNI"
        vacio="No hay cuentas con esos filtros."
        iconoVacio="admin-panel-settings"
        cabecera={(
          <>
            <Boton titulo="Qué puede cada perfil" icono="badge" variante="secundario" onPress={() => router.push('/roles' as never)} />
            <FiltrosChips opciones={[{ valor: 'activos', etiqueta: 'Activas' }, { valor: 'inactivos', etiqueta: 'Desactivadas' }]} valor={estado} onChange={setEstado} />
          </>
        )}
        onCrear={puedeCrear ? () => router.push('/usuarios/nuevo' as never) : undefined}
        textoCrear="Nueva cuenta"
        render={(u) => (
          <ItemLista
            icono="account-circle"
            colorIcono={u.activo ? C.acento : C.tenue}
            titulo={u.nombre}
            subtitulo={`@${u.username} · ${u.email}`}
            detalle={u.roles.join(' · ') || 'Sin roles asignados'}
            derecha={!u.activo ? <Chip texto="Desactivada" color={C.peligro} /> : undefined}
            onPress={() => router.push({ pathname: '/usuarios/[id]', params: { id: String(u.id) } } as never)}
          />
        )}
      />
    </>
  );
}
