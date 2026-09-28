import DateTimePicker, { type DateTimePickerEvent } from '@react-native-community/datetimepicker';
import { useQuery } from '@tanstack/react-query';
import { useCallback, useDeferredValue, useRef, useState, type ReactNode } from 'react';
import { ActivityIndicator, FlatList, Platform, Pressable, StyleSheet, Switch, Text, TextInput, View, type KeyboardTypeOptions } from 'react-native';

import { ApiError } from '@/lib/api';
import { type ArchivoLocal, elegirDocumento, elegirImagen, MB } from '@/lib/archivos';
import { C, E, TOQUE } from '@/lib/theme';

import { Hoja } from './feedback';
import { Aviso, Boton, Icon, Tenue } from './ui';

/* ───────────── Estado del formulario ───────────── */

/**
 * Estado de un formulario: valores, errores por campo (422 del servidor) y envío único.
 * Si la petición falla, los valores se conservan para corregir y reintentar.
 */
export function useFormulario<T extends Record<string, any>>(inicial: T) {
  const [valores, setValores] = useState<T>(inicial);
  const [errores, setErrores] = useState<Record<string, string>>({});
  const [errorGeneral, setErrorGeneral] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);
  const enCurso = useRef(false);

  const set = useCallback(<K extends keyof T>(campo: K, valor: T[K]) => {
    setValores((v) => ({ ...v, [campo]: valor }));
    setErrores((e) => {
      if (!e[campo as string]) return e;
      const { [campo as string]: _, ...resto } = e;
      return resto;
    });
  }, []);

  /** Ejecuta el envío una sola vez a la vez; mapea errores de validación a cada campo. */
  const enviar = useCallback(async <R,>(fn: (v: T) => Promise<R>, validar?: (v: T) => Record<string, string>): Promise<R | undefined> => {
    if (enCurso.current) return undefined;
    const locales = validar?.(valores) ?? {};
    if (Object.keys(locales).length) {
      setErrores(locales);
      setErrorGeneral('Revisá los datos marcados.');
      return undefined;
    }
    enCurso.current = true;
    setEnviando(true);
    setErrorGeneral(null);
    try {
      const r = await fn(valores);
      setErrores({});
      return r;
    } catch (e) {
      if (e instanceof ApiError && e.esValidacion && Object.keys(e.errores).length) {
        setErrores(Object.fromEntries(Object.entries(e.errores).map(([k, v]) => [k, v[0]])));
        // Errores de campos que el formulario no muestra: se ven arriba.
        setErrorGeneral(e.message);
      } else {
        setErrorGeneral(e instanceof Error ? e.message : 'No se pudo guardar.');
      }
      return undefined;
    } finally {
      enCurso.current = false;
      setEnviando(false);
    }
  }, [valores]);

  return { valores, set, setValores, errores, setErrores, errorGeneral, enviando, enviar };
}

/** Validación local mínima (UX). La definitiva la hace el servidor. */
export function requeridos<T extends Record<string, any>>(v: T, campos: Partial<Record<keyof T, string>>): Record<string, string> {
  const out: Record<string, string> = {};
  for (const [k, etiqueta] of Object.entries(campos)) {
    const x = v[k];
    if (x === undefined || x === null || x === '' || (Array.isArray(x) && x.length === 0)) out[k] = `${etiqueta} es obligatorio.`;
  }
  return out;
}

/* ───────────── Contenedores ───────────── */

export function Seccion({ titulo, children, ayuda }: { titulo?: string; children: ReactNode; ayuda?: string }) {
  return (
    <View style={s.seccion}>
      {titulo && <Text style={s.seccionTitulo} accessibilityRole="header">{titulo}</Text>}
      {ayuda && <Tenue>{ayuda}</Tenue>}
      {children}
    </View>
  );
}

export function ErrorFormulario({ mensaje }: { mensaje: string | null }) {
  return mensaje ? <Aviso tono="peligro" texto={mensaje} /> : null;
}

function Etiqueta({ texto, requerido }: { texto: string; requerido?: boolean }) {
  return <Text style={s.etiqueta}>{texto}{requerido ? ' *' : ''}</Text>;
}

