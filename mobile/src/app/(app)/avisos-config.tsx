import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Stack } from 'expo-router';
import { useState } from 'react';
import { Switch, TextInput, View } from 'react-native';

import { Aviso, Boton, Cargando, Chip, ErrorVista, Fila, Pantalla, Subtitulo, Tarjeta, Tenue, Texto } from '@/components/ui';
import { api } from '@/lib/api';
import { C, E } from '@/lib/theme';
import type { AvisoConfig, ConfigAvisos, DatosTransferencia } from '@/lib/types';

const CAMPOS_COBRO: { clave: keyof DatosTransferencia; etiqueta: string }[] = [
  { clave: 'alias', etiqueta: 'Alias' },
  { clave: 'cbu', etiqueta: 'CBU / CVU' },
  { clave: 'titular', etiqueta: 'Titular' },
  { clave: 'banco', etiqueta: 'Banco o billetera' },
  { clave: 'cuit', etiqueta: 'CUIT' },
];

/** Admin: qué avisos automáticos reciben los alumnos, por qué canales y con qué texto, y los datos para pagar. */
export default function AvisosConfig() {
  const q = useQuery({ queryKey: ['config', 'avisos'], queryFn: () => api<ConfigAvisos>('config/avisos') });

  if (q.isPending && !q.data) return <Cargando />;
  if (q.isError && !q.data) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;

  // La key reinicia el formulario cuando llegan datos nuevos del servidor (al refrescar o guardar).
  return <Formulario key={q.dataUpdatedAt} d={q.data!} refrescando={q.isRefetching} onRefrescar={() => q.refetch()} />;
}

function Formulario({ d, refrescando, onRefrescar }: { d: ConfigAvisos; refrescando: boolean; onRefrescar: () => void }) {
  const qc = useQueryClient();
  const [avisos, setAvisos] = useState<Record<string, AvisoConfig>>(d.avisos);
  const [cobro, setCobro] = useState<DatosTransferencia>(d.cobro ?? {});

  const guardar = useMutation({
    mutationFn: () => api<ConfigAvisos>('config/avisos', { method: 'PUT', body: { avisos, cobro } }),
    onSuccess: (nuevo) => qc.setQueryData(['config', 'avisos'], nuevo),
  });
  const enviar = useMutation({ mutationFn: () => api<{ enviados: number }>('config/avisos/enviar-cuotas-nuevas', { method: 'POST' }) });

  const cambiar = (tipo: string, parche: Partial<AvisoConfig>) => setAvisos((a) => ({ ...a, [tipo]: { ...a[tipo], ...parche } }));
  const alternarCanal = (tipo: string, canal: string) => {
    const actuales = avisos[tipo]?.canales ?? [];
    cambiar(tipo, { canales: actuales.includes(canal) ? actuales.filter((c) => c !== canal) : [...actuales, canal] });
  };

  return (
    <Pantalla refrescando={refrescando} onRefrescar={onRefrescar}>
      <Stack.Screen options={{ title: 'Avisos y cobro' }} />

      <Subtitulo>Datos para pagar</Subtitulo>
      <Tarjeta>
        <Tenue>Los alumnos los ven en Mi cuenta con botón para copiar el alias. Lo vacío usa lo configurado en el servidor.</Tenue>
        {CAMPOS_COBRO.map(({ clave, etiqueta }) => (
          <View key={clave} style={{ gap: 4 }}>
            <Tenue>{etiqueta}</Tenue>
            <TextInput
              value={cobro[clave] ?? ''}
              onChangeText={(v) => setCobro((c) => ({ ...c, [clave]: v }))}
              placeholder={d.cobro_efectivo?.[clave] ?? ''}
              placeholderTextColor={C.tenue}
              autoCapitalize="none"
              accessibilityLabel={etiqueta}
              style={{ borderWidth: 1, borderColor: C.borde, borderRadius: 10, padding: E.s, color: C.texto }}
            />
          </View>
        ))}
        <Tenue>Mercado Pago: {d.mercado_pago ? 'configurado' : 'sin configurar (variables del servidor)'}.</Tenue>
      </Tarjeta>

      <Subtitulo>Avisos automáticos</Subtitulo>
      <Tenue>Variables: {Object.keys(d.variables).join(' ')}</Tenue>
      {Object.entries(avisos).map(([tipo, a]) => (
        <Tarjeta key={tipo} acento={a.activo ? C.exito : undefined}>
          <Fila style={{ justifyContent: 'space-between' }}>
            <View style={{ flex: 1 }}>
              <Texto style={{ fontWeight: '800' }}>{a.nombre}</Texto>
              <Tenue>{a.para} · {a.descripcion}</Tenue>
            </View>
            <Switch value={a.activo} onValueChange={(v) => cambiar(tipo, { activo: v })} accessibilityLabel={`Activar ${a.nombre}`} />
          </Fila>
          <Fila style={{ flexWrap: 'wrap', gap: E.xs }}>
            {Object.entries(d.canales).map(([canal, etiqueta]) => {
              const on = a.canales.includes(canal);
              return (
                <Boton key={canal} titulo={`${on ? '✓ ' : ''}${etiqueta}`} variante={on ? 'primario' : 'secundario'} onPress={() => alternarCanal(tipo, canal)} />
              );
            })}
          </Fila>
          <Tenue>Título</Tenue>
          <TextInput value={a.titulo} onChangeText={(v) => cambiar(tipo, { titulo: v })} maxLength={120} accessibilityLabel={`Título de ${a.nombre}`}
            style={{ borderWidth: 1, borderColor: C.borde, borderRadius: 10, padding: E.s, color: C.texto }} />
          <Tenue>Mensaje</Tenue>
          <TextInput value={a.mensaje} onChangeText={(v) => cambiar(tipo, { mensaje: v })} maxLength={600} multiline accessibilityLabel={`Mensaje de ${a.nombre}`}
            style={{ borderWidth: 1, borderColor: C.borde, borderRadius: 10, padding: E.s, minHeight: 70, color: C.texto }} />
          {a.dias !== undefined && (
            <Fila style={{ gap: E.s }}>
              <Tenue>{tipo === 'cuota_vencida' ? 'Revisar vencidas de los últimos' : 'Días antes'}</Tenue>
              <TextInput value={String(a.dias)} onChangeText={(v) => cambiar(tipo, { dias: Number(v.replace(/\D/g, '')) || 1 })} keyboardType="number-pad" accessibilityLabel={`Días de ${a.nombre}`}
                style={{ borderWidth: 1, borderColor: C.borde, borderRadius: 10, padding: E.s, width: 70, color: C.texto }} />
              <Chip texto="días" />
            </Fila>
          )}
        </Tarjeta>
      ))}

      {guardar.isSuccess && <Aviso texto="Configuración guardada." tono="exito" />}
      {guardar.isError && <Aviso texto={guardar.error instanceof Error ? guardar.error.message : 'No se pudo guardar.'} tono="peligro" />}
      <Boton titulo="Guardar" icono="check" grande cargando={guardar.isPending} onPress={() => guardar.mutate()} />
      <Boton titulo="Enviar ahora los avisos de cuotas nuevas" icono="bolt" variante="secundario" cargando={enviar.isPending} onPress={() => enviar.mutate()} />
      {enviar.data && <Aviso texto={enviar.data.enviados > 0 ? `Se enviaron ${enviar.data.enviados} avisos.` : 'No había avisos pendientes.'} tono="info" />}
    </Pantalla>
  );
}
