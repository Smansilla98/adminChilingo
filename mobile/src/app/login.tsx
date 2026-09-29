import { Image } from 'expo-image';
import { useRef, useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Aviso, Boton, Icon, Tenue } from '@/components/ui';
import { useAuth } from '@/lib/auth';
import { VARIANTE } from '@/lib/config';
import { C, E, TOQUE } from '@/lib/theme';

export default function Login() {
  const { ingresar } = useAuth();
  const insets = useSafeAreaInsets();
  const scrollRef = useRef<ScrollView>(null);
  const claveRef = useRef<TextInput>(null);
  const [usuario, setUsuario] = useState('');
  const [clave, setClave] = useState('');
  const [verClave, setVerClave] = useState(false);
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

  const mostrarClave = () => {
    setTimeout(() => scrollRef.current?.scrollToEnd({ animated: true }), 80);
  };

  return (
    <View style={s.pantalla}>
      <KeyboardAvoidingView style={s.flex} behavior={Platform.OS === 'ios' ? 'padding' : 'height'} keyboardVerticalOffset={Platform.OS === 'ios' ? 8 : 0}>
        <ScrollView
          ref={scrollRef}
          contentContainerStyle={[s.scroll, { paddingTop: Math.max(insets.top, 24) + 12, paddingBottom: Math.max(insets.bottom, 24) + 28 }]}
          keyboardShouldPersistTaps="handled"
          keyboardDismissMode="on-drag"
          showsVerticalScrollIndicator={false}
          bounces>
          <Image source={require('@/assets/images/icon.png')} style={s.logo} contentFit="contain" accessibilityLabel="La Chilinga" />
          <Tenue style={s.centroTexto}>Ingresá con tu usuario del sistema</Tenue>

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
              blurOnSubmit={false}
              onSubmitEditing={() => claveRef.current?.focus()}
              accessibilityLabel="Usuario o email"
            />
            <View style={s.claveFila}>
              <TextInput
                ref={claveRef}
                style={[s.input, s.claveInput]}
                placeholder="Contraseña"
                placeholderTextColor={C.tenue}
                secureTextEntry={!verClave}
                autoCapitalize="none"
                autoCorrect={false}
                autoComplete="password"
                textContentType="password"
                value={clave}
                onChangeText={setClave}
                returnKeyType="go"
                onFocus={mostrarClave}
                onSubmitEditing={() => void enviar()}
                accessibilityLabel="Contraseña"
              />
              <Pressable
                onPress={() => setVerClave((v) => !v)}
                style={s.ojo}
                accessibilityRole="button"
                accessibilityLabel={verClave ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                hitSlop={8}>
                <Icon name={verClave ? 'visibility-off' : 'visibility'} size={22} color={C.tenue} />
              </Pressable>
            </View>
            {error && <Aviso texto={error} tono="peligro" />}
            <Boton titulo="Ingresar" onPress={() => void enviar()} cargando={enviando} grande />
          </View>
          {VARIANTE !== 'production' && <Tenue style={s.centroTexto}>Entorno: {VARIANTE}</Tenue>}
          <Text style={s.credito}>desarrollado por Santi Mansilla - 2026</Text>
        </ScrollView>
      </KeyboardAvoidingView>
    </View>
  );
}

const s = StyleSheet.create({
  pantalla: { flex: 1, backgroundColor: C.fondo },
  flex: { flex: 1 },
  scroll: { flexGrow: 1, justifyContent: 'center', paddingHorizontal: E.xl, gap: E.m },
  logo: { width: 160, height: 160, alignSelf: 'center' },
  centroTexto: { textAlign: 'center' },
  form: { gap: E.m, marginTop: E.l },
  input: { backgroundColor: C.superficie, borderRadius: 12, borderWidth: 1, borderColor: C.borde, color: C.texto, fontSize: 17, paddingHorizontal: E.l, minHeight: TOQUE + 8 },
  claveFila: { justifyContent: 'center' },
  claveInput: { paddingRight: TOQUE + 8 },
  ojo: { position: 'absolute', right: 4, height: TOQUE + 8, width: TOQUE, alignItems: 'center', justifyContent: 'center' },
  credito: { color: C.tenue, fontSize: 13, textAlign: 'center', marginTop: E.xl },
});
