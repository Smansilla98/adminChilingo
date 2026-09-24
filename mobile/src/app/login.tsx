import { Image } from 'expo-image';
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, StyleSheet, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Aviso, Boton, Tenue } from '@/components/ui';
import { useAuth } from '@/lib/auth';
import { VARIANTE } from '@/lib/config';
import { C, E, TOQUE } from '@/lib/theme';

export default function Login() {
  const { ingresar } = useAuth();
  const [usuario, setUsuario] = useState('');
  const [clave, setClave] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);

  const enviar = async () => {
    if (!usuario || !clave) {
      setError('Completá usuario y contraseña.');
      return;
    }
    setEnviando(true);
    setError(null);
    try {
      await ingresar(usuario, clave);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'No se pudo ingresar.');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <SafeAreaView style={s.pantalla}>
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={s.centro}>
        <Image source={require('@/assets/images/icon.png')} style={s.logo} contentFit="contain" accessibilityLabel="ITO · La Chilinga" />
        <Text style={s.titulo}>La Chilinga</Text>
        <Tenue>Ingresá con tu usuario del sistema</Tenue>

        <View style={s.form}>
          <TextInput
            style={s.input}
            placeholder="Usuario o email"
            placeholderTextColor={C.tenue}
            autoCapitalize="none"
            autoCorrect={false}
            autoComplete="username"
            textContentType="username"
            value={usuario}
            onChangeText={setUsuario}
            returnKeyType="next"
            accessibilityLabel="Usuario o email"
          />
          <TextInput
            style={s.input}
            placeholder="Contraseña"
            placeholderTextColor={C.tenue}
            secureTextEntry
            autoComplete="password"
            textContentType="password"
            value={clave}
            onChangeText={setClave}
            returnKeyType="go"
            onSubmitEditing={enviar}
            accessibilityLabel="Contraseña"
          />
          {error && <Aviso texto={error} tono="peligro" />}
          <Boton titulo="Ingresar" onPress={enviar} cargando={enviando} grande />
        </View>
        {VARIANTE !== 'production' && <Tenue>Entorno: {VARIANTE}</Tenue>}
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const s = StyleSheet.create({
  pantalla: { flex: 1, backgroundColor: C.fondo },
  centro: { flex: 1, justifyContent: 'center', padding: E.xl, gap: E.m },
  logo: { width: 140, height: 140, alignSelf: 'center' },
  titulo: { color: C.texto, fontSize: 30, fontWeight: '800', textAlign: 'center' },
  form: { gap: E.m, marginTop: E.l },
  input: { backgroundColor: C.superficie, borderRadius: 12, borderWidth: 1, borderColor: C.borde, color: C.texto, fontSize: 17, paddingHorizontal: E.l, minHeight: TOQUE + 8 },
});
