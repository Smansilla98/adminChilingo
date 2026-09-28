import { useQuery } from '@tanstack/react-query';
import * as Crypto from 'expo-crypto';
import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, View } from 'react-native';

import { Campo, CampoArchivo, CampoFecha, CampoMonto, ErrorFormulario, hoyIso, Interruptor, Progreso, Seccion, SelectorLista, useFormulario } from '@/components/form';
import { Aviso, Boton, Cargando, Chip, ErrorVista, Fila, Icon, Pantalla, Tenue, Texto } from '@/components/ui';
import { api, qs } from '@/lib/api';
import { type ArchivoLocal, subirArchivo } from '@/lib/archivos';
import { useCatalogo, useOperacion } from '@/lib/recursos';
import { C, E, moneda } from '@/lib/theme';
import type { EstadoCuenta } from '@/lib/types';

import type { CuotaParaCobrar, Pago } from './tipos';

interface Linea { clave: string; cuota_id: number | null; cuota: string; alumno_id: number | null; alumno: string; monto: string }

const nuevaLinea = (parcial: Partial<Linea> = {}): Linea => ({ clave: Crypto.randomUUID(), cuota_id: null, cuota: '', alumno_id: null, alumno: '', monto: '', ...parcial });

/**
 * Registrar o editar un pago. Las reglas (duplicados, alcance, cuota que aplica,
 * liquidación) las valida el servidor; acá se guía la carga y se muestran los errores.
 */
