import { router } from 'expo-router';

import { Campo, CampoFecha, CampoMonto, ErrorFormulario, hoyIso, Interruptor, requeridos, Seccion, Selector, SelectorLista, useFormulario } from '@/components/form';
import { Aviso, Boton, Cargando, ErrorVista, Pantalla } from '@/components/ui';
import { api } from '@/lib/api';
import { useCatalogo, useOperacion } from '@/lib/recursos';

import type { CatalogoEstadosItem, DatosDevolucion, DatosPrestamo, TipoResponsable } from './tipos';

const DATOS_PRESTAR = (condicion: string): DatosPrestamo => ({
  responsable_tipo: 'alumno', alumno_id: null, persona_id: null, responsable_nombre: '',
  entregado_el: hoyIso(), devolucion_prevista: '', deposito: '', condicion_entrega: condicion, notas: '',
});

const TIPOS: { valor: TipoResponsable; etiqueta: string }[] = [
  { valor: 'alumno', etiqueta: 'Alumno' },
  { valor: 'persona', etiqueta: 'Persona' },
  { valor: 'nombre', etiqueta: 'Otro (escribir el nombre)' },
];

/** Alta de un préstamo: a quién, desde cuándo, hasta cuándo, seña y en qué condición sale. */
export function FormPrestar({ itemId, condicionActual, nombreItem }: { itemId: number; condicionActual: string; nombreItem: string }) {
  const cat = useCatalogo<CatalogoEstadosItem>('inventario/catalogos', 12);
  const f = useFormulario<DatosPrestamo>({ ...DATOS_PRESTAR(condicionActual), devolucion_prevista: sumarDias(hoyIso(), 14) });
  const { valores: v, set, errores: e } = f;
  const prestar = useOperacion(
    (cuerpo: Record<string, unknown>) => api(`inventario/${itemId}/prestamos`, { method: 'POST', body: cuerpo }),
    { exito: `${nombreItem} prestado`, invalidar: ['inventario'], alTerminar: () => router.back() },
  );

  if (cat.isPending) return <Cargando />;
  if (cat.isError) return <ErrorVista error={cat.error} onReintentar={() => cat.refetch()} />;

  const enviar = () => f.enviar((d) => prestar.mutateAsync(cuerpoPrestamo(d)), (d) => ({
    ...requeridos(d, { entregado_el: 'La fecha de entrega', devolucion_prevista: 'La devolución prevista' }),
    ...(d.responsable_tipo === 'alumno' && !d.alumno_id ? { alumno_id: 'Elegí al alumno.' } : {}),
    ...(d.responsable_tipo === 'persona' && !d.persona_id ? { persona_id: 'Elegí a la persona.' } : {}),
    ...(d.responsable_tipo === 'nombre' && !d.responsable_nombre.trim() ? { responsable_nombre: 'Escribí quién se lo lleva.' } : {}),
    ...(d.devolucion_prevista && d.entregado_el && d.devolucion_prevista < d.entregado_el ? { devolucion_prevista: 'Tiene que ser después de la entrega.' } : {}),
  }));

  return (
    <Pantalla>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Seccion titulo="Quién se lo lleva">
        <Selector etiqueta="Responsable" opciones={TIPOS} valor={v.responsable_tipo} onChange={(x) => x && set('responsable_tipo', x)} />
        {v.responsable_tipo === 'alumno' && (
          <SelectorLista etiqueta="Alumno" requerido valor={v.alumno_id} valorEtiqueta={null} error={e.alumno_id} onChange={(x) => set('alumno_id', x)}
            buscar={async (q) => (await api<{ data: { id: number; nombre: string; sede: { nombre: string } | null }[] }>(`alumnos?q=${encodeURIComponent(q)}`)).data
              .map((a) => ({ valor: a.id, etiqueta: a.nombre, detalle: a.sede?.nombre }))} />
        )}
        {v.responsable_tipo === 'persona' && (
          <SelectorLista etiqueta="Persona" requerido valor={v.persona_id} valorEtiqueta={null} error={e.persona_id} onChange={(x) => set('persona_id', x)}
            buscar={async (q) => (await api<{ data: { id: number; nombre_completo: string }[] }>(`personas?q=${encodeURIComponent(q)}`)).data
              .map((p) => ({ valor: p.id, etiqueta: p.nombre_completo }))} />
        )}
        {v.responsable_tipo === 'nombre' && (
          <Campo etiqueta="Nombre" requerido valor={v.responsable_nombre} onChange={(x) => set('responsable_nombre', x)} error={e.responsable_nombre} maxLength={255} />
        )}
      </Seccion>
      <Seccion titulo="Cuándo">
        <CampoFecha etiqueta="Se entrega el" requerido valor={v.entregado_el} onChange={(x) => set('entregado_el', x)} error={e.entregado_el} />
        <CampoFecha etiqueta="Devolver antes del" requerido valor={v.devolucion_prevista} onChange={(x) => set('devolucion_prevista', x)} error={e.devolucion_prevista} />
      </Seccion>
      <Seccion titulo="Condición y seña">
        <Selector etiqueta="Cómo sale" opciones={Object.entries(cat.data.estados).map(([valor, etiqueta]) => ({ valor, etiqueta }))} valor={v.condicion_entrega}
          onChange={(x) => x && set('condicion_entrega', x)} error={e.condicion_entrega} requerido />
        <CampoMonto etiqueta="Seña (opcional)" valor={v.deposito} onChange={(x) => set('deposito', x)} error={e.deposito} />
        <Campo etiqueta="Notas" multilinea valor={v.notas} onChange={(x) => set('notas', x)} error={e.notas} maxLength={400} />
      </Seccion>
      <Boton titulo="Registrar préstamo" icono="handshake" grande cargando={f.enviando || prestar.isPending} onPress={() => void enviar()} />
    </Pantalla>
  );
}

