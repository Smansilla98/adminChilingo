import { useQuery, useQueryClient } from '@tanstack/react-query';
import { router, Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Image as RNImage, Pressable, ScrollView, StyleSheet, TextInput, useWindowDimensions, View } from 'react-native';

import { confirmar, Hoja, useToast } from '@/components/feedback';
import { Campo, Chip as ChipSel, Interruptor, Progreso } from '@/components/form';
import { Acciones, Aviso, Boton, Cargando, ErrorVista, Fila, Icon, Pantalla, Segmentos, Subtitulo, Tenue, Texto } from '@/components/ui';
import { type CanvasJson, esTexto, htmlDelLienzo, leerCanvas, Lienzo, type ObjetoLienzo } from '@/features/disenos/Lienzo';
import { type Diseno, type GrupoMarca, type PaginaDiseno, PALETA } from '@/features/disenos/tipos';
import { api } from '@/lib/api';
import { compartirPdf, descargarYAbrir, elegirImagen, subirArchivo } from '@/lib/archivos';
import { useOperacion } from '@/lib/recursos';
import { C, E, TOQUE } from '@/lib/theme';

type Ficha = Diseno & { pages: PaginaDiseno[] };

const tamanoImagen = (url: string) => new Promise<{ w: number; h: number }>((ok) => RNImage.getSize(url, (w, h) => ok({ w, h }), () => ok({ w: 800, h: 800 })));

/**
 * Editor de diseño adaptado al teléfono: se edita el contenido de cada pieza
 * (textos, colores, imágenes, orden y tamaño de los elementos) sobre una vista previa
 * nativa, con páginas, recursos de marca y exportación a PDF. Guarda el mismo
 * formato que el editor web, así la pieza se sigue abriendo en ambos.
 */
