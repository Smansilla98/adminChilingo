import { useQueryClient } from '@tanstack/react-query';
import { ScrollView, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useToast } from '@/components/feedback';
import { Campo, ErrorFormulario, useFormulario } from '@/components/form';
import { Aviso, Boton, Tenue, Titulo } from '@/components/ui';
import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { C, E } from '@/lib/theme';

/**
 * Cambiar la contraseña propia. `obligatoria`: entró con una provisoria y no puede seguir sin elegir la suya.
 */
export function CambiarClave({ obligatoria = false, usuario, alTerminar }: { obligatoria?: boolean; usuario?: string; alTerminar?: () => void }) {
  const qc = useQueryClient();
  const avisar = useToast();
  const { salir } = useAuth();
  const insets = useSafeAreaInsets();
  const f = useFormulario({ actual: '', password: '', password_confirmation: '' });
  const v = f.valores;

  const enviar = async () => {
    const ok = await f.enviar(
      (d) => api('auth/clave', { method: 'POST', body: d }),
      (d) => ({
        ...(!d.actual ? { actual: obligatoria ? 'Escribí la contraseña provisoria.' : 'Escribí tu contraseña actual.' } : {}),
        ...(!d.password ? { password: 'Elegí una contraseña nueva.' } : {}),
        ...(d.password && d.password.length < 6 ? { password: 'Al menos 6 letras o números.' } : {}),
        ...(d.password && d.password !== d.password_confirmation ? { password_confirmation: 'Las dos contraseñas no coinciden.' } : {}),
      }),
    );
    if (ok === undefined) return;
    avisar('Listo, ya tenés tu contraseña nueva.');
    await qc.invalidateQueries();
    alTerminar?.();
  };

  return (
    <ScrollView style={{ flex: 1, backgroundColor: C.fondo }} contentContainerStyle={{ padding: E.xl, gap: E.m, paddingTop: obligatoria ? Math.max(insets.top, 24) + 8 : E.xl }} keyboardShouldPersistTaps="handled">
      {obligatoria && <Titulo>Elegí tu contraseña</Titulo>}
      {obligatoria && <Aviso tono="info" texto={`Entraste con una contraseña provisoria. Elegí la tuya para seguir.${usuario ? ` Tu usuario es ${usuario}.` : ''}`} />}
      <Campo etiqueta={obligatoria ? 'Contraseña provisoria' : 'Contraseña actual'} valor={v.actual} onChange={(x) => f.set('actual', x)} error={f.errores.actual} secreto requerido autoCapitalize="none" />
      <Campo etiqueta="Contraseña nueva" valor={v.password} onChange={(x) => f.set('password', x)} error={f.errores.password} secreto requerido autoCapitalize="none" ayuda="Al menos 6 letras o números. No uses tu DNI." />
      <Campo etiqueta="Repetila" valor={v.password_confirmation} onChange={(x) => f.set('password_confirmation', x)} error={f.errores.password_confirmation} secreto requerido autoCapitalize="none" />
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Boton titulo="Guardar contraseña" icono="check" grande cargando={f.enviando} onPress={() => void enviar()} />
      {obligatoria && (
        <View style={{ gap: E.s }}>
          <Boton titulo="Salir" variante="secundario" onPress={() => void salir()} />
          <Tenue style={{ textAlign: 'center' }}>Las demás sesiones abiertas se cierran al cambiarla.</Tenue>
        </View>
      )}
    </ScrollView>
  );
}
