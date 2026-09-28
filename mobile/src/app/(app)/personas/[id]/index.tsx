import { useQuery } from '@tanstack/react-query';
import { router, Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Linking, View } from 'react-native';

import { confirmar, Hoja } from '@/components/feedback';
import { SelectorLista } from '@/components/form';
import { ItemLista } from '@/components/lista';
import { Acciones, Aviso, Boton, Cargando, Chip, Dato, Encabezado, ErrorVista, Fila, Pantalla, Segmentos, Subtitulo, Tarjeta, Tenue, Texto } from '@/components/ui';
import { HistorialAuditoria } from '@/features/auditoria/HistorialAuditoria';
import { EstadoCuentaVista } from '@/features/finanzas/EstadoCuentaVista';
import { EditarBeca } from '@/features/personas/EditarBeca';
import type { Beca, Persona, PersonaResumen } from '@/features/personas/tipos';
import { api, qs } from '@/lib/api';
import { formatearFecha } from '@/lib/formato';
import { usePermisos } from '@/lib/permisos';
import { useDetalle, useOperacion } from '@/lib/recursos';
import { C, E } from '@/lib/theme';

type Pestana = 'info' | 'roles' | 'cuenta' | 'becas' | 'actividad' | 'historial';

const ir = (pathname: string, params: Record<string, string | number> = {}) =>
  router.push({ pathname, params: Object.fromEntries(Object.entries(params).map(([k, v]) => [k, String(v)])) } as never);

/**
 * Ficha central de la persona: una misma persona puede ser alumna, docente,
 * coordinadora, becada… Desde acá se ejecutan las operaciones permitidas.
 */
