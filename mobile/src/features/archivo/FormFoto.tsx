import { useQuery } from '@tanstack/react-query';
import { useDeferredValue, useState } from 'react';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';

import { Campo, Chip as ChipOpcion, Interruptor, Seccion, SelectorLista } from '@/components/form';
import { Boton, Icon, Tenue } from '@/components/ui';
import { api, qs } from '@/lib/api';
import { C, E } from '@/lib/theme';

import type { CatalogoArchivo, FotoGestion, PersonaEnFoto } from './tipos';

/** Valores editables de una foto. Los de equipo se ignoran en un aporte. */
export interface ValoresFoto {
  titulo: string;
  descripcion: string;
  notas_aportante: string;
  contexto: string;
  anio: string;
  precision: string;
  lugar: string;
  ciudad: string;
  sede_id: number | null;
  acontecimiento_id: number | null;
  tipo: string | null;
  fotografo: string;
  fuente: string | null;
  fuente_detalle: string;
  tags: string;
  personas: PersonaEnFoto[];
  mostrar_aportante: boolean;
  // Solo equipo
  credito: string;
  licencia: string;
  alt_text: string;
  capitulo_id: number | null;
  destacada: boolean;
}

export function valoresIniciales(f?: FotoGestion | null): ValoresFoto {
  return {
    titulo: f?.titulo_original ?? '',
    descripcion: f?.descripcion ?? '',
    notas_aportante: f?.notas_aportante ?? '',
    contexto: f?.contexto ?? '',
    anio: f?.anio ? String(f.anio) : '',
    precision: f?.precision ?? 'anio',
    lugar: f?.lugar_detalle.lugar ?? '',
    ciudad: f?.lugar_detalle.ciudad ?? '',
    sede_id: f?.sede_id ?? null,
    acontecimiento_id: f?.acontecimiento_id ?? null,
    tipo: f?.tipo_clave ?? null,
    fotografo: f?.fotografo ?? '',
    fuente: f?.fuente_clave ?? null,
    fuente_detalle: f?.fuente_detalle ?? '',
    tags: (f?.tags ?? []).map((t) => `#${t.nombre}`).join(' '),
    personas: f?.personas_editables ?? [],
    mostrar_aportante: f?.mostrar_aportante ?? false,
    credito: f?.credito ?? '',
    licencia: f?.licencia ?? '',
    alt_text: f?.alt_text ?? '',
    capitulo_id: f?.capitulo_id ?? null,
    destacada: f?.destacada ?? false,
  };
}

/** Cuerpo JSON para la API (campos vacíos → null; personas sin vacíos). */
export function cuerpoFoto(v: ValoresFoto, equipo: boolean): Record<string, unknown> {
  const t = (x: string) => (x.trim() === '' ? null : x.trim());
  const base: Record<string, unknown> = {
    titulo: t(v.titulo), descripcion: t(v.descripcion), notas_aportante: t(v.notas_aportante),
    anio: v.anio ? Number(v.anio) : null, precision: v.precision, lugar: t(v.lugar), ciudad: t(v.ciudad),
    sede_id: v.sede_id, acontecimiento_id: v.acontecimiento_id, tipo: v.tipo, fotografo: t(v.fotografo),
    fuente: v.fuente, fuente_detalle: t(v.fuente_detalle), tags: v.tags, mostrar_aportante: v.mostrar_aportante,
    personas: v.personas.map((p) => ({ persona_id: p.persona_id, nombre: p.persona_id ? null : p.nombre, detalle: p.detalle })),
  };
  if (!equipo) return base;
  return { ...base, contexto: t(v.contexto), credito: t(v.credito), licencia: t(v.licencia), alt_text: t(v.alt_text), capitulo_id: v.capitulo_id, destacada: v.destacada };
}

/** Los mismos datos como campos de formulario multipart (`personas[0][nombre]`…). */
export function camposMultipart(v: ValoresFoto): Record<string, string | number | boolean | null> {
  const c = cuerpoFoto(v, false);
  const out: Record<string, string | number | boolean | null> = {};
  for (const [k, x] of Object.entries(c)) {
    if (k === 'personas') continue;
    if (x !== null && x !== undefined) out[k] = x as string | number | boolean;
  }
  v.personas.forEach((p, i) => {
    if (p.persona_id) out[`personas[${i}][persona_id]`] = p.persona_id;
    else out[`personas[${i}][nombre]`] = p.nombre;
    if (p.detalle) out[`personas[${i}][detalle]`] = p.detalle;
  });
  return out;
}

/**
 * Preguntas del archivo, en tono de conversación. `equipo` suma crédito, licencia,
 * texto alternativo, capítulo y destacada.
 */
