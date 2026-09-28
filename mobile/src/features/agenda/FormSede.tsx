import { router } from 'expo-router';

import { Campo, CampoMonto, ErrorFormulario, Interruptor, requeridos, Seccion, Selector, useFormulario } from '@/components/form';
import { Boton, Pantalla } from '@/components/ui';
import { api } from '@/lib/api';
import { useCatalogo, useOperacion } from '@/lib/recursos';

import type { Sede } from './tipos';

const numero = (x: string) => (x === '' ? null : Number(x));

export function FormSede({ sede }: { sede?: Sede }) {
  const cat = useCatalogo<{ tipos_propiedad: Record<string, string> }>('sedes/catalogo', 24);
  const f = useFormulario({
    nombre: sede?.nombre ?? '',
    direccion: sede?.direccion ?? '',
    tipo_propiedad: sede?.tipo_propiedad ?? 'alquilada',
    costo_alquiler_mensual: sede?.costo_alquiler_mensual != null ? String(sede.costo_alquiler_mensual) : '',
    liquidacion_retencion_escuela: sede?.liquidacion_retencion_escuela != null ? String(sede.liquidacion_retencion_escuela) : '',
    liquidacion_porc_docente: sede?.liquidacion_porc_docente != null ? String(sede.liquidacion_porc_docente) : '40',
    activo: sede?.activo ?? true,
  });
  const { valores: v, set, errores: e } = f;
  const guardar = useOperacion(
    (d: typeof v) => api<{ data: Sede }>(sede ? `sedes/${sede.id}` : 'sedes', {
      method: sede ? 'PUT' : 'POST',
      body: {
        ...d,
        direccion: d.direccion || null,
        costo_alquiler_mensual: numero(d.costo_alquiler_mensual),
        liquidacion_retencion_escuela: numero(d.liquidacion_retencion_escuela),
        liquidacion_porc_docente: numero(d.liquidacion_porc_docente),
      },
    }),
    {
      exito: sede ? 'Sede actualizada' : 'Sede creada correctamente',
      invalidar: ['sedes', ['catalogo', 'sedes']],
      alTerminar: (r) => (sede ? router.back() : router.replace({ pathname: '/sedes/[id]', params: { id: String(r.data.id) } } as never)),
    },
  );
  // Los datos financieros solo llegan si quien edita puede verlos.
  const conFinanzas = !sede || sede.liquidacion_porc_docente !== null;

  return (
    <Pantalla>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Seccion titulo="Sede">
        <Campo etiqueta="Nombre" requerido valor={v.nombre} onChange={(x) => set('nombre', x)} error={e.nombre} />
        <Campo etiqueta="Dirección" valor={v.direccion} onChange={(x) => set('direccion', x)} error={e.direccion} />
        <Selector etiqueta="Propiedad" opciones={Object.entries(cat.data?.tipos_propiedad ?? {}).map(([valor, etiqueta]) => ({ valor, etiqueta }))} valor={v.tipo_propiedad} onChange={(x) => set('tipo_propiedad', x ?? 'alquilada')} error={e.tipo_propiedad} />
        <Interruptor etiqueta="Sede activa" valor={v.activo} onChange={(x) => set('activo', x)} ayuda="Las sedes inactivas no aparecen para inscribir ni cargar." />
      </Seccion>
      {conFinanzas && (
        <Seccion titulo="Costos y liquidación docente" ayuda="Docente cobra: (cuota − retención de la escuela) × porcentaje.">
          <CampoMonto etiqueta="Alquiler mensual ($)" valor={v.costo_alquiler_mensual} onChange={(x) => set('costo_alquiler_mensual', x)} error={e.costo_alquiler_mensual} />
          <CampoMonto etiqueta="Retención de la escuela por cuota ($)" valor={v.liquidacion_retencion_escuela} onChange={(x) => set('liquidacion_retencion_escuela', x)} error={e.liquidacion_retencion_escuela} />
          <Campo etiqueta="Porcentaje docente (%)" valor={v.liquidacion_porc_docente} onChange={(x) => set('liquidacion_porc_docente', x.replace(/[^0-9.]/g, ''))} teclado="decimal-pad" error={e.liquidacion_porc_docente} />
        </Seccion>
      )}
      <Boton titulo={sede ? 'Guardar cambios' : 'Crear sede'} icono="save" grande cargando={f.enviando} onPress={() => void f.enviar((d) => guardar.mutateAsync(d), (d) => requeridos(d, { nombre: 'El nombre' }))} />
    </Pantalla>
  );
}
