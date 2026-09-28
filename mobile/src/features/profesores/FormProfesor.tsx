import { router } from 'expo-router';
import { Pressable, View } from 'react-native';

import { Campo, ErrorFormulario, Interruptor, requeridos, Seccion, Selector, SelectorLista, useFormulario } from '@/components/form';
import { Aviso, Boton, Cargando, ErrorVista, Fila, Icon, Pantalla, Tenue, Texto } from '@/components/ui';
import { api, qs } from '@/lib/api';
import { useCatalogo, useOperacion } from '@/lib/recursos';
import { C, E } from '@/lib/theme';

export interface Profesor {
  id: number;
  nombre: string;
  telefono: string | null;
  email: string | null;
  activo: boolean;
  persona_id: number | null;
  cuenta: { id: number; username: string; activo: boolean } | null;
  bloques: { id: number; nombre: string; sede: string | null; rol: string; cantidad_alumnos: number }[];
  sedes: { id: number; nombre: string; rol: string; rol_nombre: string }[];
  areas: string[];
  eventos: { id: number; titulo: string; fecha: string }[];
  alumno_id: number | null;
  acciones: { editar: boolean; eliminar: boolean; ver_persona: boolean };
}

interface Catalogo {
  roles_bloque: Record<string, string>;
  roles_sede: Record<string, string>;
  bloques: { id: number; nombre: string; sede: string | null }[];
  sedes: { id: number; nombre: string }[];
  usa_username: boolean;
}