export function FormFoto({ v, set, e, cat, equipo = false }: {
  v: ValoresFoto;
  set: <K extends keyof ValoresFoto>(k: K, x: ValoresFoto[K]) => void;
  e: Record<string, string>;
  cat: CatalogoArchivo | undefined;
  equipo?: boolean;
}) {
  return (
    <>
      <Seccion titulo="Lo que recordás">
        <Campo etiqueta="¿Cómo la llamarías?" valor={v.titulo} onChange={(x) => set('titulo', x)} error={e.titulo} placeholder="Ensayo en la plaza de Banfield" />
        <Campo etiqueta="¿Qué recordás de esta foto?" valor={v.descripcion} onChange={(x) => set('descripcion', x)} error={e.descripcion} multilinea maxLength={3000} />
        <Campo etiqueta="¿De qué año es, aproximadamente?" valor={v.anio} onChange={(x) => set('anio', x.replace(/\D/g, '').slice(0, 4))} error={e.anio} teclado="number-pad" placeholder="1998" />
        <View style={s.chips}>
          {[['anio', 'Seguro/a del año'], ['aprox', 'Es aproximado']].map(([k, l]) => (
            <ChipOpcion key={k} etiqueta={l} activo={v.precision === k} onPress={() => set('precision', k)} />
          ))}
        </View>
        <Campo etiqueta="¿Dónde fue?" valor={v.lugar} onChange={(x) => set('lugar', x)} error={e.lugar} placeholder="Plaza, teatro, calle…" />
        <Campo etiqueta="Ciudad o barrio" valor={v.ciudad} onChange={(x) => set('ciudad', x)} error={e.ciudad} />
        <SelectorLista etiqueta="¿Es de una sede?" valor={v.sede_id} onChange={(x) => set('sede_id', x)} error={e.sede_id} permitirVacio placeholder="No sé / ninguna"
          opciones={(cat?.sedes ?? []).map((x) => ({ valor: x.id, etiqueta: x.nombre }))} />
        <SelectorLista etiqueta="¿Es de alguno de estos momentos?" valor={v.acontecimiento_id} onChange={(x) => set('acontecimiento_id', x)} error={e.acontecimiento_id} permitirVacio placeholder="Ninguno / no sé"
          opciones={(cat?.acontecimientos ?? []).filter((a) => equipo || a.publicado).map((a) => ({ valor: a.id, etiqueta: `${a.anio ?? 's/f'} · ${a.titulo}` }))} />
        <SelectorLista etiqueta="¿Qué muestra?" valor={v.tipo} onChange={(x) => set('tipo', x)} error={e.tipo} permitirVacio placeholder="Elegí una opción"
          opciones={Object.entries(cat?.tipos ?? {}).map(([valor, etiqueta]) => ({ valor, etiqueta }))} />
      </Seccion>
      <Seccion titulo="¿Quiénes aparecen?" ayuda="Nombre y, si querés, dónde está en la foto.">
        <EditorPersonas personas={v.personas} onChange={(p) => set('personas', p)} equipo={equipo} />
        {!!e.personas && <Text style={s.error}>{e.personas}</Text>}
      </Seccion>
      <Seccion titulo="Autoría y procedencia" ayuda="Quién la sacó y de dónde viene son datos distintos de quién la comparte.">
        <Campo etiqueta="¿Quién sacó la foto?" valor={v.fotografo} onChange={(x) => set('fotografo', x)} error={e.fotografo} placeholder="Si no sabés, dejalo vacío" />
        <SelectorLista etiqueta="¿De dónde viene?" valor={v.fuente} onChange={(x) => set('fuente', x)} error={e.fuente} permitirVacio placeholder="Elegí una opción"
          opciones={Object.entries(cat?.fuentes ?? {}).map(([valor, etiqueta]) => ({ valor, etiqueta }))} />
        <Campo etiqueta="Detalle de la procedencia" valor={v.fuente_detalle} onChange={(x) => set('fuente_detalle', x)} error={e.fuente_detalle} placeholder="Álbum familiar, revista…" />
        <Campo etiqueta="Etiquetas" valor={v.tags} onChange={(x) => set('tags', x)} error={e.tags} placeholder="#ensayo #calle" autoCapitalize="none" />
        <Campo etiqueta="¿Querés contarnos algo más?" valor={v.notas_aportante} onChange={(x) => set('notas_aportante', x)} error={e.notas_aportante} multilinea maxLength={4000} />
        <Interruptor etiqueta="Mostrar el nombre de quien la aportó" valor={v.mostrar_aportante} onChange={(x) => set('mostrar_aportante', x)} ayuda="Si no, figura “Aportante anónimo”." />
      </Seccion>
      {equipo && (
        <Seccion titulo="Para el archivo">
          <Campo etiqueta="Contexto histórico" valor={v.contexto} onChange={(x) => set('contexto', x)} error={e.contexto} multilinea maxLength={8000} />
          <Campo etiqueta="Crédito" valor={v.credito} onChange={(x) => set('credito', x)} error={e.credito} placeholder="Archivo La Chilinga" />
          <Campo etiqueta="Licencia / derechos" valor={v.licencia} onChange={(x) => set('licencia', x)} error={e.licencia} />
          <Campo etiqueta="Texto alternativo" valor={v.alt_text} onChange={(x) => set('alt_text', x)} error={e.alt_text} ayuda="Lo que se ve, para lectores de pantalla." />
          <SelectorLista etiqueta="Capítulo" valor={v.capitulo_id} onChange={(x) => set('capitulo_id', x)} error={e.capitulo_id} permitirVacio placeholder="Sin capítulo"
            opciones={(cat?.capitulos ?? []).map((c) => ({ valor: c.id, etiqueta: c.titulo }))} />
          <Interruptor etiqueta="Destacada" valor={v.destacada} onChange={(x) => set('destacada', x)} ayuda="Puede ser la portada del archivo." />
        </Seccion>
      )}
    </>
  );
}

