import { router, Stack } from 'expo-router';
import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { Image } from 'expo-image';

import { confirmar, useToast } from '@/components/feedback';
import { ErrorFormulario, Progreso, useFormulario } from '@/components/form';
import { Aviso, Boton, Icon, Pantalla, Tenue, Texto } from '@/components/ui';
import { ApiError } from '@/lib/api';
import { type ArchivoLocal, elegirImagenes, MB, subirArchivo } from '@/lib/archivos';
import { useCatalogo } from '@/lib/recursos';
import { C, E } from '@/lib/theme';
import { camposMultipart, FormFoto, valoresIniciales } from '@/features/archivo/FormFoto';
import type { CatalogoArchivo } from '@/features/archivo/tipos';
import { useQueryClient } from '@tanstack/react-query';

type EstadoCarga = 'espera' | 'subiendo' | 'ok' | 'error' | 'omitida';
interface Item { archivo: ArchivoLocal; estado: EstadoCarga; progreso: number; error?: string }

/**
 * Compartir un recuerdo: varias fotos con los mismos datos. Cada una se sube por
 * separado y queda en revisión; si ya está en el archivo, se pregunta antes.
 */
export default function Aportar() {
  const cat = useCatalogo<CatalogoArchivo>('archivo/catalogo', 1);
  const qc = useQueryClient();
  const avisar = useToast();
  const [items, setItems] = useState<Item[]>([]);
  const f = useFormulario(valoresIniciales());
  const { valores: v, set, errores: e } = f;
  const maxMb = cat.data?.max_mb ?? 40;

  const actualizar = (i: number, cambio: Partial<Item>) => setItems((xs) => xs.map((x, j) => (j === i ? { ...x, ...cambio } : x)));

  const elegir = async () => {
    const nuevas = await elegirImagenes(50);
    setItems((xs) => [...xs, ...nuevas.map((archivo) => ({ archivo, estado: 'espera' as const, progreso: 0, error: archivo.tamano && archivo.tamano > maxMb * MB ? `Pesa más de ${maxMb} MB.` : undefined }))]);
  };

  const subirUna = async (i: number, item: Item, datos: Record<string, string | number | boolean | null>, confirmarDuplicado = false): Promise<boolean> => {
    actualizar(i, { estado: 'subiendo', progreso: 0, error: undefined });
    try {
      await subirArchivo('archivo/aportes', item.archivo, {
        datos: { ...datos, enviar: true, confirmar_duplicado: confirmarDuplicado || undefined },
        onProgreso: (p) => actualizar(i, { progreso: p }),
      });
      actualizar(i, { estado: 'ok', progreso: 1 });
      return true;
    } catch (err) {
      if (err instanceof ApiError && err.status === 409 && !confirmarDuplicado) {
        const igual = await confirmar({ titulo: 'Esta foto podría ya estar en el archivo', mensaje: `“${item.archivo.nombre}” coincide con una foto existente. ¿Querés enviarla igual? Puede tener otra procedencia.`, accion: 'Enviar igual', destructiva: false });
        if (igual) return subirUna(i, item, datos, true);
        actualizar(i, { estado: 'omitida', error: 'Ya estaba en el archivo.' });
        return false;
      }
      if (err instanceof ApiError && err.esValidacion) {
        // Error del formulario (p. ej. el año): se corta para corregirlo una sola vez.
        actualizar(i, { estado: 'espera', progreso: 0 });
        throw err;
      }
      actualizar(i, { estado: 'error', error: err instanceof Error ? err.message : 'No se pudo subir.' });
      return false;
    }
  };

  const enviar = () => f.enviar(async (d) => {
    const datos = camposMultipart(d);
    let ok = 0;
    for (let i = 0; i < items.length; i++) {
      const it = items[i];
      if (it.estado === 'ok' || it.estado === 'omitida' || it.error?.startsWith('Pesa')) continue;
      if (await subirUna(i, it, datos)) ok++;
    }
    await qc.invalidateQueries({ queryKey: ['archivo/aportes'] });
    if (ok > 0) {
      avisar(ok === 1 ? '¡Gracias! Tu foto quedó en revisión.' : `¡Gracias! Tus ${ok} fotos quedaron en revisión.`);
      router.replace('/archivo' as never);
    }
    return ok;
  }, (d) => {
    const err: Record<string, string> = {};
    if (!items.some((x) => x.estado !== 'ok' && x.estado !== 'omitida' && !x.error?.startsWith('Pesa'))) err.archivo = 'Elegí al menos una foto.';
    if (!d.anio) err.anio = 'Contanos al menos el año aproximado.';
    return err;
  });

  const subiendo = items.some((x) => x.estado === 'subiendo');

  return (
    <Pantalla>
      <Stack.Screen options={{ title: 'Compartí un recuerdo' }} />
      <Texto style={{ fontSize: 15 }}>Subí tus fotos históricas de La Chilinga. El equipo del archivo las revisa con vos antes de publicarlas y siempre conservan quién las compartió.</Texto>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <View style={s.bloque}>
        <Boton titulo={items.length ? 'Agregar más fotos' : 'Elegir fotos de la galería'} icono="add-photo-alternate" variante={items.length ? 'secundario' : 'primario'} grande={!items.length} onPress={() => void elegir()} deshabilitado={subiendo} />
        {!!e.archivo && <Aviso tono="peligro" texto={e.archivo} />}
        <Tenue style={{ fontSize: 13 }}>JPG, PNG o WebP · hasta {maxMb} MB cada una.</Tenue>
        {items.length > 0 && (
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: E.s }}>
            {items.map((it, i) => (
              <View key={`${it.archivo.uri}-${i}`} style={s.mini}>
                <Image source={{ uri: it.archivo.uri }} style={StyleSheet.absoluteFill} contentFit="cover" accessibilityLabel={it.archivo.nombre} />
                <View style={[s.estadoMini, it.estado === 'ok' && { backgroundColor: C.exito }, (it.estado === 'error' || !!it.error) && it.estado !== 'ok' && { backgroundColor: C.peligro }]}>
                  <Text style={s.estadoTexto}>{it.estado === 'ok' ? '✓' : it.estado === 'subiendo' ? `${Math.round(it.progreso * 100)}%` : it.error ? '!' : ''}</Text>
                </View>
                {it.estado !== 'subiendo' && it.estado !== 'ok' && (
                  <Pressable onPress={() => setItems((xs) => xs.filter((_, j) => j !== i))} style={s.quitar} accessibilityRole="button" accessibilityLabel={`Quitar ${it.archivo.nombre}`} hitSlop={8}>
                    <Icon name="close" size={16} color={C.texto} />
                  </Pressable>
                )}
              </View>
            ))}
          </ScrollView>
        )}
        {items.filter((x) => x.error).map((x, i) => <Tenue key={i} style={{ color: C.peligro, fontSize: 13 }}>{x.archivo.nombre}: {x.error}</Tenue>)}
        {subiendo && <Progreso fraccion={items.reduce((a, x) => a + (x.estado === 'ok' ? 1 : x.progreso), 0) / Math.max(1, items.length)} texto="Subiendo al archivo…" />}
      </View>
      <Tenue>Estos datos se aplican a todas las fotos que elegiste. Después podés corregir cada una.</Tenue>
      <FormFoto v={v} set={set} e={e} cat={cat.data} />
      <Boton titulo="Enviar al archivo" icono="send" grande cargando={f.enviando} onPress={() => void enviar()} />
    </Pantalla>
  );
}

const s = StyleSheet.create({
  bloque: { gap: E.s },
  mini: { width: 88, height: 88, borderRadius: 10, overflow: 'hidden', backgroundColor: C.superficie2 },
  estadoMini: { position: 'absolute', left: 4, bottom: 4, minWidth: 26, paddingHorizontal: 5, height: 22, borderRadius: 11, backgroundColor: 'rgba(0,0,0,0.6)', alignItems: 'center', justifyContent: 'center' },
  estadoTexto: { color: C.texto, fontSize: 12, fontWeight: '700' },
  quitar: { position: 'absolute', right: 4, top: 4, width: 28, height: 28, borderRadius: 14, backgroundColor: 'rgba(0,0,0,0.6)', alignItems: 'center', justifyContent: 'center' },
});