export default function EditorDiseno() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const qc = useQueryClient();
  const avisar = useToast();
  const { width } = useWindowDimensions();
  const q = useQuery({ queryKey: ['disenos', 'detalle', id], queryFn: () => api<Ficha>(`disenos/${id}`) });
  const [paginaId, setPaginaId] = useState<string | null>(null);
  const [borradores, setBorradores] = useState<Record<string, CanvasJson>>({});
  const [sel, setSel] = useState<number | null>(null);
  const [hoja, setHoja] = useState<'objeto' | 'marca' | 'nombre' | 'pagina' | null>(null);
  const [subiendo, setSubiendo] = useState<number | null>(null);
  const [guardando, setGuardando] = useState(false);

  const refrescar = () => qc.invalidateQueries({ queryKey: ['disenos'] });
  const renombrar = useOperacion((name: string) => api(`disenos/${id}`, { method: 'PUT', body: { name } }), { exito: 'Nombre actualizado', invalidar: ['disenos'], alTerminar: () => setHoja(null) });
  const eliminar = useOperacion(() => api(`disenos/${id}`, { method: 'DELETE' }), { exito: 'Diseño eliminado', invalidar: ['disenos'], alTerminar: () => router.back() });

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const d = q.data;
  const paginas = [...d.pages].sort((a, b) => a.sort_order - b.sort_order);
  const pagina = paginas.find((p) => p.id === paginaId) ?? paginas[0];
  const canvas = borradores[pagina.id] ?? leerCanvas(pagina.canvas_json);
  const objetos = canvas.objects ?? [];
  const objeto = sel !== null ? objetos[sel] : undefined;
  const sucio = Object.keys(borradores).length > 0;
  const anchoVista = Math.min(width, 700) - E.l * 2;

  const cambiarCanvas = (c: CanvasJson) => setBorradores((b) => ({ ...b, [pagina.id]: c }));
  const cambiarObjeto = (i: number, parcial: Partial<ObjetoLienzo>) => cambiarCanvas({ ...canvas, objects: objetos.map((o, n) => (n === i ? { ...o, ...parcial } : o)) });
  const agregar = (o: ObjetoLienzo) => {
    cambiarCanvas({ ...canvas, objects: [...objetos, o] });
    setSel(objetos.length);
    setHoja('objeto');
  };

  const guardar = async () => {
    setGuardando(true);
    try {
      for (const [pid, c] of Object.entries(borradores)) {
        await api(`disenos/paginas/${pid}`, { method: 'PUT', body: { canvas_json: JSON.stringify(c) } });
      }
      setBorradores({});
      await refrescar();
      avisar('Diseño guardado');
    } catch (e) {
      avisar(e instanceof Error ? e.message : 'No se pudo guardar.', 'error');
    } finally {
      setGuardando(false);
    }
  };

  const operarPagina = async (accion: 'agregar' | 'duplicar' | 'eliminar') => {
    try {
      if (accion === 'agregar') await api(`disenos/${id}/paginas`, { method: 'POST', body: { canvas_json: '{}', after_sort_order: pagina.sort_order } });
      if (accion === 'duplicar') await api(`disenos/paginas/${pagina.id}/duplicar`, { method: 'POST' });
      if (accion === 'eliminar') {
        if (!(await confirmar({ titulo: `¿Eliminar "${pagina.title}"?`, accion: 'Eliminar' }))) return;
        await api(`disenos/paginas/${pagina.id}`, { method: 'DELETE' });
        setPaginaId(null);
      }
      await refrescar();
      avisar(accion === 'agregar' ? 'Página agregada' : accion === 'duplicar' ? 'Página duplicada' : 'Página eliminada');
    } catch (e) {
      avisar(e instanceof Error ? e.message : 'No se pudo completar.', 'error');
    }
  };

  /** Copia completa (todas las páginas) como un diseño nuevo. */
  const duplicarDiseno = async () => {
    try {
      const nuevo = await api<Diseno>('disenos', { method: 'POST', body: { name: `${d.name} (copia)`, canvas_json: paginas[0].canvas_json, width: d.width, height: d.height } });
      for (const p of paginas.slice(1)) {
        await api(`disenos/${nuevo.id}/paginas`, { method: 'POST', body: { title: p.title, canvas_json: p.canvas_json } });
      }
      await refrescar();
      avisar('Diseño duplicado');
      router.replace({ pathname: '/disenos/[id]', params: { id: nuevo.id } } as never);
    } catch (e) {
      avisar(e instanceof Error ? e.message : 'No se pudo duplicar.', 'error');
    }
  };

  const subirYAgregar = async (reemplazar?: number) => {
    try {
      const img = await elegirImagen('galeria');
      if (!img) return;
      setSubiendo(0);
      const { url } = await subirArchivo<{ url: string }>('disenos/imagenes', img, { campo: 'file', onProgreso: setSubiendo });
      const w = img.ancho ?? 800;
      const escala = (d.width * 0.6) / w;
      if (reemplazar !== undefined) cambiarObjeto(reemplazar, { src: url });
      else agregar({ type: 'image', src: url, left: d.width * 0.2, top: d.height * 0.2, width: w, height: img.alto ?? w, scaleX: escala, scaleY: escala });
    } catch (e) {
      avisar(e instanceof Error ? e.message : 'No se pudo subir la imagen.', 'error');
    } finally {
      setSubiendo(null);
    }
  };

  const agregarDeMarca = async (url: string) => {
    const { w, h } = await tamanoImagen(url);
    const escala = (d.width * 0.4) / w;
    setHoja(null);
    agregar({ type: 'image', src: url, left: d.width * 0.3, top: d.height * 0.05, width: w, height: h, scaleX: escala, scaleY: escala });
  };

  return (
    <Pantalla>
      <Stack.Screen options={{ title: d.name }} />
      <Segmentos opciones={paginas.map((p, n) => ({ valor: p.id, etiqueta: p.title || `Página ${n + 1}` }))} valor={pagina.id} onChange={(v) => { setPaginaId(v); setSel(null); }} />
      <Lienzo canvas={canvas} ancho={d.width} alto={d.height} anchoVista={anchoVista} seleccionado={sel} onSeleccionar={(i) => { setSel(i); setHoja('objeto'); }} />
      <Tenue style={{ fontSize: 12 }}>Vista previa aproximada. Tocá un elemento para editarlo.</Tenue>
      {subiendo !== null && <Progreso fraccion={subiendo} texto="Subiendo imagen…" />}
      {sucio && <Aviso tono="alerta" texto="Tenés cambios sin guardar." />}
      <Acciones>
        <Boton titulo="Guardar" icono="save" cargando={guardando} deshabilitado={!sucio} onPress={() => void guardar()} />
        <Boton titulo="Texto" icono="title" variante="secundario" onPress={() => agregar({ type: 'textbox', text: 'Texto nuevo', left: d.width * 0.1, top: d.height * 0.4, width: d.width * 0.8, fontSize: Math.round(d.width / 14), fill: '#ffffff', fontFamily: 'sans-serif', textAlign: 'center' })} />
        <Boton titulo="Imagen" icono="add-photo-alternate" variante="secundario" onPress={() => void subirYAgregar()} />
        <Boton titulo="Marca" icono="collections" variante="secundario" onPress={() => setHoja('marca')} />
        <Boton titulo="Forma" icono="crop-square" variante="secundario" onPress={() => agregar({ type: 'rect', left: d.width * 0.25, top: d.height * 0.25, width: d.width * 0.5, height: d.height * 0.2, fill: '#f26422' })} />
      </Acciones>

      <Subtitulo>Elementos</Subtitulo>
      {objetos.length === 0 && <Tenue>Página vacía: agregá texto, imágenes o formas.</Tenue>}
      {objetos.map((o, i) => (
        <Pressable key={i} onPress={() => { setSel(i); setHoja('objeto'); }} accessibilityRole="button" style={[s.capa, sel === i && { borderColor: C.acento }]}>
          <Icon name={esTexto(o) ? 'title' : o.type === 'image' ? 'image' : o.type === 'circle' ? 'circle' : 'crop-square'} size={20} color={C.tenue} />
          <Texto style={{ flex: 1 }} >{esTexto(o) ? String(o.text ?? '').slice(0, 40) : o.type === 'image' ? 'Imagen' : `Forma ${o.fill ?? ''}`}</Texto>
          <Icon name="chevron-right" size={20} color={C.tenue} />
        </Pressable>
      ))}

      <Subtitulo>Página y diseño</Subtitulo>
      <Acciones>
        <Boton titulo="Nueva página" icono="note-add" variante="secundario" onPress={() => void operarPagina('agregar')} />
        <Boton titulo="Duplicar página" icono="file-copy" variante="secundario" onPress={() => void operarPagina('duplicar')} />
        <Boton titulo="Renombrar página" icono="drive-file-rename-outline" variante="secundario" onPress={() => setHoja('pagina')} />
        {paginas.length > 1 && <Boton titulo="Eliminar página" icono="delete-outline" variante="secundario" onPress={() => void operarPagina('eliminar')} />}
        <Boton titulo="Exportar PDF" icono="picture-as-pdf" variante="secundario" onPress={() => void compartirPdf(htmlDelLienzo(canvas, d.width, d.height), `${d.name}.pdf`, { ancho: d.width, alto: d.height }).catch((e) => avisar((e as Error).message, 'error'))} />
        {d.thumbnail_url && <Boton titulo="Compartir imagen" icono="share" variante="secundario" onPress={() => void descargarYAbrir(d.thumbnail_url!, { nombre: `${d.name}.png` }).catch((e) => avisar((e as Error).message, 'error'))} />}
        <Boton titulo="Renombrar" icono="edit" variante="secundario" onPress={() => setHoja('nombre')} />
        <Boton titulo="Duplicar diseño" icono="copy-all" variante="secundario" onPress={() => void duplicarDiseno()} />
        <Boton titulo="Eliminar diseño" icono="delete" variante="peligro" cargando={eliminar.isPending} onPress={async () => { if (await confirmar({ titulo: `¿Eliminar "${d.name}"?`, mensaje: 'Se borran todas sus páginas.', accion: 'Eliminar' })) eliminar.mutate(); }} />
      </Acciones>
      {!d.thumbnail_url && <Tenue style={{ fontSize: 12 }}>La imagen para compartir se genera al abrir el diseño en el editor completo; mientras tanto podés exportarlo en PDF.</Tenue>}

      <Hoja visible={hoja === 'objeto' && !!objeto} titulo={objeto && esTexto(objeto) ? 'Texto' : objeto?.type === 'image' ? 'Imagen' : 'Forma'} onCerrar={() => setHoja(null)}>
        {objeto && sel !== null && (
          <EditorObjeto
            o={objeto}
            anchoLienzo={d.width}
            onCambiar={(p) => cambiarObjeto(sel, p)}
            onReemplazarImagen={() => void subirYAgregar(sel)}
            onOrden={(dir) => {
              const lista = [...objetos];
              const [x] = lista.splice(sel, 1);
              const destino = dir === 'frente' ? lista.length : 0;
              lista.splice(destino, 0, x);
              cambiarCanvas({ ...canvas, objects: lista });
              setSel(destino);
            }}
            onDuplicar={() => agregar({ ...objeto, left: (objeto.left ?? 0) + 30, top: (objeto.top ?? 0) + 30 })}
            onEliminar={() => { cambiarCanvas({ ...canvas, objects: objetos.filter((_, n) => n !== sel) }); setSel(null); setHoja(null); }}
          />
        )}
      </Hoja>
      <Hoja visible={hoja === 'marca'} titulo="Recursos de marca" onCerrar={() => setHoja(null)}>
        {hoja === 'marca' && <RecursosMarca onElegir={(u) => void agregarDeMarca(u)} />}
      </Hoja>
      <Hoja visible={hoja === 'nombre'} titulo="Nombre del diseño" onCerrar={() => setHoja(null)}>
        {hoja === 'nombre' && <CampoYGuardar inicial={d.name} cargando={renombrar.isPending} onGuardar={(n) => renombrar.mutate(n)} />}
      </Hoja>
      <Hoja visible={hoja === 'pagina'} titulo="Nombre de la página" onCerrar={() => setHoja(null)}>
        {hoja === 'pagina' && (
          <CampoYGuardar inicial={pagina.title} onGuardar={async (t) => {
            try {
              await api(`disenos/paginas/${pagina.id}`, { method: 'PUT', body: { title: t } });
              await refrescar();
              setHoja(null);
            } catch (e) {
              avisar((e as Error).message, 'error');
            }
          }} />
        )}
      </Hoja>
    </Pantalla>
  );
}