function ErrorCampo({ error }: { error?: string }) {
  return error ? <Text style={s.error} accessibilityLiveRegion="polite">{error}</Text> : null;
}

/* ───────────── Campos ───────────── */

export function Campo({ etiqueta, valor, onChange, error, placeholder, requerido, ayuda, teclado, multilinea, secreto, autoCapitalize, editable = true, maxLength }: {
  etiqueta: string;
  valor: string | number | null | undefined;
  onChange: (v: string) => void;
  error?: string;
  placeholder?: string;
  requerido?: boolean;
  ayuda?: string;
  teclado?: KeyboardTypeOptions;
  multilinea?: boolean;
  secreto?: boolean;
  autoCapitalize?: 'none' | 'sentences' | 'words' | 'characters';
  editable?: boolean;
  maxLength?: number;
}) {
  return (
    <View style={s.campo}>
      <Etiqueta texto={etiqueta} requerido={requerido} />
      <TextInput
        style={[s.input, multilinea && s.multilinea, !!error && s.inputError, !editable && { opacity: 0.6 }]}
        value={valor === null || valor === undefined ? '' : String(valor)}
        onChangeText={onChange}
        placeholder={placeholder}
        placeholderTextColor={C.tenue}
        keyboardType={teclado}
        multiline={multilinea}
        secureTextEntry={secreto}
        autoCapitalize={autoCapitalize}
        editable={editable}
        maxLength={maxLength}
        accessibilityLabel={etiqueta}
        accessibilityHint={ayuda}
      />
      {ayuda && !error && <Tenue style={{ fontSize: 13 }}>{ayuda}</Tenue>}
      <ErrorCampo error={error} />
    </View>
  );
}

/** Monto en pesos: acepta coma o punto decimal y devuelve el texto normalizado. */
export function CampoMonto(props: Omit<Parameters<typeof Campo>[0], 'teclado' | 'onChange'> & { onChange: (v: string) => void }) {
  return <Campo {...props} teclado="decimal-pad" placeholder={props.placeholder ?? '0'} onChange={(t) => props.onChange(t.replace(',', '.').replace(/[^0-9.]/g, ''))} />;
}

const aIso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
export const hoyIso = () => aIso(new Date());

function desdeIso(iso: string | null | undefined): Date {
  if (!iso) return new Date();
  const [a, m, d] = iso.slice(0, 10).split('-').map(Number);
  return new Date(a, (m || 1) - 1, d || 1);
}