/** Alta / edición de docente: datos, cuenta de acceso, bloques con rol y roles por sede. */
export function FormProfesor({ profesor, persona }: { profesor?: Profesor; persona?: { id: number; nombre: string; telefono: string | null; email: string | null; tiene_cuenta?: boolean } }) {
  const cat = useCatalogo<Catalogo>('profesores/catalogo', 1);
  const f = useFormulario({
    nombre: profesor?.nombre ?? persona?.nombre ?? '',
    telefono: profesor?.telefono ?? persona?.telefono ?? '',
    email: profesor?.email ?? persona?.email ?? '',
    activo: profesor?.activo ?? true,
    cuenta_modo: (profesor ? (profesor.cuenta ? 'existente' : 'ninguna') : 'ninguna') as string,
    user_id: (profesor?.cuenta?.id ?? null) as number | null,
    user_nombre: profesor?.cuenta?.username ?? '',
    login_username: '',
    login_password: '',
    login_password_confirmation: '',
    bloques: (profesor?.bloques ?? []).map((b) => ({ bloque_id: b.id, rol: b.rol })),
    sedes: (profesor?.sedes ?? []).map((s) => ({ sede_id: s.id, rol: s.rol })),
  });
  const { valores: v, set, errores: e } = f;

  const guardar = useOperacion(
    (d: typeof v) => {
      const { user_nombre: _, ...resto } = d;
      const body = {
        ...resto,
        persona_id: persona?.id,
        telefono: d.telefono || null,
        email: d.email || null,
        user_id: d.cuenta_modo === 'existente' ? d.user_id : null,
        login_username: d.cuenta_modo === 'nueva' ? d.login_username : null,
        login_password: d.login_password || null,
        login_password_confirmation: d.login_password_confirmation || null,
      };
      return api<{ data: Profesor }>(profesor ? `profesores/${profesor.id}` : 'profesores', { method: profesor ? 'PUT' : 'POST', body });
    },
    {
      exito: profesor ? 'Docente actualizado' : 'Docente creado correctamente',
      invalidar: ['profesores', 'personas'],
      alTerminar: (r) => (profesor ? router.back() : router.replace({ pathname: '/profesores/[id]', params: { id: String(r.data.id) } } as never)),
    },
  );

  if (cat.isPending) return <Cargando />;
  if (cat.isError) return <ErrorVista error={cat.error} onReintentar={() => cat.refetch()} />;
  const c = cat.data;
  const rolesBloque = Object.entries(c.roles_bloque).map(([valor, etiqueta]) => ({ valor, etiqueta }));
  const bloquesLibres = c.bloques.filter((b) => !v.bloques.some((x) => x.bloque_id === b.id));
  const personaConCuenta = !!persona?.tiene_cuenta;

  return (
    <Pantalla>
      <ErrorFormulario mensaje={f.errorGeneral} />
      {persona && <Aviso tono="info" texto={`Se suma al plantel a ${persona.nombre}. Sus datos personales se comparten con su ficha de persona.`} />}
      <Seccion titulo="Datos">
        <Campo etiqueta="Nombre" requerido valor={v.nombre} onChange={(x) => set('nombre', x)} error={e.nombre} autoCapitalize="words" />
        <Campo etiqueta="Teléfono" valor={v.telefono} onChange={(x) => set('telefono', x)} error={e.telefono} teclado="phone-pad" />
        <Campo etiqueta="Email" valor={v.email} onChange={(x) => set('email', x)} error={e.email} teclado="email-address" autoCapitalize="none" requerido={v.cuenta_modo === 'nueva'} />
        <Interruptor etiqueta="Activo" valor={v.activo} onChange={(x) => set('activo', x)} />
      </Seccion>

      <Seccion titulo="Bloques" ayuda="Dónde da clase y con qué rol.">
        {v.bloques.map((b, i) => {
          const info = c.bloques.find((x) => x.id === b.bloque_id);
          return (
            <View key={b.bloque_id} style={{ gap: E.xs, borderTopWidth: i ? 1 : 0, borderTopColor: C.borde, paddingTop: i ? E.s : 0 }}>
              <Fila style={{ justifyContent: 'space-between' }}>
                <Texto style={{ fontWeight: '700', flex: 1 }}>{info ? `${info.nombre}${info.sede ? ` · ${info.sede}` : ''}` : `Bloque #${b.bloque_id}`}</Texto>
                <Pressable onPress={() => set('bloques', v.bloques.filter((x) => x.bloque_id !== b.bloque_id))} accessibilityRole="button" accessibilityLabel={`Quitar ${info?.nombre ?? 'bloque'}`} hitSlop={10}>
                  <Icon name="delete-outline" size={24} color={C.peligro} />
                </Pressable>
              </Fila>
              <Selector opciones={rolesBloque} valor={b.rol} onChange={(rol) => set('bloques', v.bloques.map((x) => (x.bloque_id === b.bloque_id ? { ...x, rol: rol ?? 'ayudante' } : x)))} />
            </View>
          );
        })}
        {e.bloques && <Tenue style={{ color: C.peligro }}>{e.bloques}</Tenue>}
        <SelectorLista<number>
          etiqueta="Agregar bloque"
          valor={null}
          placeholder="Elegir bloque…"
          opciones={bloquesLibres.map((b) => ({ valor: b.id, etiqueta: b.nombre, detalle: b.sede ?? undefined }))}
          onChange={(id) => id && set('bloques', [...v.bloques, { bloque_id: id, rol: 'titular' }])}
        />
      </Seccion>

      <Seccion titulo="Roles por sede" ayuda="Profesor, encargado o coordinador de sede (puede tener varios).">
        {c.sedes.map((s) => (
          <View key={s.id} style={{ gap: E.xs }}>
            <Texto style={{ fontWeight: '700' }}>{s.nombre}</Texto>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: E.s }}>
              {Object.entries(c.roles_sede).map(([rol, nombre]) => {
                const activo = v.sedes.some((x) => x.sede_id === s.id && x.rol === rol);
                return (
                  <Pressable
                    key={rol}
                    onPress={() => set('sedes', activo ? v.sedes.filter((x) => !(x.sede_id === s.id && x.rol === rol)) : [...v.sedes, { sede_id: s.id, rol }])}
                    accessibilityRole="checkbox"
                    accessibilityState={{ checked: activo }}
                    accessibilityLabel={`${nombre} en ${s.nombre}`}
                    style={{ borderRadius: 999, borderWidth: 1, borderColor: activo ? C.acento : C.borde, backgroundColor: activo ? C.acento : C.fondo, paddingHorizontal: E.m, minHeight: 40, justifyContent: 'center' }}>
                    <Texto style={{ fontSize: 14, fontWeight: '600' }}>{nombre}</Texto>
                  </Pressable>
                );
              })}
            </View>
          </View>
        ))}
      </Seccion>

      <Seccion titulo="Cuenta de acceso">
        {personaConCuenta ? (
          <Tenue>Esta persona ya tiene cuenta: el docente usa esa misma cuenta.</Tenue>
        ) : (
          <>
            <Selector
              opciones={[{ valor: 'ninguna', etiqueta: 'Sin cuenta' }, { valor: 'existente', etiqueta: 'Vincular existente' }, { valor: 'nueva', etiqueta: 'Crear cuenta' }]}
              valor={v.cuenta_modo}
              onChange={(x) => set('cuenta_modo', x ?? 'ninguna')}
              error={e.cuenta_modo}
            />
            {v.cuenta_modo === 'existente' && (
              <SelectorLista<number>
                etiqueta="Cuenta"
                requerido
                valor={v.user_id}
                valorEtiqueta={v.user_nombre}
                error={e.user_id}
                claveBusqueda={`usuarios-libres-${profesor?.id ?? 0}`}
                buscar={async (t) => (await api<{ data: { id: number; name: string; username: string; email: string }[] }>(`profesores/usuarios-disponibles${qs({ q: t, excepto: profesor?.id })}`)).data
                  .map((u) => ({ valor: u.id, etiqueta: u.name || u.username, detalle: `@${u.username} · ${u.email}` }))}
                onChange={(id, o) => { set('user_id', id); set('user_nombre', o?.etiqueta ?? ''); }}
              />
            )}
            {v.cuenta_modo === 'nueva' && (
              <>
                {c.usa_username && <Campo etiqueta="Usuario" requerido valor={v.login_username} onChange={(x) => set('login_username', x)} error={e.login_username} autoCapitalize="none" />}
                <Campo etiqueta="Contraseña" requerido secreto valor={v.login_password} onChange={(x) => set('login_password', x)} error={e.login_password} ayuda="Mínimo 8 caracteres." />
                <Campo etiqueta="Repetir contraseña" requerido secreto valor={v.login_password_confirmation} onChange={(x) => set('login_password_confirmation', x)} />
              </>
            )}
            {v.cuenta_modo === 'existente' && profesor?.cuenta && (
              <Campo etiqueta="Nueva contraseña (opcional)" secreto valor={v.login_password} onChange={(x) => set('login_password', x)} error={e.login_password} ayuda="Dejala vacía para no cambiarla." />
            )}
            {v.cuenta_modo === 'existente' && profesor?.cuenta && !!v.login_password && (
              <Campo etiqueta="Repetir nueva contraseña" secreto valor={v.login_password_confirmation} onChange={(x) => set('login_password_confirmation', x)} />
            )}
          </>
        )}
      </Seccion>

      <Boton
        titulo={profesor ? 'Guardar cambios' : 'Crear docente'}
        icono="save"
        grande
        cargando={f.enviando}
        onPress={() => void f.enviar((d) => guardar.mutateAsync(d), (d) => ({
          ...requeridos(d, { nombre: 'El nombre' }),
          ...(d.cuenta_modo === 'nueva' && d.login_password !== d.login_password_confirmation ? { login_password: 'Las contraseñas no coinciden.' } : {}),
          ...(d.cuenta_modo === 'existente' && !d.user_id ? { user_id: 'Elegí la cuenta a vincular.' } : {}),
        }))}
      />
    </Pantalla>
  );
}
