import { router, Stack } from 'expo-router';
import { View } from 'react-native';

import { ItemLista, ListaPaginada } from '@/components/lista';
import { Boton, Chip, Fila } from '@/components/ui';
import { COLOR_ESTADO_ITEM } from '@/lib/formato';
import { useMe } from '@/lib/queries';
import { C } from '@/lib/theme';
import type { InventarioItem } from '@/lib/types';

/** Inventario completo (paginado en el servidor) con búsqueda y acceso al escáner QR. */
export default function Inventario() {
  const me = useMe();
  const puedeCargar = me.data?.permisos.includes('inventario.create') ?? false;

  return (
    <>
      <Stack.Screen options={{ title: 'Inventario' }} />
      <ListaPaginada<InventarioItem>
        ruta="inventario"
        buscar
        placeholderBusqueda="Buscar por nombre, código o marca"
        iconoVacio="inventory-2"
        vacio="No hay ítems en tu alcance."
        cabecera={
          <Fila>
            <View style={{ flex: 1 }}><Boton titulo="Escanear QR" icono="qr-code-scanner" onPress={() => router.push('/inventario/escanear' as never)} grande /></View>
            {puedeCargar && <View style={{ flex: 1 }}><Boton titulo="Cargar" icono="add" variante="secundario" onPress={() => router.push('/inventario/nuevo' as never)} grande /></View>}
          </Fila>
        }
        render={(i) => (
          <ItemLista
            titulo={i.nombre}
            subtitulo={[i.codigo, i.tipo_nombre, i.sede?.nombre].filter(Boolean).join(' · ') || null}
            derecha={<Chip texto={i.estado_nombre} color={COLOR_ESTADO_ITEM[i.estado] ?? C.tenue} />}
            onPress={() => router.push({ pathname: '/inventario/[id]', params: { id: String(i.id) } } as never)}
          />
        )}
      />
    </>
  );
}
