import { useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useEffect } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { Aviso, Boton, Cargando, Chip, ErrorVista, Fila, Icon, Pantalla, Subtitulo, Tarjeta, Tenue, Texto, Titulo } from '@/components/ui';
import { useAuth } from '@/lib/auth';
import { formatearFecha } from '@/lib/formato';
import { RUTAS, urlWeb } from '@/lib/modulos';
import { useColaAsistencia, useConexion } from '@/lib/offline';
import { api } from '@/lib/api';
import { useInicio, useMe } from '@/lib/queries';
import { C, E, moneda } from '@/lib/theme';
import type { Tarjeta as TarjetaApi } from '@/lib/types';

const TONO_CUOTA: Record<string, string> = { al_dia: C.exito, pendiente: C.alerta, vencida: C.peligro, en_revision: C.info, sin_cuota: C.tenue };

export default function Inicio() {
  const me = useMe();
  const inicio = useInicio();
  const { contexto, elegirContexto } = useAuth();
  const online = useConexion();
  const pendientes = useColaAsistencia();
  const qc = useQueryClient();

  // Con señal, se precargan las planillas de las clases de hoy: así se puede tomar
  // asistencia aunque en el aula no haya conexión.
  useEffect(() => {
    const clases = inicio.data?.tarjetas.find((t) => t.tipo === 'clases_hoy')?.datos ?? [];
    const d = new Date();
    const hoy = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    clases.forEach((c: { bloque_id: number }) =>
      void qc.prefetchQuery({ queryKey: ['planilla', c.bloque_id, hoy], queryFn: () => api(`bloques/${c.bloque_id}/asistencia?fecha=${hoy}`) }),
    );
  }, [inicio.data, qc]);

  if (inicio.isPending && !inicio.data) return <Cargando />;
  if (inicio.isError && !inicio.data) return <ErrorVista error={inicio.error} onReintentar={() => inicio.refetch()} />;

  const contextos = me.data?.contextos ?? [];
  const modulos = (me.data?.modulos ?? []).filter((m) => RUTAS[m.clave] && m.clave !== 'notificaciones');

  return (
    <Pantalla refrescando={inicio.isRefetching} onRefrescar={() => { void inicio.refetch(); void me.refetch(); }}>
      <Titulo>{inicio.data?.saludo ?? 'Hola'}</Titulo>

      {!online && <Aviso tono="alerta" texto="Sin conexión: mostramos lo último guardado. La asistencia se guarda y se envía sola al volver la señal." />}
      {pendientes.length > 0 && <Aviso tono="info" texto={`${pendientes.length} planilla(s) de asistencia esperando conexión para enviarse.`} />}

      {contextos.length > 1 && (
        <View style={{ gap: E.s }}>
          <Tenue>Estoy trabajando como</Tenue>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: E.s }}>
            <ChipContexto etiqueta="Todo" activo={!contexto} onPress={() => elegirContexto(null)} />
            {contextos.map((c) => (
              <ChipContexto key={c.clave} etiqueta={c.etiqueta} activo={contexto === c.clave} onPress={() => elegirContexto(c.clave)} />
            ))}
          </ScrollView>
        </View>
      )}

      {inicio.data?.tarjetas.map((t, i) => <TarjetaInicio key={t.tipo + i} tarjeta={t} />)}

      {modulos.length > 0 && (
        <>
          <Subtitulo>Accesos</Subtitulo>
          <View style={s.grilla}>
            {modulos.map((m) => {
              const destino = RUTAS[m.clave];
              return (
                <Pressable
                  key={m.clave}
                  style={({ pressed }) => [s.modulo, pressed && { opacity: 0.7 }]}
                  accessibilityRole="button"
                  accessibilityLabel={m.etiqueta}
                  onPress={() => (destino.ruta ? router.push(destino.ruta as never) : WebBrowser.openBrowserAsync(urlWeb(destino.web!)))}>
                  <Icon name={destino.icono} size={30} color={C.acento} />
                  <Text style={s.moduloTexto} numberOfLines={2}>{m.etiqueta}</Text>
                  {!destino.ruta && <Tenue style={{ fontSize: 11 }}>panel web</Tenue>}
                </Pressable>
              );
            })}
          </View>
        </>
      )}
    </Pantalla>
  );
}

function ChipContexto({ etiqueta, activo, onPress }: { etiqueta: string; activo: boolean; onPress: () => void }) {
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityState={{ selected: activo }}
      style={[s.ctx, activo && { backgroundColor: C.acento, borderColor: C.acento }]}>
      <Text style={{ color: C.texto, fontWeight: '700' }}>{etiqueta}</Text>
    </Pressable>
  );
}

