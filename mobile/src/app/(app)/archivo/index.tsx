import { useQuery } from '@tanstack/react-query';
import { router, Stack } from 'expo-router';
import { useState } from 'react';
import { Linking, Pressable, View } from 'react-native';

import { FiltrosChips, ListaPaginada } from '@/components/lista';
import { Boton, Tarjeta, Tenue, Texto } from '@/components/ui';
import { api } from '@/lib/api';
import { API_URL } from '@/lib/config';
import { useCatalogo } from '@/lib/recursos';
import { E } from '@/lib/theme';
import { FilaFoto } from '@/features/archivo/Miniatura';
import { type CatalogoArchivo, type FotoResumen, urlArchivoPublico } from '@/features/archivo/tipos';

/** Archivo histórico: mis aportes y, para el equipo, accesos a moderación y fotos. */
export default function Archivo() {
  const cat = useCatalogo<CatalogoArchivo>('archivo/catalogo', 1);
  const [estado, setEstado] = useState<string | null>(null);
  const gestiona = !!cat.data?.permisos.gestionar;
  const resumen = useQuery({
    queryKey: ['archivo', 'resumen'],
    queryFn: ({ signal }) => api<{ moderacion: { pendiente: number }; totales: { fotos: number; publicadas: number } }>('archivo/gestion/resumen', { signal }),
    enabled: gestiona,
  });

  return (
    <>
      <Stack.Screen options={{ title: 'Archivo histórico' }} />
      <ListaPaginada<FotoResumen>
        ruta="archivo/aportes"
        filtros={{ estado }}
        vacio="Todavía no compartiste fotos. Cada una ayuda a contar la historia de La Chilinga."
        iconoVacio="photo-library"
        cabecera={
          <View style={{ gap: E.m }}>
            <Tarjeta>
              <Texto style={{ fontSize: 22, fontWeight: '800' }}>Compartí un recuerdo</Texto>
              <Tenue>¿Tenés fotos de La Chilinga? Subilas al archivo: el equipo las revisa con vos antes de publicarlas.</Tenue>
              <Boton titulo="Subir fotos" icono="add-photo-alternate" onPress={() => router.push('/archivo/aportar' as never)} />
            </Tarjeta>
            {gestiona && (
              <Tarjeta>
                <Texto style={{ fontWeight: '800' }}>Gestión del archivo</Texto>
                {resumen.data && <Tenue>{resumen.data.totales.publicadas} publicadas de {resumen.data.totales.fotos} fotos</Tenue>}
                <Boton titulo={`Moderación${resumen.data ? ` (${resumen.data.moderacion.pendiente})` : ''}`} icono="verified-user" variante="secundario" onPress={() => router.push('/archivo/moderacion' as never)} />
                <Boton titulo="Fotos del archivo" icono="collections" variante="secundario" onPress={() => router.push('/archivo/fotos' as never)} />
              </Tarjeta>
            )}
            <Boton titulo="Ver el archivo público" icono="open-in-new" variante="secundario" onPress={() => void Linking.openURL(urlArchivoPublico(API_URL))} />
            <Texto style={{ fontWeight: '800', marginTop: E.s }}>Mis aportes</Texto>
            <FiltrosChips
              opciones={[{ valor: 'pendiente', etiqueta: 'En revisión' }, { valor: 'cambios', etiqueta: 'Cambios pedidos' }, { valor: 'publicada', etiqueta: 'Publicadas' }, { valor: 'rechazada', etiqueta: 'Rechazadas' }, { valor: 'borrador', etiqueta: 'Borradores' }]}
              valor={estado}
              onChange={setEstado}
            />
          </View>
        }
        render={(f) => (
          <Pressable onPress={() => router.push({ pathname: '/archivo/aportes/[id]', params: { id: String(f.id) } } as never)} accessibilityRole="button" accessibilityLabel={`${f.titulo}, ${f.estado_etiqueta ?? ''}`}>
            <Tarjeta>
              <FilaFoto foto={f} detalle={f.estado === 'cambios' ? 'Te pedimos más información →' : null} />
            </Tarjeta>
          </Pressable>
        )}
      />
    </>
  );
}
