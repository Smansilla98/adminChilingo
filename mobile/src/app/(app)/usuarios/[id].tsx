import { router, Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Pressable, View } from 'react-native';

import { confirmar, Hoja } from '@/components/feedback';
import { Campo, ErrorFormulario, useFormulario } from '@/components/form';
import { ItemLista } from '@/components/lista';
import { Acciones, Boton, Cargando, Chip, Dato, Encabezado, ErrorVista, Fila, Icon, Pantalla, Segmentos, Tarjeta, Tenue, Texto } from '@/components/ui';
import { NuevaAsignacion } from '@/features/usuarios/NuevaAsignacion';
import type { Usuario } from '@/features/usuarios/tipos';
import { api } from '@/lib/api';
import { useDetalle, useOperacion } from '@/lib/recursos';
import { C, E } from '@/lib/theme';

const fecha = (iso: string | null) => (iso ? new Date(iso).toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' }) : '—');

export default function FichaUsuario() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<Usuario>('usuarios', id);
  const [pestana, setPestana] = useState<'roles' | 'permisos' | 'dispositivos'>('roles');
  const [asignando, setAsignando] = useState(false);
  const [editando, setEditando] = useState(false);
  const [reseteando, setReseteando] = useState(false);
  const invalidar = ['usuarios', 'personas'];
  const estado = useOperacion((activo: boolean) => api(`usuarios/${id}/estado`, { method: 'POST', body: { activo } }), {
    exito: (r) => ((r as { data: Usuario }).data.activo ? 'Cuenta activada' : 'Cuenta desactivada. Se cerraron sus sesiones'),
    invalidar,
  });
  const quitar = useOperacion((asignacionId: number) => api(`usuarios/${id}/asignaciones/${asignacionId}`, { method: 'DELETE' }), { exito: 'Asignación quitada', invalidar: [...invalidar, 'me'] });

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const u = q.data;
  const a = u.acciones;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Cuenta' }} />
      <Encabezado icono="account-circle" titulo={u.nombre} subtitulo={`@${u.username} · ${u.email}`} chips={<>
        <Chip texto={u.activo ? 'Activa' : 'Desactivada'} color={u.activo ? C.exito : C.peligro} />
        {u.superadmin && <Chip texto="Superadministrador" color={C.acento} />}
      </>} />
      <Acciones>
        {u.persona && <Boton titulo="Ficha de persona" icono="badge" variante="secundario" onPress={() => router.push({ pathname: '/personas/[id]', params: { id: String(u.persona!.id) } } as never)} />}
        {a.editar && <Boton titulo="Editar datos" icono="edit" variante="secundario" onPress={() => setEditando(true)} />}
        {a.editar && <Boton titulo="Nueva contraseña" icono="lock-reset" variante="secundario" onPress={() => setReseteando(true)} />}
        {a.activar && (
          <Boton titulo={u.activo ? 'Desactivar' : 'Activar'} icono={u.activo ? 'block' : 'check-circle'} variante={u.activo ? 'peligro' : 'primario'} cargando={estado.isPending}
            onPress={async () => {
              if (!u.activo || await confirmar({ titulo: `¿Desactivar la cuenta de ${u.nombre}?`, mensaje: 'No va a poder ingresar y se cierran sus sesiones en la web y la app. Queda registrado en auditoría.', accion: 'Desactivar' })) estado.mutate(!u.activo);
            }} />
        )}
      </Acciones>
      <Tarjeta>
        <Dato etiqueta="Teléfono" valor={u.telefono} />
        <Dato etiqueta="Último acceso" valor={fecha(u.ultimo_acceso)} />
      </Tarjeta>

      <Segmentos opciones={[{ valor: 'roles', etiqueta: 'Roles', cantidad: u.funciones.length }, { valor: 'permisos', etiqueta: 'Qué puede hacer' }, { valor: 'dispositivos', etiqueta: 'Sesiones en la app', cantidad: u.dispositivos.length }]} valor={pestana} onChange={setPestana} />

      {pestana === 'roles' && (
        <>
          {a.gestionar_permisos && <Boton titulo="Agregar rol o permiso" icono="add-moderator" onPress={() => setAsignando(true)} />}
          {u.funciones.length === 0 && <Tenue>Sin roles.</Tenue>}
          {u.funciones.map((f) => (
            <Tarjeta key={f.clave}>
              <Fila style={{ justifyContent: 'space-between' }}>
                <View style={{ flex: 1 }}>
                  <Texto style={{ fontWeight: '700' }}>{f.rol_nombre}</Texto>
                  <Tenue>{f.ambito_nombre} · {f.origen_etiqueta}</Tenue>
                </View>
                {a.gestionar_permisos && f.editable && f.asignacion_id && (
                  <Pressable accessibilityRole="button" accessibilityLabel={`Quitar ${f.rol_nombre} en ${f.ambito_nombre}`} hitSlop={10}
                    onPress={async () => { if (await confirmar({ titulo: `¿Quitar ${f.rol_nombre}?`, mensaje: `${f.ambito_nombre}. Queda registrado en auditoría.`, accion: 'Quitar' })) quitar.mutate(f.asignacion_id!); }}>
                    <Icon name="remove-circle-outline" size={26} color={C.peligro} />
                  </Pressable>
                )}
              </Fila>
            </Tarjeta>
          ))}
          <Tenue style={{ fontSize: 13 }}>Los roles que surgen de la inscripción o el plantel docente se cambian desde la ficha de la persona.</Tenue>
        </>
      )}
      {pestana === 'permisos' && Object.entries(u.permisos).map(([grupo, lista]) => (
        <Tarjeta key={grupo}>
          <Texto style={{ fontWeight: '800' }}>{grupo}</Texto>
          {lista.map((p) => <Tenue key={p.permiso}>{p.etiqueta} · {p.alcance}{p.via.length ? ` (${p.via.join(', ')})` : ''}</Tenue>)}
        </Tarjeta>
      ))}
      {pestana === 'dispositivos' && (u.dispositivos.length === 0 ? <Tenue>No tiene sesiones abiertas en la app.</Tenue> : u.dispositivos.map((d) => (
        <ItemLista key={d.id} icono="smartphone" titulo={d.nombre} subtitulo={`Último uso: ${fecha(d.ultimo_uso)}`} detalle={`Desde ${fecha(d.desde)}${d.vence ? ` · vence ${fecha(d.vence)}` : ''}`} />
      )))}

      <NuevaAsignacion usuarioId={u.id} visible={asignando} onCerrar={() => setAsignando(false)} />
      <Hoja visible={editando} titulo="Datos de la cuenta" onCerrar={() => setEditando(false)}>
        {editando && <EditarCuenta usuario={u} onCerrar={() => setEditando(false)} />}
      </Hoja>
      <Hoja visible={reseteando} titulo="Nueva contraseña" onCerrar={() => setReseteando(false)}>
        {reseteando && <Resetear usuarioId={u.id} onCerrar={() => setReseteando(false)} />}
      </Hoja>
    </Pantalla>
  );
}

