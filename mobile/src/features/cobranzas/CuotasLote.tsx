import { Alert, Pressable, View } from 'react-native';
import { useState } from 'react';

import { Campo, CampoMonto } from '@/components/form';
import { Aviso, Boton, Cargando, Chip, Encabezado, Fila, Pantalla, Segmentos, Tarjeta, Tenue, Texto } from '@/components/ui';
import { ApiError } from '@/lib/api';
import { useBloquesAsistencia } from '@/lib/queries';
import { E, C, moneda } from '@/lib/theme';

import { useGenerarLote, useVistaPreviaLote } from './hooks';
import type { DatosLote, VistaPreviaLote } from './tipos';

const anioActual = new Date().getFullYear();

/** Cuotas por lote: arma un rango de meses para bloques o toda la escuela, con vista previa. */
export function CuotasLote() {
  const bloques = useBloquesAsistencia();
  const previa = useVistaPreviaLote();
  const generar = useGenerarLote();

  const [nombre, setNombre] = useState('Cuota mensual');
  const [anio, setAnio] = useState(String(anioActual));
  const [mesDesde, setMesDesde] = useState('1');
  const [mesHasta, setMesHasta] = useState('12');
  const [monto, setMonto] = useState('');
  const [dia, setDia] = useState('');
  const [alcance, setAlcance] = useState<'bloque' | 'general'>('bloque');
  const [bloqueIds, setBloqueIds] = useState<number[]>([]);
  const [resultado, setResultado] = useState<VistaPreviaLote | null>(null);
  const [aviso, setAviso] = useState<{ texto: string; tono: 'exito' | 'peligro' | 'alerta' } | null>(null);

  if (bloques.isPending) return <Cargando />;

  const datos = (): DatosLote | null => {
    const anioNum = Number(anio);
    const desde = Number(mesDesde);
    const hasta = Number(mesHasta);
    const montoNum = Number(monto.replace(',', '.'));
    if (!nombre.trim()) return fallo('Ponele un nombre a las cuotas.');
    if (!Number.isInteger(anioNum) || anioNum < 2020 || anioNum > 2030) return fallo('El año tiene que estar entre 2020 y 2030.');
    if (!(desde >= 1 && desde <= 12 && hasta >= 1 && hasta <= 12) || hasta < desde) return fallo('Elegí un rango de meses válido (el mes de fin no puede ser anterior al de inicio).');
    if (!(montoNum > 0)) return fallo('Ingresá el monto de la cuota.');
    if (alcance === 'bloque' && bloqueIds.length === 0) return fallo('Elegí al menos un bloque.');
    const diaNum = dia.trim() === '' ? null : Number(dia);
    if (diaNum !== null && !(diaNum >= 1 && diaNum <= 31)) return fallo('El día de vencimiento tiene que estar entre 1 y 31.');

    return {
      nombre: nombre.trim(),
      año: anioNum,
      mes_desde: desde,
      mes_hasta: hasta,
      monto: montoNum,
      alcance,
      bloque_ids: alcance === 'bloque' ? bloqueIds : [],
      dia_vencimiento: diaNum,
      descripcion: null,
    };
  };

  const fallo = (texto: string): null => {
    setAviso({ texto, tono: 'alerta' });
    return null;
  };

  const verPrevia = () => {
    setAviso(null);
    setResultado(null);
    const d = datos();
    if (!d) return;
    previa.mutate(d, {
      onSuccess: (r) => setResultado(r),
      onError: (e) => setAviso({ texto: mensajeDe(e), tono: 'peligro' }),
    });
  };

  const generarCuotas = () => {
    const d = datos();
    if (!d || !resultado) return;
    Alert.alert('Generar cuotas', `¿Crear ${resultado.crear} cuota(s)? Las que ya existen no se duplican.`, [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Generar',
        onPress: () =>
          generar.mutate(d, {
            onSuccess: (r) => {
              setAviso({ texto: `Se generaron ${r.creadas} cuota(s). Omitidas: ${r.omitidas}.`, tono: 'exito' });
              setResultado(null);
            },
            onError: (e) => setAviso({ texto: mensajeDe(e), tono: 'peligro' }),
          }),
      },
    ]);
  };

  const toggleBloque = (id: number) => setBloqueIds((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));

  return (
    <Pantalla>
      <Encabezado titulo="Cuotas por lote" subtitulo="Armá varios meses de una vez. Mirá la vista previa antes de generar." icono="date-range" />

      <Campo etiqueta="Nombre" valor={nombre} onChange={setNombre} requerido />
      <Fila style={{ gap: E.s }}>
        <View style={{ flex: 1 }}>
          <Campo etiqueta="Año" valor={anio} onChange={setAnio} teclado="numeric" requerido />
        </View>
        <View style={{ flex: 1 }}>
          <Campo etiqueta="Desde (mes)" valor={mesDesde} onChange={setMesDesde} teclado="numeric" requerido />
        </View>
        <View style={{ flex: 1 }}>
          <Campo etiqueta="Hasta (mes)" valor={mesHasta} onChange={setMesHasta} teclado="numeric" requerido />
        </View>
      </Fila>
      <CampoMonto etiqueta="Monto ($)" valor={monto} onChange={setMonto} requerido />
      <Campo etiqueta="Día de vencimiento (opcional)" valor={dia} onChange={setDia} teclado="numeric" ayuda="Si el mes no tiene ese día, vence el último." />

      <Segmentos<'bloque' | 'general'>
        opciones={[
          { valor: 'bloque', etiqueta: 'Bloques elegidos' },
          { valor: 'general', etiqueta: 'Toda la escuela' },
        ]}
        valor={alcance}
        onChange={(v) => {
          setAlcance(v);
          setResultado(null);
        }}
      />

      {alcance === 'bloque' && (
        <Tarjeta>
          <Texto style={{ fontWeight: '700' }}>Bloques</Texto>
          <Fila style={{ gap: E.s, flexWrap: 'wrap' }}>
            {(bloques.data ?? []).map((b) => (
              <Pressable key={b.id} accessibilityRole="checkbox" accessibilityState={{ checked: bloqueIds.includes(b.id) }} onPress={() => toggleBloque(b.id)}>
                <Chip texto={b.nombre} color={bloqueIds.includes(b.id) ? C.acento : undefined} />
              </Pressable>
            ))}
          </Fila>
        </Tarjeta>
      )}

      {aviso && <Aviso texto={aviso.texto} tono={aviso.tono} />}
      <Boton titulo="Ver vista previa" icono="visibility" variante="secundario" onPress={verPrevia} cargando={previa.isPending} />

      {resultado && (
        <Tarjeta>
          <Texto style={{ fontWeight: '700' }}>{resultado.total} cuota(s) en el lote</Texto>
          <Tenue>
            Se crean {resultado.crear} · ya existen {resultado.existen} · sin permiso {resultado.sin_permiso}
          </Tenue>
          <View style={{ gap: E.xs, marginTop: E.s }}>
            {resultado.items.map((it, i) => (
              <Fila key={`${it.periodo}-${it.destino}-${i}`} style={{ justifyContent: 'space-between', gap: E.s }}>
                <Texto style={{ flex: 1 }}>
                  {it.periodo} · {it.destino}
                </Texto>
                <Texto>{moneda(it.monto)}</Texto>
                <Chip texto={it.estado === 'crear' ? 'Se crea' : it.estado === 'existe' ? 'Ya existe' : 'Sin permiso'} color={it.estado === 'crear' ? C.acento : undefined} />
              </Fila>
            ))}
          </View>
          {resultado.crear > 0 && (
            <Boton titulo={`Generar ${resultado.crear} cuota(s)`} icono="done-all" onPress={generarCuotas} cargando={generar.isPending} />
          )}
        </Tarjeta>
      )}
    </Pantalla>
  );
}

function mensajeDe(e: unknown): string {
  if (e instanceof ApiError) return e.message;
  return 'No se pudo completar la operación. Probá de nuevo.';
}