function EditorObjeto({ o, anchoLienzo, onCambiar, onReemplazarImagen, onOrden, onDuplicar, onEliminar }: {
  o: ObjetoLienzo;
  anchoLienzo: number;
  onCambiar: (p: Partial<ObjetoLienzo>) => void;
  onReemplazarImagen: () => void;
  onOrden: (d: 'frente' | 'fondo') => void;
  onDuplicar: () => void;
  onEliminar: () => void;
}) {
  const paso = Math.round(anchoLienzo / 40);
  const escalar = (f: number) => onCambiar({ scaleX: (o.scaleX ?? 1) * f, scaleY: (o.scaleY ?? 1) * f });
  return (
    <ScrollView style={{ maxHeight: 520 }} contentContainerStyle={{ gap: E.m }} keyboardShouldPersistTaps="handled">
      {esTexto(o) && (
        <>
          <Campo etiqueta="Texto" valor={String(o.text ?? '')} onChange={(t) => onCambiar({ text: t })} multilinea />
          <Fila>
            <Boton titulo="A−" variante="secundario" onPress={() => onCambiar({ fontSize: Math.max(8, Math.round((o.fontSize ?? 40) * 0.9)) })} />
            <Texto style={{ flex: 1, textAlign: 'center' }}>{Math.round(o.fontSize ?? 40)} px</Texto>
            <Boton titulo="A+" variante="secundario" onPress={() => onCambiar({ fontSize: Math.round((o.fontSize ?? 40) * 1.1) })} />
          </Fila>
          <View style={{ flexDirection: 'row', gap: E.s, flexWrap: 'wrap' }}>
            {(['left', 'center', 'right'] as const).map((a) => <ChipSel key={a} etiqueta={a === 'left' ? 'Izquierda' : a === 'center' ? 'Centro' : 'Derecha'} activo={(o.textAlign ?? 'left') === a} onPress={() => onCambiar({ textAlign: a })} />)}
          </View>
          <Interruptor etiqueta="Negrita" valor={String(o.fontWeight ?? '') === 'bold' || Number(o.fontWeight) >= 600} onChange={(b) => onCambiar({ fontWeight: b ? 'bold' : 'normal' })} />
        </>
      )}
      {o.type !== 'image' && <SelectorColor valor={typeof o.fill === 'string' ? o.fill : '#000000'} onChange={(c) => onCambiar({ fill: c })} />}
      {o.type === 'image' && <Boton titulo="Reemplazar imagen" icono="image" variante="secundario" onPress={onReemplazarImagen} />}
      <Tenue>Posición y tamaño</Tenue>
      <View style={s.cruz}>
        <View />
        <Mover icono="keyboard-arrow-up" etiqueta="Subir" onPress={() => onCambiar({ top: (o.top ?? 0) - paso })} />
        <View />
        <Mover icono="keyboard-arrow-left" etiqueta="Izquierda" onPress={() => onCambiar({ left: (o.left ?? 0) - paso })} />
        <View />
        <Mover icono="keyboard-arrow-right" etiqueta="Derecha" onPress={() => onCambiar({ left: (o.left ?? 0) + paso })} />
        <View />
        <Mover icono="keyboard-arrow-down" etiqueta="Bajar" onPress={() => onCambiar({ top: (o.top ?? 0) + paso })} />
        <View />
      </View>
      <Fila>
        <View style={{ flex: 1 }}><Boton titulo="Achicar" icono="zoom-out" variante="secundario" onPress={() => (esTexto(o) ? onCambiar({ width: (o.width ?? 300) * 0.9 }) : escalar(0.9))} /></View>
        <View style={{ flex: 1 }}><Boton titulo="Agrandar" icono="zoom-in" variante="secundario" onPress={() => (esTexto(o) ? onCambiar({ width: (o.width ?? 300) * 1.1 }) : escalar(1.1))} /></View>
      </Fila>
      <Acciones>
        <Boton titulo="Al frente" icono="flip-to-front" variante="secundario" onPress={() => onOrden('frente')} />
        <Boton titulo="Al fondo" icono="flip-to-back" variante="secundario" onPress={() => onOrden('fondo')} />
        <Boton titulo="Duplicar" icono="content-copy" variante="secundario" onPress={onDuplicar} />
        <Boton titulo="Quitar" icono="delete" variante="peligro" onPress={onEliminar} />
      </Acciones>
    </ScrollView>
  );
}

