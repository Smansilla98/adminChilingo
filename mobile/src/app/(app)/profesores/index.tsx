import { router, Stack } from 'expo-router';
import { useState } from 'react';

import { FiltrosChips, ItemLista, ListaPaginada } from '@/components/lista';
import { Chip } from '@/components/ui';
import { usePuede } from '@/lib/permisos';
import { useCatalogo } from '@/lib/recursos';
import { C } from '@/lib/theme';

interface ProfesorItem {
  id: number;
  nombre: string;
  telefono: string | null;
  activo: boolean;
  cantidad_bloques: number;
  bloques: string[];
  sedes: string[];
  usuario: string | null;
}

export default function Profesores() {
  const [activo, setActivo] = useState<string | null>('1');
  const [sede, setSede] = useState<number | null>(null);
  const sedes = useCatalogo<{ data: { id: number; nombre: string }[] }>('sedes');
  const puedeCrear = usePuede('profesores.create');

  return (
    <>
      <Stack.Screen options={{ title: 'Profesores' }} />
      <ListaPaginada<ProfesorItem>
        ruta="profesores"
        filtros={{ activo, sede_id: sede }}
        buscar
        placeholderBusqueda="Nombre, email o teléfono"
        vacio="No hay docentes en tu alcance."
        iconoVacio="co-present"
        cabecera={
          <>
            <FiltrosChips opciones={[{ valor: '1', etiqueta: 'Activos' }, { valor: '0', etiqueta: 'Inactivos' }]} valor={activo} onChange={setActivo} />
            <FiltrosChips opciones={(sedes.data?.data ?? []).map((s) => ({ valor: s.id, etiqueta: s.nombre }))} valor={sede} onChange={setSede} todos="Todas las sedes" />
          </>
        }
        onCrear={puedeCrear ? () => router.push('/profesores/nuevo' as never) : undefined}
        textoCrear="Nuevo docente"
        render={(p) => (
          <ItemLista
            titulo={p.nombre}
            subtitulo={p.bloques.join(' · ') || 'Sin bloques'}
            detalle={[p.sedes.join(' · '), p.usuario ? `@${p.usuario}` : null].filter(Boolean).join(' · ') || null}
            derecha={!p.activo ? <Chip texto="Inactivo" /> : <Chip texto={`${p.cantidad_bloques} bloque${p.cantidad_bloques === 1 ? '' : 's'}`} color={C.acento} />}
            onPress={() => router.push({ pathname: '/profesores/[id]', params: { id: String(p.id) } } as never)}
          />
        )}
      />
    </>
  );
}