export default function FichaPersona() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useDetalle<Persona>('personas', id);
  const { puede } = usePermisos();
  const [pestana, setPestana] = useState<Pestana>('info');
  const [fusionando, setFusionando] = useState(false);

  if (q.isPending) return <Cargando />;
  if (q.isError) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const p = q.data;
  const a = p.acciones;
  const esAlumno = p.alumnos.length > 0;

  const pestanas: { valor: Pestana; etiqueta: string; cantidad?: number }[] = [
    { valor: 'info', etiqueta: 'Información' },
    { valor: 'roles', etiqueta: 'Roles', cantidad: p.funciones.length },
    ...(esAlumno ? [{ valor: 'cuenta' as const, etiqueta: 'Cuotas y pagos' }, { valor: 'becas' as const, etiqueta: 'Becas' }] : []),
    { valor: 'actividad', etiqueta: 'Actividad' },
    ...(a.ver_auditoria ? [{ valor: 'historial' as const, etiqueta: 'Historial' }] : []),
  ];

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Persona' }} />
      <Encabezado
        titulo={p.nombre_completo}
        subtitulo={[p.dni ? `DNI ${p.dni}` : null, p.edad ? `${p.edad} años` : null].filter(Boolean).join(' · ')}
        chips={
          <>
            <Chip texto={p.estado === 'activo' ? 'Activa' : p.estado === 'baja' ? 'Baja' : 'Inactiva'} color={p.estado === 'activo' ? C.exito : C.tenue} />
            {esAlumno && <Chip texto="Alumno/a" color={C.info} />}
            {p.profesor && <Chip texto="Docente" color={C.acento} />}
            {p.cuenta && <Chip texto={p.cuenta.activo ? 'Con cuenta' : 'Cuenta desactivada'} />}
          </>
        }
      />
      {p.fusionada_en && <Aviso tono="alerta" texto={`Esta ficha se fusionó en ${p.fusionada_en.nombre}.`} />}

      <Acciones>
        {a.editar && <Boton titulo="Editar" icono="edit" variante="secundario" onPress={() => ir('/personas/[id]/editar', { id: p.id })} />}
        {!!p.telefono && <Boton titulo="Llamar" icono="call" variante="secundario" onPress={() => Linking.openURL(`tel:${p.telefono}`)} />}
        {a.inscribir_alumno && <Boton titulo="Inscribir como alumno" icono="school" variante="secundario" onPress={() => ir('/alumnos/nuevo', { persona_id: p.id })} />}
        {a.sumar_docente && <Boton titulo="Sumar al plantel" icono="co-present" variante="secundario" onPress={() => ir('/profesores/nuevo', { persona_id: p.id })} />}
        {a.crear_cuenta && <Boton titulo="Crear cuenta" icono="key" variante="secundario" onPress={() => ir('/usuarios/nuevo', { persona_id: p.id })} />}
        {a.ver_cuenta && p.cuenta && <Boton titulo="Cuenta y permisos" icono="admin-panel-settings" variante="secundario" onPress={() => ir('/usuarios/[id]', { id: p.cuenta!.id })} />}
        {a.fusionar && <Boton titulo="Fusionar duplicada" icono="merge-type" variante="secundario" onPress={() => setFusionando(true)} />}
      </Acciones>

      <Segmentos opciones={pestanas} valor={pestana} onChange={setPestana} />

      {pestana === 'info' && <Informacion p={p} />}
      {pestana === 'roles' && <Roles p={p} />}
      {pestana === 'cuenta' && (
        <View style={{ gap: E.m }}>
          {p.alumnos.map((al) => (
            <View key={al.id} style={{ gap: E.s }}>
              {p.alumnos.length > 1 && <Subtitulo>Ficha de alumno #{al.id}</Subtitulo>}
              <Acciones>
                {puede('pagos.create') && <Boton titulo="Registrar pago" icono="add-card" onPress={() => ir('/pagos/nuevo', { alumno_id: al.id })} />}
                {puede('pagos.view') && <Boton titulo="Ver pagos" icono="payments" variante="secundario" onPress={() => ir('/pagos', { alumno_id: al.id })} />}
                {puede('comprobantes.view') && <Boton titulo="Comprobantes" icono="fact-check" variante="secundario" onPress={() => ir('/comprobantes', { alumno_id: al.id })} />}
              </Acciones>
              {al.estado_cuenta ? <EstadoCuentaVista cuenta={al.estado_cuenta} /> : <Tenue>No tenés acceso a la información financiera de este alumno.</Tenue>}
            </View>
          ))}
        </View>
      )}
      {pestana === 'becas' && <Becas p={p} />}
      {pestana === 'actividad' && <Actividad p={p} />}
      {pestana === 'historial' && <HistorialAuditoria entidad="Persona" id={p.id} />}

      <Fusionar persona={p} visible={fusionando} onCerrar={() => setFusionando(false)} />
    </Pantalla>
  );
}

function Informacion({ p }: { p: Persona }) {
  return (
    <>
      <Tarjeta>
        <Dato etiqueta="Teléfono" valor={p.telefono} icono="call" onPress={p.telefono ? () => Linking.openURL(`tel:${p.telefono}`) : undefined} />
        <Dato etiqueta="Email" valor={p.email} icono="email" onPress={p.email ? () => Linking.openURL(`mailto:${p.email}`) : undefined} />
        <Dato etiqueta="Nacimiento" valor={p.fecha_nacimiento ? formatearFecha(p.fecha_nacimiento) : null} />
        <Dato etiqueta="Dirección" valor={p.direccion} />
        <Dato etiqueta="Emergencia" valor={[p.contacto_emergencia_nombre, p.contacto_emergencia_telefono].filter(Boolean).join(' · ')} />
        <Dato etiqueta="Observaciones" valor={p.observaciones} />
      </Tarjeta>
      {p.cuenta && (
        <Tarjeta>
          <Texto style={{ fontWeight: '800' }}>Cuenta de acceso</Texto>
          <Dato etiqueta="Usuario" valor={`@${p.cuenta.username}`} />
          <Dato etiqueta="Estado" valor={p.cuenta.activo ? 'Activa' : 'Desactivada'} />
          <Dato etiqueta="Último acceso" valor={p.cuenta.ultimo_acceso ? new Date(p.cuenta.ultimo_acceso).toLocaleString('es-AR') : 'Sin registro'} />
        </Tarjeta>
      )}
    </>
  );
}