function EditarCuenta({ usuario, onCerrar }: { usuario: Usuario; onCerrar: () => void }) {
  const f = useFormulario({ username: usuario.username, email: usuario.email, telefono: usuario.telefono ?? '' });
  const op = useOperacion((d: typeof f.valores) => api(`usuarios/${usuario.id}`, { method: 'PUT', body: { ...d, telefono: d.telefono || null } }), { exito: 'Datos de la cuenta actualizados', invalidar: ['usuarios'], alTerminar: onCerrar });
  return (
    <View style={{ gap: E.m }}>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Campo etiqueta="Usuario" requerido valor={f.valores.username} onChange={(x) => f.set('username', x.trim())} error={f.errores.username} autoCapitalize="none" />
      <Campo etiqueta="Email" requerido valor={f.valores.email} onChange={(x) => f.set('email', x.trim())} error={f.errores.email} teclado="email-address" autoCapitalize="none" />
      <Campo etiqueta="Teléfono" valor={f.valores.telefono} onChange={(x) => f.set('telefono', x)} error={f.errores.telefono} teclado="phone-pad" />
      <Boton titulo="Guardar" icono="save" cargando={f.enviando} onPress={() => void f.enviar((d) => op.mutateAsync(d))} />
    </View>
  );
}

function Resetear({ usuarioId, onCerrar }: { usuarioId: number; onCerrar: () => void }) {
  const f = useFormulario({ password: '', password_confirmation: '' });
  const op = useOperacion((d: typeof f.valores) => api(`usuarios/${usuarioId}/resetear-acceso`, { method: 'POST', body: d }), {
    exito: 'Contraseña reemplazada. Se cerraron sus sesiones', invalidar: ['usuarios'], alTerminar: onCerrar,
  });
  return (
    <View style={{ gap: E.m }}>
      <Tenue>Se cierran las sesiones abiertas: tendrá que volver a ingresar en la web y en la app.</Tenue>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Campo etiqueta="Nueva contraseña" requerido secreto valor={f.valores.password} onChange={(x) => f.set('password', x)} error={f.errores.password} ayuda="Mínimo 8 caracteres." />
      <Campo etiqueta="Repetir contraseña" requerido secreto valor={f.valores.password_confirmation} onChange={(x) => f.set('password_confirmation', x)} error={f.errores.password_confirmation} />
      <Boton titulo="Reemplazar contraseña" icono="lock-reset" variante="peligro" cargando={f.enviando}
        onPress={() => void f.enviar((d) => op.mutateAsync(d), (d): Record<string, string> => (d.password.length < 8 ? { password: 'Mínimo 8 caracteres.' } : d.password !== d.password_confirmation ? { password_confirmation: 'Las contraseñas no coinciden.' } : {}))} />
    </View>
  );
}