export function fechaLegible(iso: string | null | undefined): string {
  if (!iso) return '';
  return desdeIso(iso).toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

/** Fecha con el selector nativo. Valor en formato YYYY-MM-DD ('' = sin fecha). */
export function CampoFecha({ etiqueta, valor, onChange, error, requerido, opcional, minimo, maximo }: {
  etiqueta: string;
  valor: string | null | undefined;
  onChange: (v: string) => void;
  error?: string;
  requerido?: boolean;
  /** Muestra "Quitar" para dejarla vacía. */
  opcional?: boolean;
  minimo?: string;
  maximo?: string;
}) {
  const [abierto, setAbierto] = useState(false);
  const cambiar = (e: DateTimePickerEvent, d?: Date) => {
    setAbierto(Platform.OS === 'ios');
    if (e.type === 'set' && d) onChange(aIso(d));
  };

  if (Platform.OS === 'web') {
    return <Campo etiqueta={etiqueta} valor={valor ?? ''} onChange={onChange} error={error} requerido={requerido} placeholder="AAAA-MM-DD" />;
  }

  return (
    <View style={s.campo}>
      <Etiqueta texto={etiqueta} requerido={requerido} />
      <View style={{ flexDirection: 'row', gap: E.s }}>
        <Pressable
          onPress={() => setAbierto((x) => !x)}
          accessibilityRole="button"
          accessibilityLabel={`${etiqueta}: ${valor ? fechaLegible(valor) : 'sin elegir'}`}
          style={[s.input, s.selectorFila, { flex: 1 }, !!error && s.inputError]}>
          <Icon name="event" size={20} color={C.tenue} />
          <Text style={[s.valor, !valor && { color: C.tenue }]}>{valor ? fechaLegible(valor) : 'Elegir fecha'}</Text>
        </Pressable>
        {opcional && !!valor && <Pressable onPress={() => onChange('')} accessibilityRole="button" accessibilityLabel={`Quitar ${etiqueta}`} style={[s.input, s.botonChico]}><Icon name="close" size={20} color={C.tenue} /></Pressable>}
      </View>
      {abierto && (
        <DateTimePicker
          value={desdeIso(valor)}
          mode="date"
          display={Platform.OS === 'ios' ? 'inline' : 'default'}
          onChange={cambiar}
          minimumDate={minimo ? desdeIso(minimo) : undefined}
          maximumDate={maximo ? desdeIso(maximo) : undefined}
          themeVariant="dark"
          locale="es-AR"
        />
      )}
      <ErrorCampo error={error} />
    </View>
  );
}

/** Hora HH:MM con el selector nativo. */
export function CampoHora({ etiqueta, valor, onChange, error, requerido, opcional }: {
  etiqueta: string;
  valor: string | null | undefined;
  onChange: (v: string) => void;
  error?: string;
  requerido?: boolean;
  opcional?: boolean;
}) {
  const [abierto, setAbierto] = useState(false);
  const base = new Date();
  if (valor) {
    const [h, m] = valor.split(':').map(Number);
    base.setHours(h || 0, m || 0, 0, 0);
  }
  if (Platform.OS === 'web') {
    return <Campo etiqueta={etiqueta} valor={valor ?? ''} onChange={onChange} error={error} requerido={requerido} placeholder="HH:MM" />;
  }
  return (
    <View style={s.campo}>
      <Etiqueta texto={etiqueta} requerido={requerido} />
      <View style={{ flexDirection: 'row', gap: E.s }}>
        <Pressable onPress={() => setAbierto((x) => !x)} accessibilityRole="button" accessibilityLabel={`${etiqueta}: ${valor || 'sin elegir'}`} style={[s.input, s.selectorFila, { flex: 1 }, !!error && s.inputError]}>
          <Icon name="schedule" size={20} color={C.tenue} />
          <Text style={[s.valor, !valor && { color: C.tenue }]}>{valor ? valor.slice(0, 5) : 'Elegir hora'}</Text>
        </Pressable>
        {opcional && !!valor && <Pressable onPress={() => onChange('')} accessibilityRole="button" accessibilityLabel={`Quitar ${etiqueta}`} style={[s.input, s.botonChico]}><Icon name="close" size={20} color={C.tenue} /></Pressable>}
      </View>
      {abierto && (
        <DateTimePicker
          value={base}
          mode="time"
          is24Hour
          display={Platform.OS === 'ios' ? 'spinner' : 'default'}
          themeVariant="dark"
          onChange={(e, d) => {
            setAbierto(Platform.OS === 'ios');
            if (e.type === 'set' && d) onChange(`${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`);
          }}
        />
      )}
      <ErrorCampo error={error} />
    </View>
  );
}

export function Interruptor({ etiqueta, valor, onChange, ayuda }: { etiqueta: string; valor: boolean; onChange: (v: boolean) => void; ayuda?: string }) {
  return (
    <Pressable onPress={() => onChange(!valor)} accessibilityRole="switch" accessibilityState={{ checked: valor }} accessibilityLabel={etiqueta} style={s.interruptor}>
      <View style={{ flex: 1 }}>
        <Text style={s.valor}>{etiqueta}</Text>
        {ayuda && <Tenue style={{ fontSize: 13 }}>{ayuda}</Tenue>}
      </View>
      <Switch value={valor} onValueChange={onChange} trackColor={{ true: C.acento, false: C.borde }} thumbColor={C.texto} />
    </Pressable>
  );
}

export interface Opcion<V extends string | number = string> {
  valor: V;
  etiqueta: string;
  detalle?: string;
}

/** Pocas opciones: chips en línea. */
export function Selector<V extends string | number>({ etiqueta, opciones, valor, onChange, error, requerido, permitirVacio }: {
  etiqueta?: string;
  opciones: Opcion<V>[];
  valor: V | null | undefined;
  onChange: (v: V | null) => void;
  error?: string;
  requerido?: boolean;
  permitirVacio?: string;
}) {
  return (
    <View style={s.campo}>
      {etiqueta && <Etiqueta texto={etiqueta} requerido={requerido} />}
      <View style={s.chips} accessibilityRole="radiogroup" accessibilityLabel={etiqueta}>
        {permitirVacio && <Chip etiqueta={permitirVacio} activo={valor === null || valor === undefined || valor === ''} onPress={() => onChange(null)} />}
        {opciones.map((o) => <Chip key={String(o.valor)} etiqueta={o.etiqueta} activo={valor === o.valor} onPress={() => onChange(o.valor)} />)}
      </View>
      <ErrorCampo error={error} />
    </View>
  );
}

/** Selección múltiple con chips. */
export function SelectorMultiple<V extends string | number>({ etiqueta, opciones, valores, onChange, error, ayuda }: {
  etiqueta?: string;
  opciones: Opcion<V>[];
  valores: V[];
  onChange: (v: V[]) => void;
  error?: string;
  ayuda?: string;
}) {
  const alternar = (v: V) => onChange(valores.includes(v) ? valores.filter((x) => x !== v) : [...valores, v]);
  return (
    <View style={s.campo}>
      {etiqueta && <Etiqueta texto={etiqueta} />}
      {ayuda && <Tenue style={{ fontSize: 13 }}>{ayuda}</Tenue>}
      <View style={s.chips}>
        {opciones.map((o) => <Chip key={String(o.valor)} etiqueta={o.etiqueta} activo={valores.includes(o.valor)} onPress={() => alternar(o.valor)} multiple />)}
        {opciones.length === 0 && <Tenue>No hay opciones disponibles.</Tenue>}
      </View>
      <ErrorCampo error={error} />
    </View>
  );
}

export function Chip({ etiqueta, activo, onPress, multiple }: { etiqueta: string; activo: boolean; onPress: () => void; multiple?: boolean }) {
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole={multiple ? 'checkbox' : 'radio'}
      accessibilityState={multiple ? { checked: activo } : { selected: activo }}
      style={[s.chip, activo && s.chipActivo]}>
      {multiple && activo && <Icon name="check" size={16} color={C.texto} />}
      <Text style={s.chipTexto}>{etiqueta}</Text>
    </Pressable>
  );
}

