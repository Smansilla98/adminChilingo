import { router, Stack } from 'expo-router';
import { useState } from 'react';
import { Linking, View } from 'react-native';

import { confirmar, DialogoTexto } from '@/components/feedback';
import { ErrorFormulario, useFormulario } from '@/components/form';
import { Acciones, Aviso, Boton, Cargando, Dato, ErrorVista, Pantalla, Tarjeta, Tenue, Texto } from '@/components/ui';
import { api } from '@/lib/api';
import { useCatalogo, useDetalle, useOperacion } from '@/lib/recursos';
import { C, E } from '@/lib/theme';

import { cuerpoFoto, FormFoto, valoresIniciales } from './FormFoto';
import { EstadoFoto, ImagenArchivo } from './Miniatura';
import type { CatalogoArchivo, FotoGestion } from './tipos';

type Modo = 'aporte' | 'equipo';
const RUTA: Record<Modo, string> = { aporte: 'archivo/aportes', equipo: 'archivo/gestion/fotos' };

/**
 * Ficha de una foto del archivo. En modo `aporte` la ve quien la compartió (corregir,
 * enviar, borrar); en modo `equipo`, quien gestiona (editar todo, moderar, publicar).
 * Los botones salen de `acciones`, que calcula la Policy en el servidor.
 */