function Mover({ icono, etiqueta, onPress }: { icono: 'keyboard-arrow-up' | 'keyboard-arrow-down' | 'keyboard-arrow-left' | 'keyboard-arrow-right'; etiqueta: string; onPress: () => void }) {
  return (
    <Pressable onPress={onPress} accessibilityRole="button" accessibilityLabel={etiqueta} style={({ pressed }) => [s.mover, pressed && { opacity: 0.6 }]}>
      <Icon name={icono} size={28} color={C.texto} />
    </Pressable>
  );
}

function SelectorColor({ valor, onChange }: { valor: string; onChange: (c: string) => void }) {
  const [hex, setHex] = useState(valor);
  return (
    <View style={{ gap: E.s }}>
      <Tenue>Color</Tenue>
      <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: E.s }}>
        {PALETA.map((c) => (
          <Pressable key={c} onPress={() => { setHex(c); onChange(c); }} accessibilityRole="radio" accessibilityState={{ selected: valor.toLowerCase() === c }} accessibilityLabel={`Color ${c}`}
            style={[s.color, { backgroundColor: c }, valor.toLowerCase() === c && { borderColor: C.acento, borderWidth: 3 }]} />
        ))}
      </View>
      <TextInput style={s.hex} value={hex} onChangeText={(t) => { setHex(t); if (/^#[0-9a-f]{6}$/i.test(t)) onChange(t); }} autoCapitalize="none" accessibilityLabel="Color en hexadecimal" placeholder="#rrggbb" placeholderTextColor={C.tenue} />
    </View>
  );
}

function RecursosMarca({ onElegir }: { onElegir: (url: string) => void }) {
  const q = useQuery({ queryKey: ['disenos', 'marca'], queryFn: () => api<{ grupos: GrupoMarca[] }>('disenos/marca').then((r) => r.grupos) });
  if (q.isPending) return <Tenue>Cargando recursos…</Tenue>;
  if (q.isError) return <Tenue>{(q.error as Error).message}</Tenue>;
  return (
    <ScrollView style={{ maxHeight: 480 }}>
      {q.data.filter((g) => g.items.length).map((g) => (
        <View key={g.clave} style={{ gap: E.s, marginBottom: E.m }}>
          <Texto style={{ fontWeight: '800' }}>{g.titulo}</Texto>
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: E.s }}>
            {g.items.map((it) => (
              <Pressable key={it.id} onPress={() => onElegir(it.url)} accessibilityRole="button" accessibilityLabel={`Agregar ${it.label}`} style={s.recurso}>
                <RNImage source={{ uri: it.url }} style={{ width: '100%', height: '100%' }} resizeMode="contain" />
              </Pressable>
            ))}
          </View>
        </View>
      ))}
    </ScrollView>
  );
}