/**
 * Muchas opciones: abre una hoja con búsqueda. Las opciones pueden ser fijas o
 * buscarse en el servidor (`buscar(q)`), p. ej. alumnos o personas.
 */
export function SelectorLista<V extends string | number>({ etiqueta, valor, valorEtiqueta, onChange, opciones, buscar, claveBusqueda, error, requerido, placeholder = 'Elegir…', permitirVacio }: {
  etiqueta: string;
  valor: V | null | undefined;
  /** Texto a mostrar cuando el valor no está en `opciones` (p. ej. al editar). */
  valorEtiqueta?: string | null;
  onChange: (v: V | null, opcion?: Opcion<V>) => void;
  opciones?: Opcion<V>[];
  buscar?: (q: string) => Promise<Opcion<V>[]>;
  /** Clave de caché de la búsqueda remota. */
  claveBusqueda?: string;
  error?: string;
  requerido?: boolean;
  placeholder?: string;
  permitirVacio?: boolean;
}) {
  const [abierto, setAbierto] = useState(false);
  const [texto, setTexto] = useState('');
  const q = useDeferredValue(texto.trim());
  const remoto = useQuery({
    queryKey: ['selector', claveBusqueda ?? etiqueta, q],
    queryFn: () => buscar!(q),
    enabled: abierto && !!buscar,
    staleTime: 60_000,
  });
  const lista = buscar
    ? remoto.data ?? []
    : (opciones ?? []).filter((o) => !q || `${o.etiqueta} ${o.detalle ?? ''}`.toLowerCase().includes(q.toLowerCase()));
  const seleccionada = opciones?.find((o) => o.valor === valor)?.etiqueta ?? valorEtiqueta ?? (valor ? `#${valor}` : null);

  return (
    <View style={s.campo}>
      <Etiqueta texto={etiqueta} requerido={requerido} />
      <View style={{ flexDirection: 'row', gap: E.s }}>
        <Pressable
          onPress={() => setAbierto(true)}
          accessibilityRole="button"
          accessibilityLabel={`${etiqueta}: ${seleccionada ?? 'sin elegir'}`}
          style={[s.input, s.selectorFila, { flex: 1 }, !!error && s.inputError]}>
          <Text style={[s.valor, { flex: 1 }, !seleccionada && { color: C.tenue }]} numberOfLines={1}>{seleccionada ?? placeholder}</Text>
          <Icon name="expand-more" size={22} color={C.tenue} />
        </Pressable>
        {permitirVacio && valor !== null && valor !== undefined && valor !== '' && (
          <Pressable onPress={() => onChange(null)} accessibilityRole="button" accessibilityLabel={`Quitar ${etiqueta}`} style={[s.input, s.botonChico]}><Icon name="close" size={20} color={C.tenue} /></Pressable>
        )}
      </View>
      <ErrorCampo error={error} />
      <Hoja visible={abierto} titulo={etiqueta} onCerrar={() => setAbierto(false)}>
        <TextInput style={s.input} value={texto} onChangeText={setTexto} placeholder="Buscar…" placeholderTextColor={C.tenue} autoFocus={!!buscar} accessibilityLabel={`Buscar ${etiqueta}`} autoCorrect={false} />
        {remoto.isFetching && <ActivityIndicator color={C.acento} />}
        {remoto.isError && <Aviso tono="peligro" texto={(remoto.error as Error).message} />}
        <FlatList
          data={lista}
          keyExtractor={(o) => String(o.valor)}
          keyboardShouldPersistTaps="handled"
          style={{ maxHeight: 420 }}
          ListEmptyComponent={!remoto.isFetching ? <Tenue style={{ textAlign: 'center', padding: E.l }}>{buscar && q.length < 2 ? 'Escribí al menos 2 letras.' : 'Sin resultados.'}</Tenue> : null}
          renderItem={({ item }) => (
            <Pressable
              onPress={() => { onChange(item.valor, item); setAbierto(false); setTexto(''); }}
              accessibilityRole="button"
              accessibilityState={{ selected: item.valor === valor }}
              style={({ pressed }) => [s.opcionLista, pressed && { opacity: 0.7 }, item.valor === valor && { borderColor: C.acento }]}>
              <View style={{ flex: 1 }}>
                <Text style={s.valor}>{item.etiqueta}</Text>
                {item.detalle && <Tenue style={{ fontSize: 13 }}>{item.detalle}</Tenue>}
              </View>
              {item.valor === valor && <Icon name="check" size={22} color={C.acento} />}
            </Pressable>
          )}
        />
      </Hoja>
    </View>
  );
}

