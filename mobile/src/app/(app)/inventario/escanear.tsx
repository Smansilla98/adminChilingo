import { CameraView, useCameraPermissions } from 'expo-camera';
import { router } from 'expo-router';
import { useRef, useState } from 'react';
import { StyleSheet, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Aviso, Boton, Tenue, Texto } from '@/components/ui';
import { api } from '@/lib/api';
import { C, E, TOQUE } from '@/lib/theme';
import type { InventarioItem } from '@/lib/types';

/**
 * Escanea el QR de la etiqueta del instrumento (contiene la URL pública /tambor/{codigo})
 * o permite tipear el código. Busca solo dentro del alcance de la persona.
 */
export default function Escanear() {
  const [permiso, pedirPermiso] = useCameraPermissions();
  const [error, setError] = useState<string | null>(null);
  const [manual, setManual] = useState('');
  const buscando = useRef(false);

  const buscar = async (codigo: string) => {
    if (buscando.current || !codigo.trim()) return;
    buscando.current = true;
    setError(null);
    try {
      const r = await api<{ data: InventarioItem }>(`inventario/codigo/${encodeURIComponent(codigo.trim())}`);
      router.replace({ pathname: '/inventario/[id]', params: { id: String(r.data.id) } } as never);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'No se encontró el ítem.');
      setTimeout(() => (buscando.current = false), 1500); // evita lecturas repetidas del mismo QR
    }
  };

  return (
    <SafeAreaView style={s.pantalla} edges={['bottom', 'left', 'right']}>
      {!permiso ? null : !permiso.granted ? (
        <View style={s.centro}>
          <Texto style={{ textAlign: 'center' }}>Para escanear necesitamos usar la cámara.</Texto>
          <Boton titulo="Permitir cámara" icono="photo-camera" onPress={pedirPermiso} grande />
        </View>
      ) : (
        <CameraView
          style={s.camara}
          facing="back"
          barcodeScannerSettings={{ barcodeTypes: ['qr', 'code128', 'code39'] }}
          onBarcodeScanned={({ data }) => void buscar(data)}
        />
      )}
      <View style={s.panel}>
        {error && <Aviso tono="peligro" texto={error} />}
        <Tenue>¿No lee el QR? Escribí el código de la etiqueta:</Tenue>
        <TextInput style={s.input} placeholder="Ej.: CHL-0042" placeholderTextColor={C.tenue} autoCapitalize="characters" value={manual} onChangeText={setManual} onSubmitEditing={() => void buscar(manual)} returnKeyType="search" accessibilityLabel="Código del instrumento" />
        <Boton titulo="Buscar" icono="search" onPress={() => { buscando.current = false; void buscar(manual); }} />
      </View>
    </SafeAreaView>
  );
}

const s = StyleSheet.create({
  pantalla: { flex: 1, backgroundColor: C.fondo },
  camara: { flex: 1 },
  centro: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: E.l, padding: E.xl },
  panel: { padding: E.l, gap: E.s },
  input: { backgroundColor: C.superficie, borderRadius: 12, borderWidth: 1, borderColor: C.borde, color: C.texto, fontSize: 18, paddingHorizontal: E.l, minHeight: TOQUE + 4 },
});
