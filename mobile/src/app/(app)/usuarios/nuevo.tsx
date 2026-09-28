import { router, Stack, useLocalSearchParams } from 'expo-router';

import { Campo, ErrorFormulario, requeridos, Seccion, Selector, SelectorLista, useFormulario } from '@/components/form';
import { Aviso, Boton, Cargando, ErrorVista, Pantalla } from '@/components/ui';
import type { Persona } from '@/features/personas/tipos';
import type { CatalogoUsuarios, Usuario } from '@/features/usuarios/tipos';
import { api } from '@/lib/api';
import { useCatalogo, useDetalle, useOperacion } from '@/lib/recursos';

/** Crear cuenta de acceso: para una persona existente (?persona_id=) o una nueva. */
export default function NuevoUsuario() {
  const { persona_id } = useLocalSearchParams<{ persona_id?: string }>();
  const persona = useDetalle<Persona>('personas', persona_id, { habilitado: !!persona_id });
  const cat = useCatalogo<CatalogoUsuarios>('usuarios/catalogo', 1);
  if ((persona_id && persona.isPending) || cat.isPending) return <Cargando />;
  if (persona_id && persona.isError) return <ErrorVista error={persona.error} onReintentar={() => persona.refetch()} />;
  if (cat.isError) return <ErrorVista error={cat.error} onReintentar={() => cat.refetch()} />;
  return <Formulario persona={persona.data} catalogo={cat.data} />;
}

function Formulario({ persona, catalogo }: { persona?: Persona; catalogo: CatalogoUsuarios }) {
  const f = useFormulario({
    nombre: '', apellido: '', dni: '', telefono: '',
    username: '', email: persona?.email ?? '', password: '', password_confirmation: '',
    rol: '', ambito: 'global', sede_id: null as number | null,
  });
  const { valores: v, set, errores: e } = f;
  const crear = useOperacion(
    (d: typeof v) => api<{ data: Usuario }>('usuarios', {
      method: 'POST',
      body: {
        ...(persona ? { persona_id: persona.id } : { nombre: d.nombre, apellido: d.apellido || null, dni: d.dni || null, telefono: d.telefono || null }),
        username: d.username, email: d.email, password: d.password, password_confirmation: d.password_confirmation,
        rol: d.rol || null, ambito: d.rol ? d.ambito : null, sede_id: d.rol && d.ambito === 'sede' ? d.sede_id : null,
      },
    }),
    { exito: 'Cuenta creada. Revisá qué puede hacer', invalidar: ['usuarios', 'personas'], alTerminar: (r) => router.replace({ pathname: '/usuarios/[id]', params: { id: String(r.data.id) } } as never) },
  );
  const ambitos = (v.rol ? catalogo.roles_ambitos[v.rol] ?? ['global'] : ['global']).filter((a) => a !== 'bloque');

  return (
    <Pantalla>
      <Stack.Screen options={{ title: persona ? 'Crear cuenta' : 'Nueva cuenta' }} />
      <ErrorFormulario mensaje={f.errorGeneral} />
      {persona ? <Aviso tono="info" texto={`Cuenta de acceso para ${persona.nombre_completo}.`} /> : (
        <Seccion titulo="Persona" ayuda="Si ya está cargada, creá la cuenta desde su ficha para no duplicarla.">
          <Campo etiqueta="Nombre" requerido valor={v.nombre} onChange={(x) => set('nombre', x)} error={e.nombre} autoCapitalize="words" />
          <Campo etiqueta="Apellido" valor={v.apellido} onChange={(x) => set('apellido', x)} error={e.apellido} autoCapitalize="words" />
          <Campo etiqueta="DNI" valor={v.dni} onChange={(x) => set('dni', x)} error={e.dni} teclado="number-pad" />
          <Campo etiqueta="Teléfono" valor={v.telefono} onChange={(x) => set('telefono', x)} error={e.telefono} teclado="phone-pad" />
        </Seccion>
      )}
      <Seccion titulo="Acceso">
        <Campo etiqueta="Usuario" requerido valor={v.username} onChange={(x) => set('username', x.trim())} error={e.username} autoCapitalize="none" ayuda="Letras, números, guion y guion bajo." />
        <Campo etiqueta="Email" requerido valor={v.email} onChange={(x) => set('email', x.trim())} error={e.email} teclado="email-address" autoCapitalize="none" />
        <Campo etiqueta="Contraseña" requerido secreto valor={v.password} onChange={(x) => set('password', x)} error={e.password} ayuda="Mínimo 8 caracteres." />
        <Campo etiqueta="Repetir contraseña" requerido secreto valor={v.password_confirmation} onChange={(x) => set('password_confirmation', x)} />
      </Seccion>
      <Seccion titulo="Rol inicial (opcional)">
        <SelectorLista etiqueta="Rol" valor={v.rol || null} onChange={(x) => { set('rol', x ?? ''); set('ambito', (catalogo.roles_ambitos[x ?? ''] ?? ['global'])[0]); }} error={e.rol} permitirVacio placeholder="Sin rol por ahora"
          opciones={Object.entries(catalogo.roles).map(([valor, etiqueta]) => ({ valor, etiqueta }))} />
        {!!v.rol && <Selector etiqueta="Dónde" opciones={ambitos.map((a) => ({ valor: a, etiqueta: a === 'global' ? 'Toda la escuela' : 'Una sede' }))} valor={v.ambito} onChange={(x) => set('ambito', x ?? 'global')} error={e.ambito} />}
        {!!v.rol && v.ambito === 'sede' && <SelectorLista<number> etiqueta="Sede" requerido valor={v.sede_id} onChange={(x) => set('sede_id', x)} error={e.sede_id} opciones={catalogo.sedes.map((s) => ({ valor: s.id, etiqueta: s.nombre }))} />}
      </Seccion>
      <Boton titulo="Crear cuenta" icono="key" grande cargando={f.enviando}
        onPress={() => void f.enviar((d) => crear.mutateAsync(d), (d) => ({
          ...requeridos(d, { username: 'El usuario', email: 'El email', password: 'La contraseña', ...(persona ? {} : { nombre: 'El nombre' }) }),
          ...(d.password && d.password.length < 8 ? { password: 'La contraseña tiene que tener al menos 8 caracteres.' } : {}),
          ...(d.password !== d.password_confirmation ? { password_confirmation: 'Las contraseñas no coinciden.' } : {}),
        }))} />
    </Pantalla>
  );
}