export function FichaFoto({ id, modo }: { id: string; modo: Modo }) {
  const ruta = RUTA[modo];
  const q = useDetalle<FotoGestion>(ruta, id);
  const cat = useCatalogo<CatalogoArchivo>('archivo/catalogo', 1);
  const [editando, setEditando] = useState(false);
  const [dialogo, setDialogo] = useState<'cambios' | 'rechazar' | null>(null);
  const invalidar = ['archivo/aportes', 'archivo/gestion/fotos', 'archivo/gestion/moderacion', ['archivo', 'resumen']];

  const estado = useOperacion((p: { accion: string; notas?: string }) => api(`archivo/gestion/fotos/${id}/estado`, { method: 'POST', body: p }), {
    exito: (r: unknown) => mensajeEstado((r as { data: FotoGestion }).data.estado),
    invalidar,
    alTerminar: () => setDialogo(null),
  });
  const enviar = useOperacion(() => api(`archivo/aportes/${id}/enviar`, { method: 'POST' }), { exito: '¡Gracias! Tu foto quedó en revisión.', invalidar });
  const eliminar = useOperacion(() => api(`${ruta}/${id}`, { method: 'DELETE' }), { exito: 'Foto eliminada', invalidar, alTerminar: () => router.back() });

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const f = q.data;
  const pendienteDeModerar = f.es_aporte && ['pendiente', 'cambios', 'rechazada', 'borrador'].includes(f.estado);

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: modo === 'aporte' ? 'Mi aporte' : 'Foto del archivo' }} />
      <View style={{ aspectRatio: f.ancho && f.alto ? Math.max(0.6, Math.min(1.8, f.ancho / f.alto)) : 1.5, borderRadius: 12, overflow: 'hidden' }}>
        <ImagenArchivo foto={f} ancho="grande" ajuste="contain" style={{ flex: 1 }} />
      </View>
      <View style={{ gap: E.xs }}>
        {!!f.fecha && <Texto style={{ color: C.acento, fontWeight: '700' }}>{f.fecha}</Texto>}
        <Texto style={{ fontSize: 22, fontWeight: '800' }}>{f.titulo}</Texto>
        <EstadoFoto estado={f.estado} etiqueta={f.estado_etiqueta} />
      </View>

      {f.estado === 'cambios' && !!f.notas_revision && <Aviso tono="alerta" texto={`${modo === 'aporte' ? 'El equipo del archivo te pide' : 'Pedido al aportante'}: ${f.notas_revision}`} />}
      {f.estado === 'rechazada' && !!f.motivo_rechazo && <Aviso tono="peligro" texto={`Motivo: ${f.motivo_rechazo}`} />}
      {f.estado === 'publicada' && modo === 'aporte' && <Aviso tono="exito" texto="¡Ya es parte del archivo!" />}
      {!!f.duplicados && <Aviso tono="alerta" texto="La misma imagen ya está en el archivo. Puede tener otra procedencia: revisala antes de aprobar." />}

      <Acciones>
        {modo === 'equipo' && f.acciones.moderar && pendienteDeModerar && (
          <>
            <Boton titulo="Aprobar y publicar" icono="check" cargando={estado.isPending} onPress={() => estado.mutate({ accion: 'aprobar' })} />
            {f.estado !== 'cambios' && <Boton titulo="Pedir cambios" icono="edit-note" variante="secundario" onPress={() => setDialogo('cambios')} />}
            {f.estado !== 'rechazada' && <Boton titulo="Rechazar" icono="block" variante="peligro" onPress={() => setDialogo('rechazar')} />}
          </>
        )}
        {modo === 'equipo' && f.acciones.publicar && ['borrador', 'oculta'].includes(f.estado) && !pendienteDeModerar && (
          <Boton titulo="Publicar" icono="visibility" cargando={estado.isPending} onPress={() => estado.mutate({ accion: 'publicar' })} />
        )}
        {modo === 'equipo' && f.acciones.publicar && f.estado === 'publicada' && (
          <Boton titulo="Ocultar" icono="visibility-off" variante="secundario" cargando={estado.isPending} onPress={() => estado.mutate({ accion: 'ocultar' })} />
        )}
        {modo === 'aporte' && f.acciones.enviar && <Boton titulo={f.estado === 'cambios' ? 'Reenviar al archivo' : 'Enviar al archivo'} icono="send" cargando={enviar.isPending} onPress={() => enviar.mutate()} />}
        {(modo === 'aporte' ? f.acciones.editar : f.acciones.editar_equipo) && !editando && <Boton titulo="Editar datos" icono="edit" variante="secundario" onPress={() => setEditando(true)} />}
        {f.estado === 'publicada' && !!f.url && <Boton titulo="Ver publicada" icono="open-in-new" variante="secundario" onPress={() => void Linking.openURL(f.url!)} />}
        {f.acciones.eliminar && (
          <Boton titulo="Eliminar" icono="delete" variante="peligro" cargando={eliminar.isPending}
            onPress={async () => { if (await confirmar({ titulo: '¿Eliminar esta foto?', mensaje: 'Se borran el original y sus versiones web.', accion: 'Eliminar' })) eliminar.mutate(); }} />
        )}
      </Acciones>

      {editando
        ? <EdicionFoto foto={f} modo={modo} cat={cat.data} ruta={`${ruta}/${id}`} onListo={() => { setEditando(false); void q.refetch(); }} />
        : <DatosFoto f={f} />}

      {f.revisiones.length > 0 && (
        <Tarjeta>
          <Texto style={{ fontWeight: '800' }}>Historial</Texto>
          {f.revisiones.map((r, i) => (
            <View key={i} style={{ gap: 2 }}>
              <Texto>{r.etiqueta}{r.usuario && modo === 'equipo' ? ` · ${r.usuario}` : ''}</Texto>
              <Tenue style={{ fontSize: 12 }}>{r.fecha ? new Date(r.fecha).toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' }) : ''}{r.notas && ['cambios', 'rechazada', 'aprobada'].includes(r.accion) ? ` — ${r.notas}` : ''}</Tenue>
            </View>
          ))}
        </Tarjeta>
      )}

      <DialogoTexto
        visible={dialogo !== null}
        titulo={dialogo === 'cambios' ? 'Pedir cambios' : 'Rechazar aporte'}
        mensaje={dialogo === 'cambios' ? 'Contale qué falta: año, lugar, quiénes aparecen…' : 'Lo va a ver quien la aportó.'}
        etiqueta={dialogo === 'cambios' ? 'Qué necesitás' : 'Motivo'}
        accion={dialogo === 'cambios' ? 'Enviar pedido' : 'Rechazar'}
        destructiva={dialogo === 'rechazar'}
        minimo={3}
        cargando={estado.isPending}
        error={estado.error instanceof Error ? estado.error.message : null}
        onConfirmar={(notas) => estado.mutate({ accion: dialogo === 'cambios' ? 'cambios' : 'rechazar', notas })}
        onCerrar={() => setDialogo(null)}
      />
    </Pantalla>
  );
}

