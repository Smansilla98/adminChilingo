import { router } from 'expo-router';

import { Campo, CampoFecha, ErrorFormulario, Interruptor, requeridos, Seccion, Selector, SelectorLista, SelectorMultiple, useFormulario } from '@/components/form';
import { Aviso, Boton, Cargando, ErrorVista, Pantalla } from '@/components/ui';
import { api } from '@/lib/api';
import { useCatalogo, useOperacion } from '@/lib/recursos';

export interface AlumnoFicha {
  id: number;
  persona_id: number | null;
  nombre: string;
  activo: boolean;
  instrumento: string | null;
  instrumento_secundario: string | null;
  tipo_tambor: string | null;
  tambor_procedencia: string | null;
  telefono: string | null;
  dni?: string | null;
  fecha_nacimiento?: string | null;
  sede: { id: number; nombre: string } | null;
  bloque_principal_id: number | null;
  bloques_detalle: { id: number; nombre: string; sede: string | null; principal: boolean }[];
  es_docente: boolean;
  acciones: { editar: boolean; eliminar: boolean; ver_finanzas: boolean; ver_persona: boolean };
}

interface Catalogo {
  sedes: { id: number; nombre: string }[];
  bloques: { id: number; nombre: string; sede_id: number; sede: string | null }[];
  instrumentos: string[];
  tipos_tambor: string[];
  procedencias_tambor: string[];
  profesores: { id: number; nombre: string }[];
}

const opciones = (xs: string[]) => xs.map((x) => ({ valor: x, etiqueta: x }));

