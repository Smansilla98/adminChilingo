import { router } from 'expo-router';

import { Campo, ErrorFormulario, Interruptor, requeridos, Seccion, Selector, SelectorLista, SelectorMultiple, useFormulario } from '@/components/form';
import { Boton, Cargando, ErrorVista, Pantalla } from '@/components/ui';
import { api } from '@/lib/api';
import { useCatalogo, useOperacion } from '@/lib/recursos';

export interface BloqueFicha {
  id: number;
  nombre: string;
  anio: number;
  activo: boolean;
  sede: { id: number; nombre: string } | null;
  cantidad_alumnos: number;
  corresponde_a: string | null;
  cantidad_max_alumnos: number;
  tambores: string[];
  profesor: { id: number; nombre: string } | null;
  profesores: { id: number; nombre: string; rol: string }[];
  horarios_detalle: { id: number; dia: number; dia_nombre: string; inicio: string; fin: string }[];
  eventos: { id: number; titulo: string; fecha: string }[];
  acciones: { editar: boolean; eliminar: boolean; tomar_asistencia: boolean; ver_alumnos: boolean };
}

interface Catalogo { sedes: { id: number; nombre: string }[]; profesores: { id: number; nombre: string }[]; tambores: string[]; dias: Record<string, string> }

export function FormBloque({ bloque, sedeInicial }: { bloque?: BloqueFicha; sedeInicial?: number }) {
  const cat = useCatalogo<Catalogo>('bloques/catalogo', 1);
  const f = useFormulario({
    nombre: bloque?.nombre ?? '',
    anio: bloque?.anio ?? 1,
    sede_id: (bloque?.sede?.id ?? sedeInicial ?? null) as number | null,
    profesor_id: (bloque?.profesor?.id ?? null) as number | null,
    corresponde_a: bloque?.corresponde_a ?? '',
    cantidad_max_alumnos: String(bloque?.cantidad_max_alumnos ?? 30),
    tambores: bloque?.tambores ?? ([] as string[]),
    activo: bloque?.activo ?? true,
  });
  const { valores: v, set, errores: e } = f;
  const guardar = useOperacion(
    (d: typeof v) => api<{ data: BloqueFicha }>(bloque ? `bloques/${bloque.id}` : 'bloques', {
      method: bloque ? 'PUT' : 'POST',
      body: { ...d, cantidad_max_alumnos: Number(d.cantidad_max_alumnos), corresponde_a: d.corresponde_a || null },
    }),
    {
      exito: bloque ? 'Bloque actualizado' : 'Bloque creado correctamente',
      invalidar: ['bloques', 'sedes'],
      alTerminar: (r) => (bloque ? router.back() : router.replace({ pathname: '/bloques/[id]', params: { id: String(r.data.id) } } as never)),
    },
  );

  if (cat.isPending) return <Cargando />;
  if (cat.isError) return <ErrorVista error={cat.error} onReintentar={() => cat.refetch()} />;
  const c = cat.data;

  return (
    <Pantalla>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Seccion titulo="Bloque">
        <Campo etiqueta="Nombre" requerido valor={v.nombre} onChange={(x) => set('nombre', x)} error={e.nombre} />
        <Selector etiqueta="Año" requerido opciones={[1, 2, 3, 4, 5, 6].map((n) => ({ valor: n, etiqueta: `${n}°` }))} valor={v.anio} onChange={(x) => set('anio', x ?? 1)} error={e.anio} />
        <SelectorLista<number> etiqueta="Sede" requerido valor={v.sede_id} onChange={(x) => set('sede_id', x)} error={e.sede_id}
          opciones={c.sedes.map((s) => ({ valor: s.id, etiqueta: s.nombre }))} valorEtiqueta={bloque?.sede?.nombre} />
        <SelectorLista<number> etiqueta="Docente titular" valor={v.profesor_id} onChange={(x) => set('profesor_id', x)} error={e.profesor_id} permitirVacio placeholder="Sin titular"
          opciones={c.profesores.map((p) => ({ valor: p.id, etiqueta: p.nombre }))} valorEtiqueta={bloque?.profesor?.nombre} />
        <Campo etiqueta="Corresponde a" valor={v.corresponde_a} onChange={(x) => set('corresponde_a', x)} error={e.corresponde_a} placeholder="Ej.: adultos, infantil" />
        <Campo etiqueta="Cupo máximo" requerido valor={v.cantidad_max_alumnos} onChange={(x) => set('cantidad_max_alumnos', x.replace(/\D/g, ''))} teclado="number-pad" error={e.cantidad_max_alumnos} />
        <Interruptor etiqueta="Bloque activo" valor={v.activo} onChange={(x) => set('activo', x)} />
      </Seccion>
      <Seccion titulo="Tambores">
        <SelectorMultiple opciones={c.tambores.map((t) => ({ valor: t, etiqueta: t }))} valores={v.tambores} onChange={(x) => set('tambores', x)} error={e.tambores} />
      </Seccion>
      <Boton titulo={bloque ? 'Guardar cambios' : 'Crear bloque'} icono="save" grande cargando={f.enviando}
        onPress={() => void f.enviar((d) => guardar.mutateAsync(d), (d) => ({
          ...requeridos(d, { nombre: 'El nombre', sede_id: 'La sede', cantidad_max_alumnos: 'El cupo' }),
          ...(Number(d.cantidad_max_alumnos) < 1 ? { cantidad_max_alumnos: 'El cupo tiene que ser al menos 1.' } : {}),
        }))} />
    </Pantalla>
  );
}
