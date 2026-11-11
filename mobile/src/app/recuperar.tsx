import { router } from 'expo-router';
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Campo, ErrorFormulario, useFormulario } from '@/components/form';
import { Aviso, Boton, Tenue, Titulo } from '@/components/ui';
import { api } from '@/lib/api';
import { C, E } from '@/lib/theme';

/**
 * «Olvidé mi contraseña»: la persona escribe su DNI (o usuario o correo), la app le manda un código
 * por correo o WhatsApp y con ese código elige una contraseña nueva. El código vence en 15 minutos.
 * La respuesta es la misma exista o no la cuenta.
 */
export default function Recuperar() {
  const insets = useSafeAreaInsets();
  const [paso, setPaso] = useState<'pedir' | 'confirmar'>('pedir');
  const [usuario, setUsuario] = useState('');
  const [aviso, setAviso] = useState<string | null>(null);

  const pedir = useFormulario({ usuario: '' });
  const confirmar = useFormulario({ codigo: '', password: '', password_confirmation: '' });

  const enviarCodigo = () =>
    pedir.enviar(
      async (d) => {
        const r = await api<{ mensaje: string }>('auth/recuperar', { method: 'POST', body: { usuario: d.usuario.trim() } });
        setUsuario(d.usuario.trim());
        setAviso(r.mensaje);
        setPaso('confirmar');
      },
      (d) => (!d.usuario.trim() ? { usuario: 'Escribí tu DNI, tu usuario o tu correo.' } : ({} as Record<string, string>)),
    );

  const cambiar = () =>
    confirmar.enviar(
      async (d) => {
        await api('auth/recuperar/confirmar', {
          method: 'POST',
          body: { usuario, codigo: d.codigo.trim().toUpperCase(), password: d.password, password_confirmation: d.password_confirmation },
        });
        setAviso('Listo, ya podés entrar con tu nueva contraseña.');
        setTimeout(() => router.replace('/login' as never), 1200);
      },
      (d) => ({
        ...(!d.codigo.trim() ? { codigo: 'Escribí el código que te mandamos.' } : {}),
        ...(d.password && d.password.length < 8 ? { password: 'Al menos 8 letras o números.' } : {}),
        ...(d.password && d.password !== d.password_confirmation ? { password_confirmation: 'Las dos contraseñas no coinciden.' } : {}),
        ...(d.password && usuario && d.password === usuario ? { password: 'No uses tu DNI como contraseña.' } : {}),
      }),
    );

  return (
    <View style={{ flex: 1, backgroundColor: C.fondo }}>
      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : 'height'}>
        <ScrollView contentContainerStyle={[s.scroll, { paddingTop: Math.max(insets.top, 24) + 8, paddingBottom: Math.max(insets.bottom, 24) + 24 }]} keyboardShouldPersistTaps="handled">
          <Titulo>Olvidé mi contraseña</Titulo>

          {paso === 'pedir' ? (
            <>
              <Tenue>Escribí tu DNI (o tu usuario o tu correo). Te mandamos un código por correo o por WhatsApp.</Tenue>
              <Campo etiqueta="Tu DNI, usuario o correo" valor={pedir.valores.usuario} onChange={(x) => pedir.set('usuario', x)} error={pedir.errores.usuario} requerido autoCapitalize="none" />
              <ErrorFormulario mensaje={pedir.errorGeneral} />
              <Boton titulo="Mandarme el código" icono="send" grande cargando={pedir.enviando} onPress={() => void enviarCodigo()} />
            </>
          ) : (
            <>
              {aviso && <Aviso texto={aviso} tono="info" />}
              <Tenue>Escribí el código de 6 números que te llegó. Vence a los 15 minutos.</Tenue>
              <Campo etiqueta="Código" valor={confirmar.valores.codigo} onChange={(x) => confirmar.set('codigo', x)} error={confirmar.errores.codigo} teclado="number-pad" requerido maxLength={12} />
              <Campo etiqueta="Contraseña nueva" valor={confirmar.valores.password} onChange={(x) => confirmar.set('password', x)} error={confirmar.errores.password} secreto requerido autoCapitalize="none" ayuda="Al menos 8 letras o números. No uses tu DNI." />
              <Campo etiqueta="Repetila" valor={confirmar.valores.password_confirmation} onChange={(x) => confirmar.set('password_confirmation', x)} error={confirmar.errores.password_confirmation} secreto requerido autoCapitalize="none" />
              <ErrorFormulario mensaje={confirmar.errorGeneral} />
              <Boton titulo="Guardar contraseña" icono="check" grande cargando={confirmar.enviando} onPress={() => void cambiar()} />
              <Boton titulo="No me llegó el código" variante="secundario" onPress={() => setPaso('pedir')} />
            </>
          )}

          <Boton titulo="Volver a entrar" variante="secundario" onPress={() => router.back()} />
        </ScrollView>
      </KeyboardAvoidingView>
    </View>
  );
}

const s = StyleSheet.create({ scroll: { flexGrow: 1, paddingHorizontal: E.xl, gap: E.m } });