function Roles({ p }: { p: Persona }) {
  return (
    <>
      <Tarjeta>
        {p.funciones.length === 0 && <Tenue>Todavía no tiene funciones en la escuela.</Tenue>}
        {p.funciones.map((f) => (
          <Fila key={f.clave} style={{ justifyContent: 'space-between' }}>
            <View style={{ flex: 1 }}>
              <Texto style={{ fontWeight: '700' }}>{f.rol_nombre}</Texto>
              <Tenue>{f.ambito_nombre}</Tenue>
            </View>
            <Chip texto={f.origen_etiqueta} />
          </Fila>
        ))}
      </Tarjeta>
      {p.alumnos.map((al) => (
        <ItemLista
          key={al.id}
          icono="school"
          titulo={`Alumno/a${al.activo ? '' : ' (inactivo)'}`}
          subtitulo={al.bloques.map((b) => b.nombre).join(' · ') || 'Sin bloque'}
          detalle={[al.sede?.nombre, al.instrumento].filter(Boolean).join(' · ') || null}
          onPress={al.puede_ver ? () => ir('/alumnos/[id]', { id: al.id }) : undefined}
        />
      ))}
      {p.profesor && (
        <ItemLista
          icono="co-present"
          titulo={`Docente${p.profesor.activo ? '' : ' (inactivo)'}`}
          subtitulo={p.profesor.bloques.map((b) => `${b.nombre} (${b.rol})`).join(' · ') || 'Sin bloques'}
          detalle={p.profesor.sedes.map((s) => `${s.rol} en ${s.nombre}`).join(' · ') || null}
          onPress={() => ir('/profesores/[id]', { id: p.profesor!.id })}
        />
      )}
      {p.permisos && (
        <>
          <Subtitulo>Qué puede hacer</Subtitulo>
          {Object.entries(p.permisos).map(([grupo, lista]) => (
            <Tarjeta key={grupo}>
              <Texto style={{ fontWeight: '800' }}>{grupo}</Texto>
              {lista.map((x) => <Tenue key={x.permiso}>{x.etiqueta} · {x.alcance}{x.via.length ? ` (${x.via.join(', ')})` : ''}</Tenue>)}
            </Tarjeta>
          ))}
        </>
      )}
    </>
  );
}

function Becas({ p }: { p: Persona }) {
  const q = useQuery({ queryKey: ['becas', 'persona', p.id], queryFn: () => api<{ data: Beca[] }>(`becas${qs({ persona_id: p.id })}`).then((r) => r.data), retry: false });
  const [editando, setEditando] = useState<Beca | null>(null);
  return (
    <View style={{ gap: E.s }}>
      {p.acciones.gestionar_becas && <Boton titulo="Otorgar beca" icono="volunteer-activism" onPress={() => ir('/personas/[id]/beca', { id: p.id })} />}
      {q.isPending && <Tenue>Cargando becas…</Tenue>}
      {q.isError && <Tenue>{(q.error as Error).message}</Tenue>}
      {q.data?.length === 0 && <Tenue>No tiene becas.</Tenue>}
      {q.data?.map((b) => (
        <ItemLista
          key={b.id}
          icono="volunteer-activism"
          colorIcono={b.estado === 'activa' ? C.exito : C.tenue}
          titulo={b.etiqueta}
          subtitulo={`${b.estado_nombre} · desde ${b.desde}${b.hasta ? ` hasta ${b.hasta}` : ''}`}
          detalle={[b.motivo, b.bloque?.nombre ?? b.sede?.nombre].filter(Boolean).join(' · ') || null}
          onPress={b.puede_gestionar ? () => setEditando(b) : undefined}
        />
      ))}
      <EditarBeca beca={editando} onCerrar={() => setEditando(null)} />
    </View>
  );
}

