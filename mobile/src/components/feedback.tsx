import { createContext, type ReactNode, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { Alert, Animated, KeyboardAvoidingView, Modal, Platform, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useConexion } from '@/lib/offline';
import { C, E, TOQUE } from '@/lib/theme';

import { Boton, Icon } from './ui';

/* ───────────── Toast: confirmación breve de una operación ───────────── */

type Tono = 'exito' | 'error' | 'info';
interface Mensaje { id: number; texto: string; tono: Tono }

const ToastContext = createContext<(texto: string, tono?: Tono) => void>(() => undefined);

export function ToastProvider({ children }: { children: ReactNode }) {
  const [mensaje, setMensaje] = useState<Mensaje | null>(null);
  const [opacidad] = useState(() => new Animated.Value(0));
  const insets = useSafeAreaInsets();

  const avisar = useCallback((texto: string, tono: Tono = 'exito') => setMensaje({ id: Date.now(), texto, tono }), []);

  useEffect(() => {
    if (!mensaje) return;
    Animated.timing(opacidad, { toValue: 1, duration: 180, useNativeDriver: true }).start();
    const t = setTimeout(() => {
      Animated.timing(opacidad, { toValue: 0, duration: 220, useNativeDriver: true }).start(() => setMensaje(null));
    }, mensaje.tono === 'error' ? 4500 : 2800);
    return () => clearTimeout(t);
  }, [mensaje, opacidad]);

  const color = mensaje?.tono === 'error' ? C.peligro : mensaje?.tono === 'info' ? C.info : C.exito;

  return (
    <ToastContext.Provider value={avisar}>
      {children}
      {mensaje && (
        <Animated.View
          pointerEvents="none"
          accessibilityLiveRegion="polite"
          accessibilityRole="alert"
          style={[s.toast, { bottom: insets.bottom + 80, opacity: opacidad, borderColor: color }]}>
          <Icon name={mensaje.tono === 'error' ? 'error-outline' : mensaje.tono === 'info' ? 'info-outline' : 'check-circle'} size={22} color={color} />
          <Text style={s.toastTexto}>{mensaje.texto}</Text>
        </Animated.View>
      )}
    </ToastContext.Provider>
  );
}

/** `const avisar = useToast(); avisar('Pago registrado correctamente')` */
export const useToast = () => useContext(ToastContext);

/* ───────────── Confirmaciones ───────────── */

/**
 * Confirmación antes de una acción destructiva o sensible. Resuelve true si se acepta.
 * `await confirmar({ titulo: '¿Anular este pago?', mensaje: 'Queda registrado en auditoría.', accion: 'Anular' })`
 */
export function confirmar({ titulo, mensaje, accion = 'Confirmar', destructiva = true }: { titulo: string; mensaje?: string; accion?: string; destructiva?: boolean }): Promise<boolean> {
  return new Promise((resolve) => {
    if (Platform.OS === 'web') {
      resolve(globalThis.confirm?.(`${titulo}${mensaje ? `\n\n${mensaje}` : ''}`) ?? false);
      return;
    }
    Alert.alert(titulo, mensaje, [
      { text: 'Cancelar', style: 'cancel', onPress: () => resolve(false) },
      { text: accion, style: destructiva ? 'destructive' : 'default', onPress: () => resolve(true) },
    ], { cancelable: true, onDismiss: () => resolve(false) });
  });
}

/**
 * Diálogo que pide un texto (motivo de anulación, observación de rechazo…).
 * Controlado: `<DialogoTexto visible={..} onConfirmar={(t) => ..} />`.
 */
export function DialogoTexto({ visible, titulo, mensaje, etiqueta = 'Motivo', accion = 'Confirmar', minimo = 0, destructiva, cargando, error, onConfirmar, onCerrar }: {
  visible: boolean;
  titulo: string;
  mensaje?: string;
  etiqueta?: string;
  accion?: string;
  minimo?: number;
  destructiva?: boolean;
  cargando?: boolean;
  error?: string | null;
  onConfirmar: (texto: string) => void;
  onCerrar: () => void;
}) {
  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onCerrar}>
      {/* El contenido se monta al abrir: el texto arranca vacío cada vez. */}
      {visible && <ContenidoDialogo {...{ titulo, mensaje, etiqueta, accion, minimo, destructiva, cargando, error, onConfirmar, onCerrar }} />}
    </Modal>
  );
}