/** Adjuntar un archivo (PDF o imagen) desde documentos, galería o cámara. */
export function CampoArchivo({ etiqueta, archivo, onChange, error, requerido, ayuda, maxMb = 10, tipos, soloImagen, actual }: {
  etiqueta: string;
  archivo: ArchivoLocal | null;
  onChange: (a: ArchivoLocal | null) => void;
  error?: string;
  requerido?: boolean;
  ayuda?: string;
  maxMb?: number;
  tipos?: string[];
  soloImagen?: boolean;
  /** Nombre del archivo ya cargado (al editar). */
  actual?: string | null;
}) {
  const [errorLocal, setErrorLocal] = useState<string | null>(null);
  const tomar = async (fn: () => Promise<ArchivoLocal | null>) => {
    setErrorLocal(null);
    try {
      const a = await fn();
      if (!a) return;
      if (a.tamano && a.tamano > maxMb * MB) {
        setErrorLocal(`El archivo pesa ${(a.tamano / MB).toFixed(1)} MB. Máximo ${maxMb} MB.`);
        return;
      }
      onChange(a);
    } catch (e) {
      setErrorLocal(e instanceof Error ? e.message : 'No se pudo abrir el archivo.');
    }
  };

  return (
    <View style={s.campo}>
      <Etiqueta texto={etiqueta} requerido={requerido} />
      {archivo ? (
        <View style={[s.input, s.selectorFila]}>
          <Icon name={archivo.mime.startsWith('image/') ? 'image' : 'description'} size={22} color={C.acento} />
          <Text style={[s.valor, { flex: 1 }]} numberOfLines={1}>{archivo.nombre}</Text>
          <Pressable onPress={() => onChange(null)} accessibilityRole="button" accessibilityLabel="Quitar archivo" hitSlop={10}><Icon name="close" size={22} color={C.tenue} /></Pressable>
        </View>
      ) : (
        <>
          {actual && <Tenue>Actual: {actual}</Tenue>}
          <View style={{ flexDirection: 'row', gap: E.s, flexWrap: 'wrap' }}>
            {!soloImagen && <View style={{ flexGrow: 1 }}><Boton titulo="Documento" icono="attach-file" variante="secundario" onPress={() => tomar(() => elegirDocumento(tipos))} /></View>}
            <View style={{ flexGrow: 1 }}><Boton titulo="Galería" icono="photo-library" variante="secundario" onPress={() => tomar(() => elegirImagen('galeria'))} /></View>
            {Platform.OS !== 'web' && <View style={{ flexGrow: 1 }}><Boton titulo="Cámara" icono="photo-camera" variante="secundario" onPress={() => tomar(() => elegirImagen('camara'))} /></View>}
          </View>
        </>
      )}
      {ayuda && <Tenue style={{ fontSize: 13 }}>{ayuda}</Tenue>}
      <ErrorCampo error={errorLocal ?? error} />
    </View>
  );
}