function Actividad({ p }: { p: Persona }) {
  return (
    <>
      <Subtitulo>Últimas asistencias</Subtitulo>
      {p.asistencias.length === 0 && <Tenue>Sin asistencias registradas.</Tenue>}
      {p.asistencias.map((x) => (
        <Fila key={x.id} style={{ justifyContent: 'space-between' }}>
          <Texto style={{ flex: 1 }}>{formatearFecha(x.fecha)} · {x.bloque}</Texto>
          <Chip texto={x.tipo_nombre} color={x.tipo === 'presente' ? C.exito : x.tipo === 'tarde' ? C.alerta : C.tenue} />
        </Fila>
      ))}
      <Subtitulo>Próximos eventos</Subtitulo>
      {p.eventos.length === 0 && <Tenue>No hay eventos próximos.</Tenue>}
      {p.eventos.map((e) => (
        <ItemLista key={e.id} icono="celebration" titulo={e.titulo} subtitulo={`${formatearFecha(e.fecha)}${e.hora_inicio ? ` · ${e.hora_inicio}` : ''}${e.sede ? ` · ${e.sede}` : ''}`} onPress={() => ir('/eventos/[id]', { id: e.id })} />
      ))}
      {p.inventario.length > 0 && (
        <>
          <Subtitulo>Instrumentos a cargo</Subtitulo>
          {p.inventario.map((i) => (
            <ItemLista key={i.id} icono="inventory-2" titulo={i.nombre} subtitulo={[i.codigo, i.sede].filter(Boolean).join(' · ')} onPress={() => ir('/inventario/[id]', { id: i.id })} />
          ))}
        </>
      )}
    </>
  );
}

function Fusionar({ persona, visible, onCerrar }: { persona: Persona; visible: boolean; onCerrar: () => void }) {
  const [duplicada, setDuplicada] = useState<{ id: number; nombre: string } | null>(null);
  const op = useOperacion(
    (dupId: number) => api<{ data: Persona }>(`personas/${persona.id}/fusionar`, { method: 'POST', body: { duplicada_id: dupId } }),
    { exito: 'Personas fusionadas: se unificaron fichas, cuenta y asignaciones', invalidar: ['personas'], alTerminar: onCerrar },
  );

  const fusionar = async () => {
    if (!duplicada) return;
    const ok = await confirmar({
      titulo: `¿Fusionar ${duplicada.nombre} en ${persona.nombre_completo}?`,
      mensaje: 'Se mueven sus fichas de alumno/docente, cuenta y asignaciones a esta persona. La duplicada queda dada de baja. Queda registrado en auditoría.',
      accion: 'Fusionar',
    });
    if (ok) op.mutate(duplicada.id);
  };

  return (
    <Hoja visible={visible} titulo="Fusionar persona duplicada" onCerrar={onCerrar}>
      <Tenue>Elegí la ficha duplicada. Se conserva {persona.nombre_completo}.</Tenue>
      <SelectorLista<number>
        etiqueta="Persona duplicada"
        valor={duplicada?.id ?? null}
        valorEtiqueta={duplicada?.nombre}
        onChange={(v, o) => setDuplicada(v && o ? { id: v, nombre: o.etiqueta } : null)}
        claveBusqueda="personas-fusion"
        buscar={async (texto) => {
          if (texto.length < 2) return [];
          const r = await api<{ data: PersonaResumen[] }>(`personas${qs({ q: texto })}`);
          return r.data.filter((x) => x.id !== persona.id).map((x) => ({ valor: x.id, etiqueta: x.nombre_completo, detalle: [x.dni ? `DNI ${x.dni}` : null, x.telefono].filter(Boolean).join(' · ') }));
        }}
      />
      {op.isError && <Aviso tono="peligro" texto={(op.error as Error).message} />}
      <Boton titulo="Fusionar" icono="merge-type" variante="peligro" deshabilitado={!duplicada} cargando={op.isPending} onPress={() => void fusionar()} />
    </Hoja>
  );
}
