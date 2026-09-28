import { router } from 'expo-router';

import { Campo, CampoMonto, ErrorFormulario, requeridos, Seccion, Selector, SelectorLista, useFormulario } from '@/components/form';
import { Boton, Cargando, ErrorVista, Pantalla } from '@/components/ui';
import { api } from '@/lib/api';
import { useCatalogo, useOperacion } from '@/lib/recursos';

export interface Facturacion {
  id: number;
  anio: number;
  mes: number;
  mes_nombre: string;
  sede: { id: number; nombre: string } | null;
  cantidad_alumnos: number;
  monto_facturado: number;
  monto_previsto: number | null;
  diferencia: number | null;
  notas: string | null;
  puede_editar: boolean;
}

export interface CatalogoFacturacion { meses: Record<string, string>; sedes: { id: number; nombre: string }[]; puede_general: boolean; puede_cierre: boolean }

/** Carga o corrección de la facturación de un mes (el período y la sede no se cambian al editar). */
export function FormFacturacion({ item }: { item?: Facturacion }) {
  const cat = useCatalogo<CatalogoFacturacion>('facturacion/catalogo', 1);
  const hoy = new Date();
  const f = useFormulario({
    sede_id: (item?.sede?.id ?? null) as number | null,
    anio: item?.anio ?? hoy.getFullYear(),
    mes: item?.mes ?? hoy.getMonth() + 1,
    cantidad_alumnos: item ? String(item.cantidad_alumnos) : '',
    monto_facturado: item ? String(item.monto_facturado) : '',
    monto_previsto: item?.monto_previsto != null ? String(item.monto_previsto) : '',
    notas: item?.notas ?? '',
  });
  const { valores: v, set, errores: e } = f;
  const guardar = useOperacion(
    (d: typeof v) => api<{ data: Facturacion }>(item ? `facturacion/${item.id}` : 'facturacion', {
      method: item ? 'PUT' : 'POST',
      body: { ...d, cantidad_alumnos: Number(d.cantidad_alumnos), monto_facturado: Number(d.monto_facturado), monto_previsto: d.monto_previsto === '' ? null : Number(d.monto_previsto), notas: d.notas || null },
    }),
    { exito: item ? 'Facturación actualizada' : 'Facturación mensual registrada', invalidar: ['facturacion'], alTerminar: () => router.back() },
  );

  if (cat.isPending) return <Cargando />;
  if (cat.isError) return <ErrorVista error={cat.error} onReintentar={() => cat.refetch()} />;
  const c = cat.data;

  return (
    <Pantalla>
      <ErrorFormulario mensaje={f.errorGeneral} />
      {!item && (
        <Seccion titulo="Período">
          <SelectorLista<number> etiqueta="Sede" valor={v.sede_id} onChange={(x) => set('sede_id', x)} error={e.sede_id} permitirVacio={c.puede_general}
            placeholder={c.puede_general ? 'Toda la escuela' : 'Elegir sede'} opciones={c.sedes.map((s) => ({ valor: s.id, etiqueta: s.nombre }))} />
          <Selector etiqueta="Año" opciones={[hoy.getFullYear() - 1, hoy.getFullYear()].map((a) => ({ valor: a, etiqueta: String(a) }))} valor={v.anio} onChange={(x) => set('anio', x ?? hoy.getFullYear())} error={e.anio} />
          <Selector etiqueta="Mes" opciones={Object.entries(c.meses).map(([k, n]) => ({ valor: Number(k), etiqueta: n.slice(0, 3) }))} valor={v.mes} onChange={(x) => set('mes', x ?? 1)} error={e.mes} />
        </Seccion>
      )}
      <Seccion titulo="Resultado del mes">
        <Campo etiqueta="Cantidad de alumnos" requerido valor={v.cantidad_alumnos} onChange={(x) => set('cantidad_alumnos', x.replace(/\D/g, ''))} teclado="number-pad" error={e.cantidad_alumnos} />
        <CampoMonto etiqueta="Monto facturado ($)" requerido valor={v.monto_facturado} onChange={(x) => set('monto_facturado', x)} error={e.monto_facturado} />
        <CampoMonto etiqueta="Monto previsto ($)" valor={v.monto_previsto} onChange={(x) => set('monto_previsto', x)} error={e.monto_previsto} />
        <Campo etiqueta="Notas" valor={v.notas} onChange={(x) => set('notas', x)} error={e.notas} multilinea maxLength={500} />
      </Seccion>
      <Boton titulo={item ? 'Guardar cambios' : 'Registrar facturación'} icono="save" grande cargando={f.enviando}
        onPress={() => void f.enviar((d) => guardar.mutateAsync(d), (d) => ({
          ...requeridos(d, { cantidad_alumnos: 'La cantidad de alumnos', monto_facturado: 'El monto facturado' }),
          ...(!item && !c.puede_general && !d.sede_id ? { sede_id: 'Elegí la sede.' } : {}),
        }))} />
    </Pantalla>
  );
}
