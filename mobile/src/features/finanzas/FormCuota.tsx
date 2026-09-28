import { router } from 'expo-router';
import { Pressable, View } from 'react-native';

import { Campo, CampoFecha, CampoMonto, ErrorFormulario, Interruptor, requeridos, Seccion, Selector, SelectorLista, useFormulario } from '@/components/form';
import { Boton, Cargando, ErrorVista, Fila, Icon, Pantalla, Tenue, Texto } from '@/components/ui';
import { api, qs } from '@/lib/api';
import { useCatalogo, useOperacion } from '@/lib/recursos';
import { C } from '@/lib/theme';

import { type CuotaFicha, MESES } from './tipos';

interface Catalogo {
  alcances: Record<string, string>;
  puede_general: boolean;
  sedes: { id: number; nombre: string }[];
  bloques: { id: number; nombre: string; sede: string | null }[];
}

/** Alta/edición de cuota: período, monto, vencimiento, alcance y (opcional) lista de alumnos. */
export function FormCuota({ cuota }: { cuota?: CuotaFicha }) {
  const cat = useCatalogo<Catalogo>('cuotas/catalogo', 1);
  const anio = new Date().getFullYear();
  const f = useFormulario({
    nombre: cuota?.nombre ?? '',
    anio: cuota?.anio ?? anio,
    mes: (cuota?.mes ?? new Date().getMonth() + 1) as number,
    monto: cuota ? String(cuota.monto) : '',
    fecha_vencimiento: cuota?.vencimiento ?? '',
    alcance: (cuota?.alcance ?? 'general') as string,
    sede_id: (cuota?.sede_id ?? null) as number | null,
    bloque_id: (cuota?.bloque_id ?? null) as number | null,
    descripcion: cuota?.descripcion ?? '',
    activo: cuota?.activo ?? true,
    alumnos: cuota?.alumnos ?? ([] as { id: number; nombre: string }[]),
  });
  const { valores: v, set, errores: e } = f;
  const guardar = useOperacion(
    (d: typeof v) => {
      const { alumnos, ...resto } = d;
      return api<{ data: CuotaFicha }>(cuota ? `cuotas/${cuota.id}` : 'cuotas', {
        method: cuota ? 'PUT' : 'POST',
        body: { ...resto, monto: Number(d.monto), fecha_vencimiento: d.fecha_vencimiento || null, descripcion: d.descripcion || null, alumno_ids: alumnos.map((a) => a.id) },
      });
    },
    {
      exito: cuota ? 'Cuota actualizada' : 'Cuota creada correctamente',
      invalidar: ['cuotas', 'pagos', 'alumnos', 'personas', ['catalogo', 'pagos/cuotas-para-cobrar']],
      alTerminar: (r) => (cuota ? router.back() : router.replace({ pathname: '/cuotas/[id]', params: { id: String(r.data.id) } } as never)),
    },
  );

  if (cat.isPending) return <Cargando />;
  if (cat.isError) return <ErrorVista error={cat.error} onReintentar={() => cat.refetch()} />;
  const c = cat.data;
  const alcances = Object.entries(c.alcances).filter(([k]) => k !== 'general' || c.puede_general || cuota?.alcance === 'general').map(([valor, etiqueta]) => ({ valor, etiqueta }));

  return (
    <Pantalla>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Seccion titulo="Cuota">
        <Campo etiqueta="Nombre" requerido valor={v.nombre} onChange={(x) => set('nombre', x)} error={e.nombre} placeholder="Ej.: Cuota marzo" />
        <Selector etiqueta="Año" opciones={[anio - 1, anio, anio + 1].map((a) => ({ valor: a, etiqueta: String(a) }))} valor={v.anio} onChange={(x) => set('anio', x ?? anio)} error={e.anio} requerido />
        <Selector etiqueta="Mes" opciones={MESES.map((m, i) => ({ valor: i + 1, etiqueta: m.slice(0, 3) }))} valor={v.mes} onChange={(x) => set('mes', x ?? 1)} error={e.mes} requerido />
        <CampoMonto etiqueta="Monto ($)" requerido valor={v.monto} onChange={(x) => set('monto', x)} error={e.monto} />
        <CampoFecha etiqueta="Vencimiento" valor={v.fecha_vencimiento} onChange={(x) => set('fecha_vencimiento', x)} error={e.fecha_vencimiento} opcional />
        <Interruptor etiqueta="Activa" ayuda="Las cuotas inactivas no se cobran ni cuentan en la deuda." valor={v.activo} onChange={(x) => set('activo', x)} />
      </Seccion>
      <Seccion titulo="A quién aplica">
        <Selector opciones={alcances} valor={v.alcance} onChange={(x) => set('alcance', x ?? 'general')} error={e.alcance} />
        {v.alcance === 'sede' && (
          <SelectorLista<number> etiqueta="Sede" requerido valor={v.sede_id} onChange={(x) => set('sede_id', x)} error={e.sede_id} valorEtiqueta={cuota?.sede}
            opciones={c.sedes.map((s) => ({ valor: s.id, etiqueta: s.nombre }))} />
        )}
        {v.alcance === 'bloque' && (
          <SelectorLista<number> etiqueta="Bloque" requerido valor={v.bloque_id} onChange={(x) => set('bloque_id', x)} error={e.bloque_id} valorEtiqueta={cuota?.bloque}
            opciones={c.bloques.map((b) => ({ valor: b.id, etiqueta: b.nombre, detalle: b.sede ?? undefined }))} />
        )}
        <Tenue>Solo para algunos alumnos (opcional). Si no elegís ninguno, aplica a todos los del alcance.</Tenue>
        {v.alumnos.map((a) => (
          <Fila key={a.id} style={{ justifyContent: 'space-between', minHeight: 40 }}>
            <Texto>{a.nombre}</Texto>
            <Pressable onPress={() => set('alumnos', v.alumnos.filter((x) => x.id !== a.id))} accessibilityRole="button" accessibilityLabel={`Quitar ${a.nombre}`} hitSlop={10}>
              <Icon name="close" size={22} color={C.peligro} />
            </Pressable>
          </Fila>
        ))}
        <View>
          <SelectorLista<number>
            etiqueta="Agregar alumno"
            valor={null}
            placeholder="Buscar alumno…"
            claveBusqueda="alumnos-cuota"
            buscar={async (t) => (t.length < 2 ? [] : (await api<{ data: { id: number; nombre: string; sede?: { nombre: string } | null }[] }>(`alumnos${qs({ q: t })}`)).data
              .filter((a) => !v.alumnos.some((x) => x.id === a.id)).map((a) => ({ valor: a.id, etiqueta: a.nombre, detalle: a.sede?.nombre })))}
            onChange={(id, o) => id && o && set('alumnos', [...v.alumnos, { id, nombre: o.etiqueta }])}
          />
        </View>
        <Campo etiqueta="Descripción" valor={v.descripcion} onChange={(x) => set('descripcion', x)} error={e.descripcion} multilinea maxLength={500} />
      </Seccion>
      <Boton titulo={cuota ? 'Guardar cambios' : 'Crear cuota'} icono="save" grande cargando={f.enviando}
        onPress={() => void f.enviar((d) => guardar.mutateAsync(d), (d) => ({
          ...requeridos(d, { nombre: 'El nombre', monto: 'El monto' }),
          ...(d.alcance === 'sede' && !d.sede_id ? { sede_id: 'Elegí la sede.' } : {}),
          ...(d.alcance === 'bloque' && !d.bloque_id ? { bloque_id: 'Elegí el bloque.' } : {}),
        }))} />
    </Pantalla>
  );
}