function CampoYGuardar({ inicial, cargando, onGuardar }: { inicial: string; cargando?: boolean; onGuardar: (v: string) => void }) {
  const [v, setV] = useState(inicial);
  return (
    <>
      <Campo etiqueta="Nombre" valor={v} onChange={setV} />
      <Boton titulo="Guardar" icono="save" cargando={cargando} deshabilitado={!v.trim()} onPress={() => onGuardar(v.trim())} />
    </>
  );
}

const s = StyleSheet.create({
  capa: { flexDirection: 'row', alignItems: 'center', gap: E.s, minHeight: TOQUE, backgroundColor: C.superficie, borderRadius: 10, borderWidth: 1, borderColor: C.borde, paddingHorizontal: E.m },
  cruz: { flexDirection: 'row', flexWrap: 'wrap', width: TOQUE * 3 + E.s * 2, gap: E.s, alignSelf: 'center' },
  mover: { width: TOQUE, height: TOQUE, borderRadius: 10, backgroundColor: C.superficie2, alignItems: 'center', justifyContent: 'center' },
  color: { width: 40, height: 40, borderRadius: 20, borderWidth: 1, borderColor: C.borde },
  hex: { backgroundColor: C.fondo, borderRadius: 10, borderWidth: 1, borderColor: C.borde, color: C.texto, paddingHorizontal: E.m, minHeight: TOQUE },
  recurso: { width: 80, height: 80, borderRadius: 10, backgroundColor: C.superficie2, padding: 6 },
});
