import { Image } from 'expo-image';
import { StyleSheet, Text, View } from 'react-native';

import { Tenue } from '@/components/ui';
import { headersSesion } from '@/lib/api';
import { C, E } from '@/lib/theme';

import { COLOR_ESTADO, type FotoResumen } from './tipos';

/**
 * Imagen del archivo. Lo publicado es público; lo demás viaja por /api/v1 con token,
 * por eso siempre se mandan los headers de la sesión.
 */
export function ImagenArchivo({ foto, ancho = 'media', style, ajuste = 'cover' }: {
  foto: Pick<FotoResumen, 'imagen' | 'alt' | 'color' | 'titulo'>;
  ancho?: 'chica' | 'media' | 'grande' | 'completa';
  style?: object;
  ajuste?: 'cover' | 'contain';
}) {
  return (
    <Image
      source={{ uri: foto.imagen[ancho], headers: headersSesion() }}
      style={[{ backgroundColor: foto.color ?? C.superficie2 }, style]}
      contentFit={ajuste}
      transition={200}
      accessibilityLabel={foto.alt || foto.titulo}
    />
  );
}

/** Fila de lista con miniatura, año, título y estado. */
export function FilaFoto({ foto, detalle }: { foto: FotoResumen; detalle?: string | null }) {
  return (
    <View style={s.fila}>
      <ImagenArchivo foto={foto} ancho="chica" style={s.mini} />
      <View style={{ flex: 1, gap: 2 }}>
        {!!foto.anio && <Text style={s.anio}>{foto.anio}</Text>}
        <Text style={s.titulo} numberOfLines={2}>{foto.titulo}</Text>
        {!!detalle && <Tenue style={{ fontSize: 13 }}>{detalle}</Tenue>}
        {!!foto.estado_etiqueta && <EstadoFoto estado={foto.estado ?? ''} etiqueta={foto.estado_etiqueta} />}
      </View>
    </View>
  );
}

export function EstadoFoto({ estado, etiqueta }: { estado: string; etiqueta: string }) {
  return (
    <View style={s.estado} accessibilityLabel={`Estado: ${etiqueta}`}>
      <View style={[s.punto, { backgroundColor: COLOR_ESTADO[estado] ?? C.tenue }]} />
      <Text style={s.estadoTexto}>{etiqueta}</Text>
    </View>
  );
}

const s = StyleSheet.create({
  fila: { flexDirection: 'row', gap: E.m, alignItems: 'center' },
  mini: { width: 76, height: 76, borderRadius: 10 },
  anio: { color: C.acento, fontWeight: '700', fontSize: 13, fontVariant: ['tabular-nums'] },
  titulo: { color: C.texto, fontWeight: '700', fontSize: 15 },
  estado: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  punto: { width: 9, height: 9, borderRadius: 5 },
  estadoTexto: { color: C.tenue, fontSize: 13 },
});