/** Lista de personas: nombres libres o, para el equipo, personas del sistema. */
function EditorPersonas({ personas, onChange, equipo }: { personas: PersonaEnFoto[]; onChange: (p: PersonaEnFoto[]) => void; equipo: boolean }) {
  const [nombre, setNombre] = useState('');
  const [detalle, setDetalle] = useState('');
  const q = useDeferredValue(nombre.trim());
  const sugeridas = useQuery({
    queryKey: ['archivo', 'personas', equipo, q],
    queryFn: ({ signal }) => api<{ data: { persona_id: number | null; nombre: string }[] }>(`${equipo ? 'archivo/gestion/personas' : 'archivo/personas'}${qs({ q })}`, { signal }).then((r) => r.data),
    enabled: q.length >= 2,
    staleTime: 60_000,
  });

  const agregar = (p: { persona_id: number | null; nombre: string }) => {
    const ya = personas.some((x) => (p.persona_id && x.persona_id === p.persona_id) || (!p.persona_id && !x.persona_id && x.nombre.toLowerCase() === p.nombre.toLowerCase()));
    if (!ya && p.nombre.trim()) onChange([...personas, { persona_id: p.persona_id, nombre: p.nombre.trim(), detalle: detalle.trim() || null }]);
    setNombre('');
    setDetalle('');
  };

  return (
    <View style={{ gap: E.s }}>
      {personas.map((p, i) => (
        <View key={`${p.persona_id ?? p.nombre}-${i}`} style={s.persona}>
          <View style={{ flex: 1 }}>
            <Text style={s.personaNombre}>{p.nombre}</Text>
            {(p.detalle || p.persona_id) && <Tenue>{[p.detalle, p.persona_id ? 'En el sistema' : null].filter(Boolean).join(' · ')}</Tenue>}
          </View>
          <Pressable onPress={() => onChange(personas.filter((_, j) => j !== i))} accessibilityRole="button" accessibilityLabel={`Quitar a ${p.nombre}`} hitSlop={10} style={s.quitar}>
            <Icon name="close" size={20} color={C.tenue} />
          </Pressable>
        </View>
      ))}
      <TextInput value={nombre} onChangeText={setNombre} placeholder="Nombre y apellido" placeholderTextColor={C.tenue} style={s.input} accessibilityLabel="Nombre de la persona" returnKeyType="next" />
      {(sugeridas.data ?? []).length > 0 && (
        <View style={s.chips}>
          {sugeridas.data!.slice(0, 6).map((p) => (
            <ChipOpcion key={`${p.persona_id ?? 'n'}-${p.nombre}`} etiqueta={p.nombre} activo={false} onPress={() => agregar(p)} />
          ))}
        </View>
      )}
      <TextInput value={detalle} onChangeText={setDetalle} placeholder="Dónde está en la foto (opcional)" placeholderTextColor={C.tenue} style={s.input} accessibilityLabel="Dónde está en la foto" />
      <Boton titulo="Agregar persona" icono="person-add" variante="secundario" deshabilitado={!nombre.trim()} onPress={() => agregar({ persona_id: null, nombre })} />
    </View>
  );
}

const s = StyleSheet.create({
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: E.s },
  persona: { flexDirection: 'row', alignItems: 'center', gap: E.s, padding: E.m, borderRadius: 12, backgroundColor: C.superficie2 },
  personaNombre: { color: C.texto, fontWeight: '700', fontSize: 15 },
  quitar: { minWidth: 44, minHeight: 44, alignItems: 'center', justifyContent: 'center' },
  input: { minHeight: 48, borderRadius: 12, borderWidth: 1, borderColor: C.borde, backgroundColor: C.superficie, color: C.texto, paddingHorizontal: E.m, fontSize: 16 },
  error: { color: C.peligro, fontSize: 13 },
});