/** Inscripción / edición de alumno. Con `persona` inscribe a alguien que ya está en el sistema. */
export function FormAlumno({ alumno, persona }: { alumno?: AlumnoFicha; persona?: { id: number; nombre: string; dni: string | null; telefono: string | null; fecha_nacimiento: string | null; es_docente: boolean } }) {
  const cat = useCatalogo<Catalogo>('alumnos/catalogo', 1);
  const f = useFormulario({
    nombre_apellido: alumno?.nombre ?? persona?.nombre ?? '',
    dni: alumno?.dni ?? persona?.dni ?? '',
    fecha_nacimiento: alumno?.fecha_nacimiento ?? persona?.fecha_nacimiento ?? '',
    telefono: alumno?.telefono ?? persona?.telefono ?? '',
    instrumento_principal: alumno?.instrumento ?? '',
    instrumento_secundario: alumno?.instrumento_secundario ?? '',
    tipo_tambor: alumno?.tipo_tambor ?? '',
    tambor_procedencia: alumno?.tambor_procedencia ?? '',
    sede_id: (alumno?.sede?.id ?? null) as number | null,
    bloque_ids: alumno?.bloques_detalle.map((b) => b.id) ?? ([] as number[]),
    bloque_principal_id: (alumno?.bloque_principal_id ?? null) as number | null,
    activo: alumno?.activo ?? true,
    crear_perfil_profesor: false,
  });
  const { valores: v, set, errores: e } = f;
  const guardar = useOperacion(
    (d: typeof v) => api<{ data: AlumnoFicha }>(alumno ? `alumnos/${alumno.id}` : 'alumnos', {
      method: alumno ? 'PUT' : 'POST',
      body: {
        ...d,
        persona_id: persona?.id,
        dni: d.dni || null,
        telefono: d.telefono || null,
        instrumento_secundario: d.instrumento_secundario || null,
        tipo_tambor: d.tipo_tambor || null,
        tambor_procedencia: d.tambor_procedencia || null,
        bloque_principal_id: d.bloque_principal_id ?? d.bloque_ids[0] ?? null,
      },
    }),
    {
      exito: alumno ? 'Alumno actualizado' : 'Alumno inscripto correctamente',
      invalidar: ['alumnos', 'personas', 'bloques'],
      alTerminar: (r) => (alumno ? router.back() : router.replace({ pathname: '/alumnos/[id]', params: { id: String(r.data.id) } } as never)),
    },
  );

  if (cat.isPending) return <Cargando />;
  if (cat.isError) return <ErrorVista error={cat.error} onReintentar={() => cat.refetch()} />;
  const c = cat.data;
  const bloques = c.bloques.filter((b) => !v.sede_id || b.sede_id === v.sede_id || v.bloque_ids.includes(b.id));

  return (
    <Pantalla>
      <ErrorFormulario mensaje={f.errorGeneral} />
      {persona && <Aviso tono="info" texto={`Se inscribe a ${persona.nombre}. Sus datos personales se comparten con su ficha de persona.`} />}
      <Seccion titulo="Datos">
        <Campo etiqueta="Nombre y apellido" requerido valor={v.nombre_apellido} onChange={(x) => set('nombre_apellido', x)} error={e.nombre_apellido} autoCapitalize="words" />
        <Campo etiqueta="DNI" valor={v.dni} onChange={(x) => set('dni', x)} error={e.dni} teclado="number-pad" />
        <CampoFecha etiqueta="Fecha de nacimiento" requerido valor={v.fecha_nacimiento} onChange={(x) => set('fecha_nacimiento', x)} error={e.fecha_nacimiento} />
        <Campo etiqueta="Teléfono" valor={v.telefono} onChange={(x) => set('telefono', x)} error={e.telefono} teclado="phone-pad" />
        <Interruptor etiqueta="Activo" valor={v.activo} onChange={(x) => set('activo', x)} />
      </Seccion>
      <Seccion titulo="Sede y bloques">
        <SelectorLista<number> etiqueta="Sede" requerido valor={v.sede_id} onChange={(x) => set('sede_id', x)} error={e.sede_id} valorEtiqueta={alumno?.sede?.nombre}
          opciones={c.sedes.map((s) => ({ valor: s.id, etiqueta: s.nombre }))} />
        <SelectorMultiple etiqueta="Bloques" opciones={bloques.map((b) => ({ valor: b.id, etiqueta: `${b.nombre}${b.sede ? ` · ${b.sede}` : ''}` }))} valores={v.bloque_ids}
          onChange={(x) => { set('bloque_ids', x); if (v.bloque_principal_id && !x.includes(v.bloque_principal_id)) set('bloque_principal_id', x[0] ?? null); }} error={e.bloque_ids} />
        {v.bloque_ids.length > 1 && (
          <Selector etiqueta="Bloque principal" opciones={v.bloque_ids.map((id) => ({ valor: id, etiqueta: c.bloques.find((b) => b.id === id)?.nombre ?? `#${id}` }))}
            valor={v.bloque_principal_id ?? v.bloque_ids[0]} onChange={(x) => set('bloque_principal_id', x)} error={e.bloque_principal_id} />
        )}
      </Seccion>
      <Seccion titulo="Instrumento">
        <Selector etiqueta="Instrumento principal" requerido opciones={opciones(c.instrumentos)} valor={v.instrumento_principal} onChange={(x) => set('instrumento_principal', x ?? '')} error={e.instrumento_principal} />
        <Selector etiqueta="Instrumento secundario" permitirVacio="Ninguno" opciones={opciones(c.instrumentos)} valor={v.instrumento_secundario || null} onChange={(x) => set('instrumento_secundario', x ?? '')} />
        <Selector etiqueta="Tambor" permitirVacio="Sin dato" opciones={opciones(c.tipos_tambor)} valor={v.tipo_tambor || null} onChange={(x) => set('tipo_tambor', x ?? '')} error={e.tipo_tambor} />
        <Selector etiqueta="Procedencia del tambor" permitirVacio="Sin dato" opciones={opciones(c.procedencias_tambor)} valor={v.tambor_procedencia || null} onChange={(x) => set('tambor_procedencia', x ?? '')} error={e.tambor_procedencia} />
      </Seccion>
      {!alumno?.es_docente && !persona?.es_docente && (
        <Seccion>
          <Interruptor etiqueta="También da clases" ayuda="Crea su ficha docente vinculada a la misma persona." valor={v.crear_perfil_profesor} onChange={(x) => set('crear_perfil_profesor', x)} />
        </Seccion>
      )}
      <Boton titulo={alumno ? 'Guardar cambios' : 'Inscribir'} icono="save" grande cargando={f.enviando}
        onPress={() => void f.enviar((d) => guardar.mutateAsync(d), (d) => requeridos(d, { nombre_apellido: 'El nombre', fecha_nacimiento: 'La fecha de nacimiento', sede_id: 'La sede', instrumento_principal: 'El instrumento' }))} />
    </Pantalla>
  );
}