/** Devolución: cuándo vuelve, en qué condición (si se rompió, queda en reparación) y si se devolvió la seña. */
export function FormDevolver({ prestamoId, condicionActual, deposito, nombreItem }: { prestamoId: number; condicionActual: string; deposito: number | null; nombreItem: string }) {
  const cat = useCatalogo<CatalogoEstadosItem>('inventario/catalogos', 12);
  const f = useFormulario<DatosDevolucion>({ devuelto_el: hoyIso(), condicion_devolucion: condicionActual, deposito_devuelto: false, notas: '' });
  const { valores: v, set, errores: e } = f;
  const devolver = useOperacion(
    (cuerpo: DatosDevolucion) => api(`inventario/prestamos/${prestamoId}/devolucion`, { method: 'POST', body: cuerpo }),
    { exito: `${nombreItem} devuelto`, invalidar: ['inventario'], alTerminar: () => router.back() },
  );

  if (cat.isPending) return <Cargando />;
  if (cat.isError) return <ErrorVista error={cat.error} onReintentar={() => cat.refetch()} />;

  return (
    <Pantalla>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Seccion titulo="Devolución">
        <CampoFecha etiqueta="Devuelto el" requerido valor={v.devuelto_el} onChange={(x) => set('devuelto_el', x)} error={e.devuelto_el} />
        <Selector etiqueta="Cómo vuelve" opciones={Object.entries(cat.data.estados).map(([valor, etiqueta]) => ({ valor, etiqueta }))} valor={v.condicion_devolucion}
          onChange={(x) => x && set('condicion_devolucion', x)} error={e.condicion_devolucion} requerido
          />
        {deposito !== null && (
          <Interruptor etiqueta="Se devolvió la seña" ayuda={`Seña cobrada: $${deposito.toLocaleString('es-AR')}`} valor={v.deposito_devuelto} onChange={(x) => set('deposito_devuelto', x)} />
        )}
        <Campo etiqueta="Notas" multilinea valor={v.notas} onChange={(x) => set('notas', x)} error={e.notas} maxLength={400} />
      </Seccion>
      {v.condicion_devolucion === 'reparacion' && <Aviso tono="alerta" texto="El instrumento queda en reparación: no figura disponible hasta que lo des de alta de nuevo." />}
      <Boton titulo="Registrar devolución" icono="assignment-return" grande cargando={f.enviando || devolver.isPending}
        onPress={() => void f.enviar((d) => devolver.mutateAsync(d), (d) => requeridos(d, { devuelto_el: 'La fecha de devolución', condicion_devolucion: 'La condición' }))} />
    </Pantalla>
  );
}

/** Cuerpo para la API: solo el responsable elegido; la seña vacía no se manda. */
export function cuerpoPrestamo(d: DatosPrestamo) {
  return {
    alumno_id: d.responsable_tipo === 'alumno' ? d.alumno_id : null,
    persona_id: d.responsable_tipo === 'persona' ? d.persona_id : null,
    responsable_nombre: d.responsable_tipo === 'nombre' ? d.responsable_nombre.trim() : null,
    entregado_el: d.entregado_el,
    devolucion_prevista: d.devolucion_prevista,
    deposito: d.deposito.trim() === '' ? null : Number(d.deposito.replace(',', '.')),
    condicion_entrega: d.condicion_entrega,
    notas: d.notas.trim() || null,
  };
}

function sumarDias(iso: string, dias: number): string {
  const [a, m, d] = iso.split('-').map(Number);
  const f = new Date(a, m - 1, d + dias);
  return `${f.getFullYear()}-${String(f.getMonth() + 1).padStart(2, '0')}-${String(f.getDate()).padStart(2, '0')}`;
}