/** Barra de progreso de subida/descarga. */
export function Progreso({ fraccion, texto }: { fraccion: number; texto?: string }) {
  const pct = Math.round(Math.min(1, Math.max(0, fraccion)) * 100);
  return (
    <View style={{ gap: E.xs }} accessibilityRole="progressbar" accessibilityValue={{ min: 0, max: 100, now: pct }}>
      <View style={s.barra}><View style={[s.barraRelleno, { width: `${pct}%` }]} /></View>
      <Tenue style={{ fontSize: 13 }}>{texto ?? 'Subiendo…'} {pct}%</Tenue>
    </View>
  );
}

const s = StyleSheet.create({
  seccion: { backgroundColor: C.superficie, borderRadius: 14, borderWidth: 1, borderColor: C.borde, padding: E.l, gap: E.m },
  seccionTitulo: { color: C.texto, fontSize: 16, fontWeight: '800' },
  campo: { gap: E.xs },
  etiqueta: { color: C.tenue, fontSize: 14, fontWeight: '600' },
  input: { backgroundColor: C.fondo, borderRadius: 12, borderWidth: 1, borderColor: C.borde, color: C.texto, fontSize: 16, paddingHorizontal: E.m, minHeight: TOQUE },
  inputError: { borderColor: C.peligro },
  multilinea: { minHeight: 96, textAlignVertical: 'top', paddingTop: E.m },
  error: { color: C.peligro, fontSize: 13, fontWeight: '600' },
  valor: { color: C.texto, fontSize: 16 },
  selectorFila: { flexDirection: 'row', alignItems: 'center', gap: E.s },
  botonChico: { width: TOQUE, alignItems: 'center', justifyContent: 'center' },
  interruptor: { flexDirection: 'row', alignItems: 'center', gap: E.m, minHeight: TOQUE },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: E.s },
  chip: { flexDirection: 'row', alignItems: 'center', gap: 4, borderRadius: 999, borderWidth: 1, borderColor: C.borde, backgroundColor: C.fondo, paddingHorizontal: E.l, minHeight: 44 },
  chipActivo: { backgroundColor: C.acento, borderColor: C.acento },
  chipTexto: { color: C.texto, fontWeight: '600' },
  opcionLista: { flexDirection: 'row', alignItems: 'center', gap: E.m, padding: E.m, minHeight: 56, borderRadius: 12, borderWidth: 1, borderColor: C.borde, marginBottom: E.s, backgroundColor: C.fondo },
  barra: { height: 8, borderRadius: 4, backgroundColor: C.superficie2, overflow: 'hidden' },
  barraRelleno: { height: 8, backgroundColor: C.acento },
});