export function FormPago({ pago, alumnoId, cuotaId }: { pago?: Pago; alumnoId?: number; cuotaId?: number }) {
  const cuotas = useCatalogo<{ data: CuotaParaCobrar[] }>('pagos/cuotas-para-cobrar', 0.05);
  // Mismo UUID durante toda la vida del formulario: reintentar no duplica el pago.
  const [uuid] = useState(() => Crypto.randomUUID());
  const [progreso, setProgreso] = useState<number | null>(null);

  const f = useFormulario({
    fecha_pago: pago?.fecha ?? hoyIso(),
    lineas: pago
      ? pago.detalles.map((d) => nuevaLinea({ cuota_id: d.cuota_id, cuota: d.cuota ?? '', alumno_id: d.alumno_id, alumno: d.alumno ?? '', monto: String(d.monto) }))
      : [nuevaLinea()],
    liquidar_profesor: pago ? pago.detalles.some((d) => d.abono_profesor != null) : true,
    monto_abono_profesor: '',
    notas: pago?.notas ?? '',
    comprobante: null as ArchivoLocal | null,
    quitar_comprobante: false,
  });
  const { valores: v, set, errores: e } = f;

  // Cuota preseleccionada (desde la ficha de la cuota).
  const [aplicadoInicial, setAplicadoInicial] = useState(false);
  if (!aplicadoInicial && !pago && cuotaId && cuotas.data) {
    const c = cuotas.data.data.find((x) => x.id === cuotaId);
    if (c) set('lineas', [nuevaLinea({ cuota_id: c.id, cuota: c.label, monto: String(c.monto) })]);
    setAplicadoInicial(true);
  }

  const cuenta = useQuery({
    queryKey: ['alumnos', 'cuenta-para-pago', alumnoId],
    queryFn: () => api<EstadoCuenta & { alumno_id: number }>(`alumnos/${alumnoId}/estado-cuenta`),
    enabled: !!alumnoId && !pago,
  });
  const alumnoNombre = useQuery({
    queryKey: ['alumnos', 'nombre', alumnoId],
    queryFn: () => api<{ data: { nombre: string } }>(`alumnos/${alumnoId}`).then((r) => r.data.nombre),
    enabled: !!alumnoId && !pago,
  });

  const lineasValidas = v.lineas.filter((l) => l.cuota_id && l.alumno_id && Number(l.monto) > 0);
  const total = Math.round(v.lineas.reduce((s, l) => s + (Number(l.monto) || 0), 0) * 100) / 100;
  const abonoRegla = v.lineas.reduce((s, l) => s + (cuotas.data?.data.find((c) => c.id === l.cuota_id)?.abono_docente_ref ?? 0), 0);

  const guardar = useOperacion(
    async (d: typeof v) => {
      const lineas = d.lineas.map((l) => ({ alumno_id: l.alumno_id, cuota_id: l.cuota_id, monto: Number(l.monto) }));
      const base = {
        fecha_pago: d.fecha_pago,
        monto_total: total,
        liquidar_profesor: d.liquidar_profesor ? '1' : '0',
        monto_abono_profesor: d.liquidar_profesor && d.monto_abono_profesor !== '' ? Number(d.monto_abono_profesor) : null,
        notas: d.notas || null,
      };
      const ruta = pago ? `pagos/${pago.id}` : 'pagos';
      if (d.comprobante) {
        // Multipart: los arrays viajan como lineas[0][alumno_id]=…
        const plano: Record<string, string | number | null> = { ...base, ...(pago ? {} : { client_uuid: uuid }) };
        lineas.forEach((l, i) => Object.entries(l).forEach(([k, x]) => { plano[`lineas[${i}][${k}]`] = x; }));
        setProgreso(0);
        try {
          return await subirArchivo<{ data: Pago }>(ruta, d.comprobante, { campo: 'comprobante', datos: plano, metodo: pago ? 'PUT' : 'POST', onProgreso: setProgreso });
        } finally {
          setProgreso(null);
        }
      }
      return api<{ data: Pago }>(ruta, {
        method: pago ? 'PUT' : 'POST',
        body: { ...base, lineas, ...(pago ? { quitar_comprobante: d.quitar_comprobante } : { client_uuid: uuid }) },
      });
    },
    {
      exito: pago ? 'Pago actualizado correctamente' : 'Pago registrado correctamente',
      invalidar: ['pagos', 'cuotas', 'alumnos', 'personas', 'mi', ['catalogo', 'pagos/cuotas-para-cobrar']],
      alTerminar: (r) => (pago ? router.back() : router.replace({ pathname: '/pagos/[id]', params: { id: String(r.data.id) } } as never)),
    },
  );

  if (cuotas.isPending) return <Cargando />;
  if (cuotas.isError) return <ErrorVista error={cuotas.error} onReintentar={() => cuotas.refetch()} />;
  const opcionesCuota = cuotas.data.data.filter((c) => c.activo || v.lineas.some((l) => l.cuota_id === c.id)).map((c) => ({ valor: c.id, etiqueta: c.label, detalle: [c.bloque ?? c.sede_nombre, c.liquidacion_resumen].filter(Boolean).join(' · ') }));
  const cambiarLinea = (clave: string, parcial: Partial<Linea>) => set('lineas', v.lineas.map((l) => (l.clave === clave ? { ...l, ...parcial } : l)));
  const pendientes = (cuenta.data?.items ?? []).filter((i) => i.saldo > 0 && !v.lineas.some((l) => l.cuota_id === i.cuota_id && l.alumno_id === alumnoId));

  return (
    <Pantalla>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Seccion titulo="Pago">
        <CampoFecha etiqueta="Fecha de pago" requerido valor={v.fecha_pago} onChange={(x) => set('fecha_pago', x)} error={e.fecha_pago} />
      </Seccion>

      {alumnoId && !pago && (
        <Seccion titulo={`Cuotas pendientes${alumnoNombre.data ? ` de ${alumnoNombre.data}` : ''}`} ayuda="Tocá una cuota para sumarla al pago con el saldo pendiente.">
          {cuenta.isPending && <Tenue>Cargando estado de cuenta…</Tenue>}
          {cuenta.data && pendientes.length === 0 && <Tenue>No tiene saldo pendiente este año.</Tenue>}
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: E.s }}>
            {pendientes.map((i) => (
              <Pressable
                key={i.cuota_id}
                accessibilityRole="button"
                accessibilityLabel={`Agregar ${i.nombre} por ${moneda(i.saldo)}`}
                onPress={() => {
                  const vacias = v.lineas.filter((l) => l.cuota_id || l.alumno_id);
                  set('lineas', [...vacias, nuevaLinea({ cuota_id: i.cuota_id, cuota: i.nombre, alumno_id: alumnoId, alumno: alumnoNombre.data ?? `Alumno #${alumnoId}`, monto: String(i.saldo) })]);
                }}
                style={{ borderRadius: 12, borderWidth: 1, borderColor: C.acento, padding: E.m, gap: 2 }}>
                <Texto style={{ fontWeight: '700' }}>{i.nombre}</Texto>
                <Tenue style={{ fontSize: 13 }}>{moneda(i.saldo)}{i.estado === 'vencida' ? ' · vencida' : ''}</Tenue>
              </Pressable>
            ))}
          </View>
        </Seccion>
      )}

      <Seccion titulo="Qué se paga">
        {e.lineas && <Aviso tono="peligro" texto={e.lineas} />}
        {v.lineas.map((l, i) => (
          <View key={l.clave} style={{ gap: E.s, borderTopWidth: i ? 1 : 0, borderTopColor: C.borde, paddingTop: i ? E.m : 0 }}>
            <Fila style={{ justifyContent: 'space-between' }}>
              <Texto style={{ fontWeight: '800' }}>Línea {i + 1}</Texto>
              {v.lineas.length > 1 && (
                <Pressable onPress={() => set('lineas', v.lineas.filter((x) => x.clave !== l.clave))} accessibilityRole="button" accessibilityLabel={`Quitar línea ${i + 1}`} hitSlop={10}>
                  <Icon name="delete-outline" size={24} color={C.peligro} />
                </Pressable>
              )}
            </Fila>
            <SelectorLista<number>
              etiqueta={`Cuota (línea ${i + 1})`}
              requerido
              valor={l.cuota_id}
              valorEtiqueta={l.cuota}
              opciones={opcionesCuota}
              error={e[`lineas.${i}.cuota_id`]}
              onChange={(id, o) => {
                const c = cuotas.data.data.find((x) => x.id === id);
                cambiarLinea(l.clave, { cuota_id: id, cuota: o?.etiqueta ?? '', monto: l.monto || (c ? String(c.monto) : ''), ...(alumnoId ? {} : { alumno_id: null, alumno: '' }) });
              }}
            />
            <SelectorLista<number>
              etiqueta={`Alumno (línea ${i + 1})`}
              requerido
              valor={l.alumno_id}
              valorEtiqueta={l.alumno}
              placeholder={l.cuota_id ? 'Buscar alumno…' : 'Primero elegí la cuota'}
              error={e[`lineas.${i}.alumno_id`]}
              claveBusqueda={`alumnos-cuota-${l.cuota_id}-${pago?.id ?? 0}`}
              buscar={async (t) => {
                if (!l.cuota_id) return [];
                const r = await api<{ data: { id: number; nombre_apellido: string; sede_nombre: string | null; bloque_nombre: string }[] }>(`pagos/cuotas/${l.cuota_id}/alumnos${qs({ q: t, pago_id: pago?.id })}`);
                return r.data.map((a) => ({ valor: a.id, etiqueta: a.nombre_apellido, detalle: [a.bloque_nombre, a.sede_nombre].filter(Boolean).join(' · ') }));
              }}
              onChange={(id, o) => cambiarLinea(l.clave, { alumno_id: id, alumno: o?.etiqueta ?? '' })}
            />
            <CampoMonto etiqueta={`Monto (línea ${i + 1})`} requerido valor={l.monto} onChange={(x) => cambiarLinea(l.clave, { monto: x })} error={e[`lineas.${i}.monto`]} />
          </View>
        ))}
        <Boton titulo="Agregar otra línea" icono="add" variante="secundario" onPress={() => set('lineas', [...v.lineas, nuevaLinea()])} />
        <Fila style={{ justifyContent: 'space-between' }}>
          <Texto style={{ fontWeight: '800' }}>Total</Texto>
          <Texto style={{ fontWeight: '800', fontSize: 22 }}>{moneda(total)}</Texto>
        </Fila>
        {e.monto_total && <Aviso tono="peligro" texto={e.monto_total} />}
      </Seccion>

      <Seccion titulo="Liquidación docente">
        <Interruptor etiqueta="Liquidar al docente" ayuda="Registra cuánto le corresponde al docente por este pago." valor={v.liquidar_profesor} onChange={(x) => set('liquidar_profesor', x)} />
        {v.liquidar_profesor && (
          <>
            <Tenue>Según la regla de cada sede: {moneda(abonoRegla)}</Tenue>
            <CampoMonto etiqueta="Monto manual (opcional)" valor={v.monto_abono_profesor} onChange={(x) => set('monto_abono_profesor', x)} error={e.monto_abono_profesor}
              ayuda="Si lo cargás, se reparte en proporción a cada línea en lugar de usar la regla de la sede." />
          </>
        )}
      </Seccion>

      <Seccion titulo="Comprobante y notas">
        <CampoArchivo etiqueta="Comprobante (PDF o foto)" archivo={v.comprobante} onChange={(a) => set('comprobante', a)} error={e.comprobante} maxMb={10} tipos={['application/pdf', 'image/jpeg', 'image/png']} />
        {pago?.tiene_comprobante && !v.comprobante && <Interruptor etiqueta="Quitar el comprobante actual" valor={v.quitar_comprobante} onChange={(x) => set('quitar_comprobante', x)} />}
        {progreso !== null && <Progreso fraccion={progreso} texto="Subiendo comprobante…" />}
        <Campo etiqueta="Notas" valor={v.notas} onChange={(x) => set('notas', x)} error={e.notas} multilinea maxLength={1000} />
      </Seccion>

      {lineasValidas.length > 0 && <Chip texto={`${lineasValidas.length} línea${lineasValidas.length === 1 ? '' : 's'} · ${moneda(total)}`} color={C.acento} />}
      <Boton
        titulo={pago ? 'Guardar cambios' : 'Registrar pago'}
        icono="save"
        grande
        cargando={f.enviando}
        onPress={() => void f.enviar((d) => guardar.mutateAsync(d), (d) => {
          const errs: Record<string, string> = {};
          if (!d.fecha_pago) errs.fecha_pago = 'La fecha es obligatoria.';
          const vistos = new Set<string>();
          d.lineas.forEach((l, i) => {
            if (!l.cuota_id) errs[`lineas.${i}.cuota_id`] = 'Elegí la cuota.';
            if (!l.alumno_id) errs[`lineas.${i}.alumno_id`] = 'Elegí el alumno.';
            if (!(Number(l.monto) > 0)) errs[`lineas.${i}.monto`] = 'Ingresá un monto mayor a 0.';
            const par = `${l.alumno_id}-${l.cuota_id}`;
            if (l.alumno_id && l.cuota_id && vistos.has(par)) errs.lineas = 'Hay dos líneas del mismo alumno y cuota. Unificalas en una.';
            vistos.add(par);
          });
          return errs;
        })}
      />
    </Pantalla>
  );
}