function DatosFoto({ f }: { f: FotoGestion }) {
  const lugar = [f.lugar_detalle.lugar, f.lugar_detalle.ciudad, f.lugar_detalle.pais].filter(Boolean).join(', ');
  return (
    <Tarjeta>
      {!!f.descripcion && <Texto>{f.descripcion}</Texto>}
      <Dato etiqueta="Lugar" valor={lugar || null} />
      <Dato etiqueta="Sede" valor={f.sede} />
      <Dato etiqueta="Momento" valor={f.acontecimiento?.titulo ?? null} />
      <Dato etiqueta="Quiénes aparecen" valor={f.personas_editables.map((p) => p.nombre + (p.detalle ? ` (${p.detalle})` : '')).join(', ') || null} />
      <Dato etiqueta="Fotografía" valor={f.fotografo} />
      <Dato etiqueta="Fuente" valor={[f.fuente, f.fuente_detalle].filter(Boolean).join(' · ') || null} />
      <Dato etiqueta="Crédito" valor={f.credito} />
      <Dato etiqueta="Aportada por" valor={f.es_aporte ? `${f.aportante_nombre ?? '—'}${f.mostrar_aportante ? '' : ' (anónimo en público)'}` : null} />
      <Dato etiqueta="Etiquetas" valor={f.tags.map((t) => `#${t.nombre}`).join(' ') || null} />
      {!!f.notas_aportante && <Dato etiqueta="Lo que contó" valor={f.notas_aportante} />}
    </Tarjeta>
  );
}

function EdicionFoto({ foto, modo, cat, ruta, onListo }: { foto: FotoGestion; modo: Modo; cat: CatalogoArchivo | undefined; ruta: string; onListo: () => void }) {
  const equipo = modo === 'equipo';
  const f = useFormulario(valoresIniciales(foto));
  const guardar = useOperacion((p: { enviar: boolean; cuerpo: Record<string, unknown> }) => api(ruta, { method: 'PUT', body: { ...p.cuerpo, ...(p.enviar ? { enviar: true } : {}) } }), {
    exito: (_r: unknown) => 'Guardamos los cambios',
    invalidar: ['archivo/aportes', 'archivo/gestion/fotos', 'archivo/gestion/moderacion'],
    alTerminar: onListo,
  });
  const ejecutar = (enviar: boolean) => f.enviar((d) => guardar.mutateAsync({ enviar, cuerpo: cuerpoFoto(d, equipo) }));

  return (
    <View style={{ gap: E.m }}>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <FormFoto v={f.valores} set={f.set} e={f.errores} cat={cat} equipo={equipo} />
      <Acciones>
        {!equipo && foto.acciones.enviar && <Boton titulo="Guardar y enviar" icono="send" cargando={f.enviando} onPress={() => void ejecutar(true)} />}
        <Boton titulo="Guardar" icono="save" variante={!equipo && foto.acciones.enviar ? 'secundario' : 'primario'} cargando={f.enviando} onPress={() => void ejecutar(false)} />
        <Boton titulo="Cancelar" variante="secundario" onPress={onListo} />
      </Acciones>
    </View>
  );
}

function mensajeEstado(estado: string): string {
  return ({
    publicada: 'Publicada. Le avisamos a quien la aportó.',
    cambios: 'Le pedimos los cambios a quien la aportó.',
    rechazada: 'Aporte rechazado.',
    oculta: 'Oculta: ya no se ve en el archivo público.',
  } as Record<string, string>)[estado] ?? 'Listo';
}
