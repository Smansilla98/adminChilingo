import { router } from 'expo-router';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Campo, CampoFecha, ErrorFormulario, useFormulario } from '@/components/form';
import { Boton, Tenue, Titulo } from '@/components/ui';
import { useAuth } from '@/lib/auth';
import { C, E } from '@/lib/theme';

/**
 * Un alumno que la escuela ya tiene cargado crea su cuenta: DNI y fecha de nacimiento (tienen que
 * coincidir con su ficha) y una contraseña que elige él. Después entra con su DNI.
 */
export default function Registro() {
  const { registrarse } = useAuth();
  const insets = useSafeAreaInsets();
  const f = useFormulario({ dni: '', fecha_nacimiento: '', email: '', password: '', password_confirmation: '' });
  const v = f.valores;

  const enviar = () =>
    f.enviar(
      (d) => registrarse({ ...d, email: d.email.trim() || undefined }),
      (d) => ({
        ...(!d.dni.trim() ? { dni: 'Escribí tu DNI.' } : {}),
        ...(!d.fecha_nacimiento ? { fecha_nacimiento: 'Elegí tu fecha de nacimiento.' } : {}),
        ...(!d.password ? { password: 'Elegí una contraseña.' } : {}),
        ...(d.password && d.password.length < 6 ? { password: 'Al menos 6 letras o números.' } : {}),
        ...(d.password && d.password !== d.password_confirmation ? { password_confirmation: 'Las dos contraseñas no coinciden.' } : {}),
        ...(d.password && d.password.replace(/\D/g, '') === d.dni.replace(/\D/g, '') && /^\d+$/.test(d.password) ? { password: 'No uses tu DNI como contraseña.' } : {}),
      }),
    );

  return (
    <View style={{ flex: 1, backgroundColor: C.fondo }}>
      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : 'height'}>
        <ScrollView contentContainerStyle={[s.scroll, { paddingTop: Math.max(insets.top, 24) + 8, paddingBottom: Math.max(insets.bottom, 24) + 24 }]} keyboardShouldPersistTaps="handled">
          <Titulo>Crear mi cuenta</Titulo>
          <Tenue>Si ya estás anotado en La Chilinga, con tu DNI y tu fecha de nacimiento (los mismos de tu ficha) creás tu cuenta en un minuto.</Tenue>
          <Campo etiqueta="Tu DNI" valor={v.dni} onChange={(x) => f.set('dni', x)} error={f.errores.dni} teclado="number-pad" requerido placeholder="30123456" />
          <CampoFecha etiqueta="Tu fecha de nacimiento" valor={v.fecha_nacimiento} onChange={(x) => f.set('fecha_nacimiento', x)} error={f.errores.fecha_nacimiento} requerido maximo={new Date().toISOString().slice(0, 10)} />
          <Campo etiqueta="Elegí una contraseña" valor={v.password} onChange={(x) => f.set('password', x)} error={f.errores.password} secreto requerido autoCapitalize="none" ayuda="Al menos 6 letras o números. No uses tu DNI." />
          <Campo etiqueta="Repetila" valor={v.password_confirmation} onChange={(x) => f.set('password_confirmation', x)} error={f.errores.password_confirmation} secreto requerido autoCapitalize="none" />
          <Campo etiqueta="Tu correo (opcional)" valor={v.email} onChange={(x) => f.set('email', x)} error={f.errores.email} teclado="email-address" autoCapitalize="none" />
          <ErrorFormulario mensaje={f.errorGeneral} />
          <Boton titulo="Crear mi cuenta" icono="check" grande cargando={f.enviando} onPress={() => void enviar()} />
          <Boton titulo="Ya tengo cuenta" variante="secundario" onPress={() => router.back()} />
          <Tenue style={{ textAlign: 'center' }}>¿No te deja? Pedile a tu profe o a administración que revisen tu DNI y tu fecha de nacimiento, o que te den acceso con una contraseña provisoria.</Tenue>
        </ScrollView>
      </KeyboardAvoidingView>
    </View>
  );
}

const s = StyleSheet.create({ scroll: { flexGrow: 1, paddingHorizontal: E.xl, gap: E.m } });
