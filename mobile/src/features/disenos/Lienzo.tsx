import { Image } from 'expo-image';
import { PanResponder, Pressable, Text, View } from 'react-native';

import { C } from '@/lib/theme';

/** Objeto del lienzo (formato fabric.js que guarda el editor web). */
export interface ObjetoLienzo {
  type: string;
  left?: number;
  top?: number;
  width?: number;
  height?: number;
  radius?: number;
  scaleX?: number;
  scaleY?: number;
  angle?: number;
  opacity?: number;
  fill?: string | null;
  text?: string;
  fontSize?: number;
  fontFamily?: string;
  fontWeight?: string | number;
  textAlign?: string;
  lineHeight?: number;
  src?: string;
  originX?: string;
  originY?: string;
  [k: string]: unknown;
}

export interface CanvasJson {
  version?: string;
  background?: string;
  objects?: ObjetoLienzo[];
  [k: string]: unknown;
}

export function leerCanvas(json: string | null | undefined): CanvasJson {
  try {
    const c = JSON.parse(json || '{}');
    return c && typeof c === 'object' ? c : { objects: [] };
  } catch {
    return { objects: [] };
  }
}

export const esTexto = (o: ObjetoLienzo) => ['textbox', 'i-text', 'text'].includes(o.type);

/**
 * Vista previa nativa del lienzo, escalada al ancho disponible. Es una aproximación
 * (tipografías y efectos pueden variar respecto del editor web) pensada para revisar
 * y editar el contenido desde el teléfono. Tocar un objeto lo selecciona.
 */
export function Lienzo({ canvas, ancho, alto, anchoVista, seleccionado, onSeleccionar, onMover }: {
  canvas: CanvasJson;
  ancho: number;
  alto: number;
  anchoVista: number;
  seleccionado?: number | null;
  onSeleccionar?: (indice: number) => void;
  /** Arrastre en coordenadas del lienzo (px del diseño, no de la pantalla). */
  onMover?: (indice: number, left: number, top: number) => void;
}) {
  const k = anchoVista / Math.max(1, ancho);
  return (
    <View
      style={{ width: anchoVista, height: alto * k, backgroundColor: canvas.background || '#ffffff', overflow: 'hidden', borderRadius: 8 }}
      accessibilityLabel="Vista previa del diseño">
      {(canvas.objects ?? []).map((o, i) => {
        const sx = o.scaleX ?? 1;
        const sy = o.scaleY ?? 1;
        const w = (o.type === 'circle' ? (o.radius ?? 0) * 2 : o.width ?? (o.type === 'image' ? 400 : 100)) * sx;
        const h = (o.type === 'circle' ? (o.radius ?? 0) * 2 : o.height ?? (esTexto(o) ? (o.fontSize ?? 40) * (o.lineHeight ?? 1.16) * Math.max(1, String(o.text ?? '').split('\n').length) : o.type === 'image' ? 400 : 100)) * sy;
        let left = o.left ?? 0;
        let top = o.top ?? 0;
        if (o.originX === 'center') left -= w / 2;
        if (o.originY === 'center') top -= h / 2;
        const caja = {
          position: 'absolute' as const,
          left: left * k,
          top: top * k,
          width: w * k,
          height: esTexto(o) ? undefined : h * k,
          opacity: o.opacity ?? 1,
          transform: o.angle ? [{ rotate: `${o.angle}deg` }] : undefined,
          borderWidth: seleccionado === i ? 2 : 0,
          borderColor: C.acento,
          borderStyle: 'dashed' as const,
        };
        let contenido = null;
        if (o.type === 'rect') contenido = <View style={{ flex: 1, backgroundColor: o.fill || 'transparent' }} />;
        else if (o.type === 'circle') contenido = <View style={{ flex: 1, backgroundColor: o.fill || 'transparent', borderRadius: (w * k) / 2 }} />;
        else if (o.type === 'image' && o.src) contenido = <Image source={{ uri: o.src }} style={{ flex: 1 }} contentFit="contain" />;
        else if (esTexto(o)) {
          contenido = (
            <Text
              style={{
                color: typeof o.fill === 'string' ? o.fill : '#000',
                fontSize: (o.fontSize ?? 40) * k * sy,
                fontWeight: String(o.fontWeight ?? 'normal') as 'normal',
                textAlign: (o.textAlign as 'left' | 'center' | 'right') ?? 'left',
                lineHeight: (o.fontSize ?? 40) * (o.lineHeight ?? 1.16) * k * sy,
              }}>
              {o.text}
            </Text>
          );
        }
        if (!contenido) return null;
        const gesto = onMover ? PanResponder.create({
          onStartShouldSetPanResponder: () => true,
          onPanResponderGrant: () => onSeleccionar?.(i),
          onPanResponderRelease: (_, g) => onMover(i, (o.left ?? 0) + g.dx / k, (o.top ?? 0) + g.dy / k),
        }) : null;
        return onSeleccionar ? (
          <View key={i} {...(gesto ? gesto.panHandlers : {})} style={caja}>
            <Pressable onPress={() => onSeleccionar(i)} accessibilityRole="button" accessibilityLabel={esTexto(o) ? `Texto: ${o.text}` : `Elemento ${o.type}`} style={{ flex: 1 }}>
              {contenido}
            </Pressable>
          </View>
        ) : <View key={i} style={caja}>{contenido}</View>;
      })}
    </View>
  );
}

const escapar = (t: string) => t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br/>');

/** HTML de la pieza en tamaño real (px) para exportarla a PDF desde el teléfono. */
export function htmlDelLienzo(canvas: CanvasJson, ancho: number, alto: number): string {
  const partes = (canvas.objects ?? []).map((o) => {
    const sx = o.scaleX ?? 1;
    const sy = o.scaleY ?? 1;
    const w = (o.type === 'circle' ? (o.radius ?? 0) * 2 : o.width ?? 400) * sx;
    const h = (o.type === 'circle' ? (o.radius ?? 0) * 2 : o.height ?? 400) * sy;
    let left = o.left ?? 0;
    let top = o.top ?? 0;
    if (o.originX === 'center') left -= w / 2;
    if (o.originY === 'center') top -= h / 2;
    const base = `position:absolute;left:${left}px;top:${top}px;width:${w}px;opacity:${o.opacity ?? 1};${o.angle ? `transform:rotate(${o.angle}deg);transform-origin:top left;` : ''}`;
    if (o.type === 'rect') return `<div style="${base}height:${h}px;background:${o.fill || 'transparent'}"></div>`;
    if (o.type === 'circle') return `<div style="${base}height:${h}px;border-radius:50%;background:${o.fill || 'transparent'}"></div>`;
    if (o.type === 'image' && o.src) return `<img src="${o.src}" style="${base}${o.height ? `height:${h}px;` : ''}object-fit:contain"/>`;
    if (esTexto(o)) {
      return `<div style="${base}color:${typeof o.fill === 'string' ? o.fill : '#000'};font-size:${(o.fontSize ?? 40) * sy}px;font-family:${o.fontFamily ?? 'sans-serif'};font-weight:${o.fontWeight ?? 'normal'};text-align:${o.textAlign ?? 'left'};line-height:${o.lineHeight ?? 1.16}">${escapar(String(o.text ?? ''))}</div>`;
    }
    return '';
  });
  return `<!doctype html><html><head><meta charset="utf-8"><style>@page{size:${ancho}px ${alto}px;margin:0}html,body{margin:0;padding:0}</style></head><body><div style="position:relative;width:${ancho}px;height:${alto}px;overflow:hidden;background:${canvas.background || '#fff'}">${partes.join('')}</div></body></html>`;
}
