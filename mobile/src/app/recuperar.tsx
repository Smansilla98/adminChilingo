import { router } from 'expo-router';
import { ScrollView, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Aviso, Boton, Titulo } from '@/components/ui';
import { C, E } from '@/lib/theme';

/**
 * «Olvidé mi contraseña»: por ahora la recuperación no se hace desde la app. La clave provisoria
 * la da administración (o tu profe), que la genera desde tu ficha de alumno.
 */
export default function Recuperar() {
  const insets = useSafeAreaInsets();

  return (
    <View style={{ flex: 1, backgroundColor: C.fondo }}>
      <ScrollView contentContainerStyle={[s.scroll, { paddingTop: Math.max(insets.top, 24) + 8, paddingBottom: Math.max(insets.bottom, 24) + 24 }]}>
        <Titulo>Olvidé mi contraseña</Titulo>

        <Aviso texto="Para recuperar tu contraseña, pedile a administración o a tu profe una clave provisoria. Al entrar con ella te va a pedir que elijas una nueva." tono="info" />

        <Boton titulo="Volver a entrar" variante="secundario" onPress={() => router.back()} />
      </ScrollView>
    </View>
  );
}

const s = StyleSheet.create({ scroll: { flexGrow: 1, paddingHorizontal: E.xl, gap: E.m } });
