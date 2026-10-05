import { router, Stack } from 'expo-router';
import { useState } from 'react';
import { Pressable } from 'react-native';

import { FiltrosChips, ListaPaginada } from '@/components/lista';
import { Tarjeta } from '@/components/ui';
import { FilaFoto } from '@/features/archivo/Miniatura';
import type { FotoGestion } from '@/features/archivo/tipos';

/** Aportes de la comunidad esperando revisión (o con cambios pedidos / rechazados). */
export default function Moderacion() {
  const [estado, setEstado] = useState<string | null>(null);

  return (
    <>
      <Stack.Screen options={{ title: 'Moderación' }} />
      <ListaPaginada<FotoGestion>
        ruta="archivo/gestion/moderacion"
        filtros={{ estado }}
        vacio="No hay aportes esperando revisión."
        iconoVacio="verified-user"
        cabecera={<FiltrosChips opciones={[{ valor: 'cambios', etiqueta: 'Cambios pedidos' }, { valor: 'rechazada', etiqueta: 'Rechazados' }]} valor={estado} onChange={setEstado} todos="Pendientes" />}
        render={(f) => (
          <Pressable onPress={() => router.push({ pathname: '/archivo/fotos/[id]', params: { id: String(f.id) } } as never)} accessibilityRole="button" accessibilityLabel={`Revisar ${f.titulo}`}>
            <Tarjeta>
              <FilaFoto foto={f} detalle={[f.aportante_nombre && `Aporte de ${f.aportante_nombre}`, f.duplicados ? 'Posible duplicado' : null].filter(Boolean).join(' · ')} />
            </Tarjeta>
          </Pressable>
        )}
      />
    </>
  );
}