function ContenidoDialogo({ titulo, mensaje, etiqueta, accion, minimo, destructiva, cargando, error, onConfirmar, onCerrar }: {
  titulo: string;
  mensaje?: string;
  etiqueta: string;
  accion: string;
  minimo: number;
  destructiva?: boolean;
  cargando?: boolean;
  error?: string | null;
  onConfirmar: (texto: string) => void;
  onCerrar: () => void;
}) {
  const [texto, setTexto] = useState('');
  const valido = texto.trim().length >= minimo;
  return (
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={s.fondoModal}>
        <View style={s.dialogo} accessibilityViewIsModal>
          <Text style={s.dialogoTitulo} accessibilityRole="header">{titulo}</Text>
          {mensaje && <Text style={s.dialogoMensaje}>{mensaje}</Text>}
          <Text style={s.etiqueta}>{etiqueta}{minimo > 0 ? ` (mínimo ${minimo} caracteres)` : ''}</Text>
          <TextInput
            style={[s.input, { minHeight: 96, textAlignVertical: 'top', paddingTop: E.m }]}
            value={texto}
            onChangeText={setTexto}
            multiline
            autoFocus
            accessibilityLabel={etiqueta}
            placeholderTextColor={C.tenue}
          />
          {error && <Text style={{ color: C.peligro }}>{error}</Text>}
          <View style={{ flexDirection: 'row', gap: E.s }}>
            <View style={{ flex: 1 }}><Boton titulo="Cancelar" variante="secundario" onPress={onCerrar} deshabilitado={cargando} /></View>
            <View style={{ flex: 1 }}>
              <Boton titulo={accion} variante={destructiva ? 'peligro' : 'primario'} onPress={() => onConfirmar(texto.trim())} deshabilitado={!valido} cargando={cargando} />
            </View>
          </View>
        </View>
      </KeyboardAvoidingView>
  );
}

/* ───────────── Hoja inferior (bottom sheet simple) ───────────── */

export function Hoja({ visible, titulo, children, onCerrar }: { visible: boolean; titulo: string; children: ReactNode; onCerrar: () => void }) {
  const insets = useSafeAreaInsets();
  return (
    <Modal visible={visible} transparent animationType="slide" onRequestClose={onCerrar}>
      <Pressable style={s.fondoHoja} onPress={onCerrar} accessibilityLabel="Cerrar" />
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <View style={[s.hoja, { paddingBottom: insets.bottom + E.l }]} accessibilityViewIsModal>
          <View style={s.asa} />
          <View style={{ flexDirection: 'row', alignItems: 'center' }}>
            <Text style={[s.dialogoTitulo, { flex: 1 }]} accessibilityRole="header">{titulo}</Text>
            <Pressable onPress={onCerrar} accessibilityRole="button" accessibilityLabel="Cerrar" hitSlop={12} style={{ padding: E.s }}>
              <Icon name="close" size={24} color={C.tenue} />
            </Pressable>
          </View>
          {children}
        </View>
      </KeyboardAvoidingView>
    </Modal>
  );
}

/* ───────────── Banner sin conexión ───────────── */

export function BannerSinConexion() {
  const online = useConexion();
  const insets = useSafeAreaInsets();
  if (online) return null;
  return (
    <View style={[s.offline, { paddingTop: insets.top + 2 }]} accessibilityLiveRegion="polite">
      <Icon name="cloud-off" size={16} color={C.texto} />
      <Text style={{ color: C.texto, fontWeight: '700', fontSize: 13 }}>Sin conexión · mostramos lo último guardado</Text>
    </View>
  );
}

/** Estado de envío con guardia de doble toque (el botón además se deshabilita). */
export function useEnvioUnico() {
  const enCurso = useRef(false);
  return useMemo(
    () => async <T,>(fn: () => Promise<T>): Promise<T | undefined> => {
      if (enCurso.current) return undefined;
      enCurso.current = true;
      try {
        return await fn();
      } finally {
        enCurso.current = false;
      }
    },
    [],
  );
}

const s = StyleSheet.create({
  toast: {
    position: 'absolute', left: E.l, right: E.l, flexDirection: 'row', alignItems: 'center', gap: E.s,
    backgroundColor: C.superficie2, borderRadius: 14, borderWidth: 1, padding: E.m, elevation: 6,
  },
  toastTexto: { color: C.texto, fontSize: 15, fontWeight: '600', flex: 1 },
  fondoModal: { flex: 1, backgroundColor: 'rgba(0,0,0,0.7)', justifyContent: 'center', padding: E.l },
  dialogo: { backgroundColor: C.superficie, borderRadius: 16, padding: E.l, gap: E.m, borderWidth: 1, borderColor: C.borde },
  dialogoTitulo: { color: C.texto, fontSize: 19, fontWeight: '800' },
  dialogoMensaje: { color: C.tenue, fontSize: 15 },
  etiqueta: { color: C.tenue, fontSize: 14 },
  input: { backgroundColor: C.fondo, borderRadius: 12, borderWidth: 1, borderColor: C.borde, color: C.texto, fontSize: 16, paddingHorizontal: E.m, minHeight: TOQUE },
  fondoHoja: { flex: 1, backgroundColor: 'rgba(0,0,0,0.6)' },
  hoja: { backgroundColor: C.superficie, borderTopLeftRadius: 20, borderTopRightRadius: 20, padding: E.l, gap: E.m, maxHeight: '85%', borderTopWidth: 1, borderColor: C.borde },
  asa: { alignSelf: 'center', width: 40, height: 4, borderRadius: 2, backgroundColor: C.borde },
  offline: { backgroundColor: C.alerta, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: E.s, paddingBottom: 4 },
});
