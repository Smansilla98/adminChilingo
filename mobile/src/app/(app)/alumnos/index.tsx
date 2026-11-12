import { router, Stack } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import { useToast } from '@/components/feedback';
import { ItemLista, ListaPaginada } from '@/components/lista';
import { Boton } from '@/components/ui';
import { descargarYAbrir } from '@/lib/archivos';
import { usePermisos, usePuede } from '@/lib/permisos';
import { E } from '@/lib/theme';

interface AlumnoFila {
  id: number;
  nombre: string;
  bloques?: { id: number; nombre: string }[];
  sede?: { nombre: string } | null;
}

/** Alumnos de los bloques y sedes de la persona: todos, con scroll infinito y búsqueda en el servidor. */
export default function Alumnos() {
  const puedeCrear = usePuede('alumnos.create');
  const puedeExportar = usePermisos().puede('alumnos.export');
  const puedeImportar = usePermisos().puede('alumnos.import');
  const [exportando, setExportando] = useState(false);
  const avisar = useToast();

  return (
    <>
      <Stack.Screen options={{ title: 'Alumnos' }} />
      <ListaPaginada<AlumnoFila>
        ruta="alumnos"
        buscar
        placeholderBusqueda="Buscar por nombre o DNI"
        iconoVacio="person-search"
        vacio="Sin alumnos en tus bloques y sedes."
        cabecera={(puedeImportar || puedeExportar) && (
          <View style={{ gap: E.s }}>
            {puedeImportar && <Boton titulo="Importar Excel o CSV" icono="upload-file" variante="secundario" onPress={() => router.push('/alumnos/importar' as never)} />}
            {puedeExportar && (
              <Boton titulo="Exportar a Excel" icono="table-view" variante="secundario" cargando={exportando} onPress={async () => {
                setExportando(true);
                try { await descargarYAbrir('alumnos/exportar', { nombre: 'alumnos.xlsx' }); } catch (e) { avisar(e instanceof Error ? e.message : 'No se pudo exportar.', 'error'); } finally { setExportando(false); }
              }} />
            )}
          </View>
        )}
        render={(a) => (
          <ItemLista
            titulo={a.nombre}
            subtitulo={[a.sede?.nombre, a.bloques?.map((b) => b.nombre).join(', ')].filter(Boolean).join(' · ') || null}
            onPress={() => router.push({ pathname: '/alumnos/[id]', params: { id: String(a.id) } } as never)}
          />
        )}
        onCrear={puedeCrear ? () => router.push('/alumnos/nuevo' as never) : undefined}
        textoCrear="Inscribir"
      />
    </>
  );
}
