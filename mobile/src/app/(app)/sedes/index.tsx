import { router, Stack } from 'expo-router';
import { useState } from 'react';

import { FiltrosChips, ItemLista, ListaPaginada } from '@/components/lista';
import { Chip } from '@/components/ui';
import { usePermisos } from '@/lib/permisos';
import { C } from '@/lib/theme';

interface SedeItem { id: number; nombre: string; direccion: string | null; activo: boolean; cantidad_bloques: number; cantidad_alumnos: number }

export default function Sedes() {
  const [activo, setActivo] = useState<string | null>(null);
  // Crear sedes requiere alcance global (decisión de toda la escuela).
  const puedeCrear = usePermisos().puedeGlobal('sedes.manage');

  return (
    <>
      <Stack.Screen options={{ title: 'Sedes' }} />
      <ListaPaginada<SedeItem>
        ruta="sedes"
        filtros={{ gestion: 1, activo }}
        buscar
        placeholderBusqueda="Nombre o dirección"
        vacio="No hay sedes en tu alcance."
        iconoVacio="location-on"
        cabecera={<FiltrosChips opciones={[{ valor: '1', etiqueta: 'Activas' }, { valor: '0', etiqueta: 'Inactivas' }]} valor={activo} onChange={setActivo} />}
        onCrear={puedeCrear ? () => router.push('/sedes/nueva' as never) : undefined}
        textoCrear="Nueva sede"
        render={(s) => (
          <ItemLista
            icono="location-on"
            colorIcono={s.activo ? C.acento : C.tenue}
            titulo={s.nombre}
            subtitulo={s.direccion}
            detalle={`${s.cantidad_bloques} bloques · ${s.cantidad_alumnos} alumnos`}
            derecha={!s.activo ? <Chip texto="Inactiva" /> : undefined}
            onPress={() => router.push({ pathname: '/sedes/[id]', params: { id: String(s.id) } } as never)}
          />
        )}
      />
    </>
  );
}