function TarjetaInicio({ tarjeta }: { tarjeta: TarjetaApi }) {
  const d = tarjeta.datos;
  switch (tarjeta.tipo) {
    case 'clases_hoy':
      return (
        <Tarjeta acento={C.acento}>
          <Fila><Icon name="fact-check" size={22} color={C.acento} /><Texto style={s.cabecera}>Clases de hoy</Texto></Fila>
          {d.length === 0 && <Tenue>Hoy no tenés clases con horario cargado.</Tenue>}
          {d.map((b: any) => (
            <Pressable
              key={b.bloque_id}
              style={({ pressed }) => [s.fila, pressed && { opacity: 0.7 }]}
              accessibilityRole="button"
              accessibilityLabel={`${b.asistencia_tomada ? 'Ver' : 'Tomar'} asistencia de ${b.nombre}`}
              onPress={() => router.push({ pathname: '/asistencia/[bloque]', params: { bloque: String(b.bloque_id), nombre: b.nombre } } as never)}>
              <View style={{ flex: 1 }}>
                <Texto style={{ fontWeight: '700' }}>{b.inicio ? `${b.inicio} · ` : ''}{b.nombre}</Texto>
                <Tenue>{b.sede}</Tenue>
              </View>
              {b.asistencia_tomada ? <Chip texto="✓ Tomada" color={C.exito} fondo={C.exitoSuave} /> : <Chip texto="Tomar" color={C.acento} fondo={C.acentoSuave} />}
            </Pressable>
          ))}
        </Tarjeta>
      );
    case 'mi_espacio': {
      const cuota = d.cuota;
      return (
        <Tarjeta acento={C.info} onPress={() => router.push('/cuotas' as never)}>
          <Fila><Icon name="school" size={22} color={C.info} /><Texto style={s.cabecera}>Mi cursada</Texto></Fila>
          <Texto>{(d.bloques ?? []).map((b: any) => b.nombre).join(' · ') || 'Sin bloque asignado'}</Texto>
          {d.sede && <Tenue>{d.sede}</Tenue>}
          {d.proxima_clase && (
            <Tenue>
              Próxima clase: {d.proxima_clase.es_hoy ? 'hoy' : d.proxima_clase.dia}{d.proxima_clase.hora ? ` ${d.proxima_clase.hora}` : ''} · {d.proxima_clase.bloque}
            </Tenue>
          )}
          {cuota && <Chip texto={`${cuota.label} · ${cuota.periodo}`} color={TONO_CUOTA[cuota.estado] ?? C.tenue} fondo={C.superficie2} />}
        </Tarjeta>
      );
    }
    case 'finanzas':
      return (
        <Tarjeta acento={C.exito} onPress={() => router.push('/finanzas' as never)}>
          <Fila><Icon name="payments" size={22} color={C.exito} /><Texto style={s.cabecera}>Finanzas del mes</Texto></Fila>
          <Texto style={s.numero}>{moneda(d.cobrado_mes)}</Texto>
          <Tenue>{d.pagos_mes} pagos registrados{d.comprobantes_pendientes != null ? ` · ${d.comprobantes_pendientes} comprobantes por revisar` : ''}</Tenue>
        </Tarjeta>
      );
    case 'inventario':
      return (
        <Tarjeta acento={C.alerta}>
          <Fila><Icon name="inventory-2" size={22} color={C.alerta} /><Texto style={s.cabecera}>Inventario</Texto></Fila>
          <Tenue>{d.items} ítems · {d.en_reparacion} en reparación</Tenue>
          <Fila style={{ flexWrap: 'wrap' }}>
            <View style={{ flex: 1 }}><Boton titulo="Escanear QR" icono="qr-code-scanner" onPress={() => router.push('/inventario/escanear' as never)} /></View>
            <View style={{ flex: 1 }}><Boton titulo="Ver todo" variante="secundario" onPress={() => router.push('/inventario' as never)} /></View>
          </Fila>
        </Tarjeta>
      );
    case 'eventos':
      return (
        <Tarjeta onPress={() => router.push('/agenda' as never)}>
          <Fila><Icon name="celebration" size={22} color={C.texto} /><Texto style={s.cabecera}>Próximos eventos</Texto></Fila>
          {d.length === 0 && <Tenue>No hay eventos próximos.</Tenue>}
          {d.map((e: any) => (
            <View key={e.id}>
              <Texto>{formatearFecha(e.fecha)}{e.hora_inicio ? ` · ${e.hora_inicio}` : ''} — {e.titulo}</Texto>
              {e.sede && <Tenue>{e.sede.nombre}</Tenue>}
            </View>
          ))}
        </Tarjeta>
      );
    default:
      return null;
  }
}

const s = StyleSheet.create({
  cabecera: { fontWeight: '800', fontSize: 17 },
  numero: { fontSize: 28, fontWeight: '800' },
  fila: { flexDirection: 'row', alignItems: 'center', gap: E.m, paddingVertical: E.s, minHeight: 52, borderTopWidth: 1, borderTopColor: C.borde },
  grilla: { flexDirection: 'row', flexWrap: 'wrap', gap: E.m },
  modulo: { width: '47%', minHeight: 96, backgroundColor: C.superficie, borderRadius: 14, borderWidth: 1, borderColor: C.borde, padding: E.m, justifyContent: 'center', gap: E.xs },
  moduloTexto: { color: C.texto, fontWeight: '700', fontSize: 15 },
  ctx: { borderRadius: 999, borderWidth: 1, borderColor: C.borde, backgroundColor: C.superficie, paddingHorizontal: E.l, minHeight: 44, justifyContent: 'center' },
});
