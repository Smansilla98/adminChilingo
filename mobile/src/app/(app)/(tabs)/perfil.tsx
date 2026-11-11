import { router } from 'expo-router';
import { Alert, View } from 'react-native';

import { confirmar } from '@/components/feedback';
import { Aviso, Boton, Cargando, Chip, ErrorVista, Fila, Pantalla, Subtitulo, Tarjeta, Tenue, Texto, Titulo } from '@/components/ui';
import { useAuth } from '@/lib/auth';
import { VARIANTE } from '@/lib/config';
import { formatearFecha } from '@/lib/formato';
import { descartar, sincronizar, useColaAsistencia } from '@/lib/offline';
import { useMe } from '@/lib/queries';
import { C, E } from '@/lib/theme';

export default function Perfil() {
  const me = useMe();
  const { salir, contexto, elegirContexto } = useAuth();
  const cola = useColaAsistencia();

  if (me.isPending && !me.data) return <Cargando />;
  if (me.isError && !me.data) return <ErrorVista error={me.error} onReintentar={() => me.refetch()} />;
  const d = me.data!;

  const pendientes = cola.filter((e) => !e.fallida).length;
  const fallidas = cola.length - pendientes;

  const confirmarSalida = () => {
    if (cola.length === 0) {
      Alert.alert('Cerrar sesión', '¿Querés salir?', [
        { text: 'Cancelar', style: 'cancel' },
        { text: 'Salir', style: 'destructive', onPress: () => void salir() },
      ]);
      return;
    }
    Alert.alert(
      'Cerrar sesión',
      `Tenés ${cola.length} planilla${cola.length === 1 ? '' : 's'} de asistencia sin enviar. ` +
        'Quedan guardadas en este teléfono y se envían cuando vuelvas a ingresar con tu cuenta. Si podés, envialas antes de salir.',
      [
        { text: 'Cancelar', style: 'cancel' },
        { text: 'Enviar ahora', onPress: () => void sincronizar({ forzar: true }) },
        { text: 'Salir igual', style: 'destructive', onPress: () => void salir() },
      ],
    );
  };

  return (
    <Pantalla refrescando={me.isRefetching} onRefrescar={() => me.refetch()}>
      <Titulo>{d.usuario.nombre}</Titulo>
      <Tenue>@{d.usuario.username} · {d.usuario.email}</Tenue>

      <Subtitulo>Mis funciones</Subtitulo>
      <Tarjeta>
        {d.funciones.length === 0 && <Tenue>Sin funciones asignadas.</Tenue>}
        {d.funciones.map((f) => (
          <Fila key={f.clave} style={{ justifyContent: 'space-between' }}>
            <View style={{ flex: 1 }}>
              <Texto style={{ fontWeight: '700' }}>{f.rol_nombre}</Texto>
              <Tenue>{f.ambito_nombre}</Tenue>
            </View>
            <Chip texto={f.origen_etiqueta} />
          </Fila>
        ))}
      </Tarjeta>

      {d.contextos.length > 1 && (
        <>
          <Subtitulo>Estoy trabajando como</Subtitulo>
          <Tenue>Cambia qué ves primero en el inicio. No cambia tus permisos.</Tenue>
          <View style={{ gap: E.s }}>
            <Boton titulo={(contexto ? '' : '✓ ') + 'Todo'} variante={contexto ? 'secundario' : 'primario'} onPress={() => elegirContexto(null)} />
            {d.contextos.map((c) => (
              <Boton key={c.clave} titulo={(contexto === c.clave ? '✓ ' : '') + c.etiqueta} variante={contexto === c.clave ? 'primario' : 'secundario'} onPress={() => elegirContexto(c.clave)} />
            ))}
          </View>
        </>
      )}

      {cola.length > 0 && (
        <>
          <Subtitulo>Asistencia sin enviar</Subtitulo>
          {pendientes > 0 && <Tenue>{pendientes} planilla{pendientes === 1 ? '' : 's'} esperando conexión: se envían solas.</Tenue>}
          {fallidas > 0 && <Aviso tono="peligro" texto={`${fallidas} planilla${fallidas === 1 ? '' : 's'} no se pudo enviar. Revisá el motivo: podés reintentar o descartarla.`} />}
          {cola.map((e) => (
            <Tarjeta key={e.uuid} acento={e.fallida ? C.peligro : C.alerta}>
              <Texto style={{ fontWeight: '700' }}>{e.bloque ? `${e.bloque} · ` : ''}{formatearFecha(e.fecha)}</Texto>
              <Tenue>{e.registros.length} alumnos · {e.fallida ? 'No se pudo enviar' : 'Esperando conexión'}</Tenue>
              {e.error && <Aviso tono={e.fallida ? 'peligro' : 'alerta'} texto={`Detalle: ${e.error}${e.intentos ? ` (intentos: ${e.intentos})` : ''}`} />}
              {e.fallida && (
                <Fila>
                  <View style={{ flex: 1 }}><Boton titulo="Ver planilla" variante="secundario" onPress={() => router.push({ pathname: '/asistencia/[bloque]', params: { bloque: String(e.bloqueId), fecha: e.fecha } } as never)} /></View>
                  <View style={{ flex: 1 }}><Boton titulo="Descartar" variante="peligro" onPress={async () => {
                    if (await confirmar({ titulo: '¿Descartar esta planilla?', mensaje: 'Las marcas de asistencia que no se enviaron se pierden.', accion: 'Descartar' })) void descartar(e.uuid);
                  }} /></View>
                </Fila>
              )}
            </Tarjeta>
          ))}
          <Boton titulo="Reintentar envío" icono="sync" variante="secundario" onPress={() => void sincronizar({ forzar: true })} />
        </>
      )}

      <Subtitulo>Más</Subtitulo>
      <Boton titulo="Qué puede cada perfil" icono="badge" variante="secundario" onPress={() => router.push('/roles' as never)} />
      <Boton titulo="Cambiar mi contraseña" icono="key" variante="secundario" onPress={() => router.push('/cambiar-clave' as never)} />
      <Boton titulo="Cerrar sesión" icono="logout" variante="peligro" onPress={confirmarSalida} />
      {VARIANTE !== 'production' && <Tenue>Entorno: {VARIANTE}</Tenue>}
    </Pantalla>
  );
}
