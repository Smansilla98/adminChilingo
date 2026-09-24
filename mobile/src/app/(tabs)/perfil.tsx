import * as WebBrowser from 'expo-web-browser';
import { Alert, View } from 'react-native';

import { Aviso, Boton, Cargando, Chip, ErrorVista, Fila, Pantalla, Subtitulo, Tarjeta, Tenue, Texto, Titulo } from '@/components/ui';
import { useAuth } from '@/lib/auth';
import { VARIANTE, WEB_URL } from '@/lib/config';
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

  const confirmarSalida = () =>
    Alert.alert('Cerrar sesión', cola.length > 0 ? `Tenés ${cola.length} planilla(s) sin enviar. Si salís se pierden.` : '¿Querés salir?', [
      { text: 'Cancelar', style: 'cancel' },
      { text: 'Salir', style: 'destructive', onPress: () => void salir() },
    ]);

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
          <Subtitulo>Pendiente de envío</Subtitulo>
          {cola.map((e) => (
            <Tarjeta key={e.uuid} acento={e.error ? C.peligro : C.alerta}>
              <Texto>Asistencia del {e.fecha} · {e.registros.length} alumnos</Texto>
              {e.error && <Aviso tono="peligro" texto={e.error} />}
              {e.error && <Boton titulo="Descartar" variante="peligro" onPress={() => void descartar(e.uuid)} />}
            </Tarjeta>
          ))}
          <Boton titulo="Reintentar envío" icono="sync" variante="secundario" onPress={() => void sincronizar()} />
        </>
      )}

      <Subtitulo>Más</Subtitulo>
      <Boton titulo="Abrir el panel web" icono="open-in-new" variante="secundario" onPress={() => WebBrowser.openBrowserAsync(WEB_URL)} />
      <Boton titulo="Cerrar sesión" icono="logout" variante="peligro" onPress={confirmarSalida} />
      {VARIANTE !== 'production' && <Tenue>Entorno: {VARIANTE}</Tenue>}
    </Pantalla>
  );
}
