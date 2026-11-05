import { useMutation } from '@tanstack/react-query';
import * as Clipboard from 'expo-clipboard';
import { useState } from 'react';
import { Linking, Share, View } from 'react-native';

import { Aviso, Boton, Dato, Fila, Tarjeta, Tenue, Texto } from '@/components/ui';
import { api } from '@/lib/api';
import { E } from '@/lib/theme';
import type { CobroDisponible } from '@/lib/types';

/**
 * Cómo pagar: alias y CBU para copiar o compartir (para pegarlos en la app del banco o billetera)
 * y, si está configurado, pagar con Mercado Pago.
 */
export function ComoPagar({ cobro, alumnoId, saldo }: { cobro: CobroDisponible; alumnoId: number; saldo: number }) {
  const [copiado, setCopiado] = useState<string | null>(null);
  const t = cobro.transferencia;

  const copiar = async (etiqueta: string, valor: string) => {
    await Clipboard.setStringAsync(valor);
    setCopiado(`${etiqueta} copiado. Pegalo en la app de tu banco o billetera.`);
  };

  const compartir = () => {
    if (!t) return;
    const lineas = [
      t.alias && `Alias: ${t.alias}`,
      t.cbu && `CBU/CVU: ${t.cbu}`,
      t.titular && `Titular: ${t.titular}`,
      t.banco && `Banco: ${t.banco}`,
      t.cuit && `CUIT: ${t.cuit}`,
    ].filter(Boolean);
    void Share.share({ message: `Datos para pagar la cuota de La Chilinga\n${lineas.join('\n')}` });
  };

  const mp = useMutation({
    mutationFn: () => api<{ link: string }>('mi/cobro/mercadopago', { method: 'POST', body: { alumno_id: alumnoId } }),
    onSuccess: (r) => void Linking.openURL(r.link),
  });

  if (!t && !cobro.mercado_pago) return null;

  return (
    <Tarjeta>
      <Texto style={{ fontWeight: '800' }} accessibilityRole="header">Cómo pagar</Texto>
      {t && (
        <View style={{ gap: E.xs }}>
          <Tenue>Transferí desde tu banco o billetera y después enviá el comprobante.</Tenue>
          {t.alias && <Dato etiqueta="Alias (tocá para copiar)" valor={t.alias} icono="content-copy" onPress={() => void copiar('Alias', t.alias!)} />}
          {t.cbu && <Dato etiqueta="CBU / CVU (tocá para copiar)" valor={t.cbu} icono="content-copy" onPress={() => void copiar('CBU', t.cbu!)} />}
          <Dato etiqueta="Titular" valor={t.titular} />
          <Dato etiqueta="Banco" valor={t.banco} />
          <Dato etiqueta="CUIT" valor={t.cuit} />
          <Fila style={{ gap: E.s, flexWrap: 'wrap' }}>
            {t.alias && <Boton titulo="Copiar alias" icono="content-copy" variante="secundario" onPress={() => void copiar('Alias', t.alias!)} />}
            <Boton titulo="Compartir datos" icono="share" variante="secundario" onPress={compartir} />
          </Fila>
          {copiado && <Aviso texto={copiado} tono="exito" />}
        </View>
      )}
      {cobro.mercado_pago && saldo > 0 && (
        <View style={{ gap: E.xs, marginTop: E.s }}>
          <Boton titulo="Pagar con Mercado Pago" icono="open-in-new" cargando={mp.isPending} onPress={() => mp.mutate()} />
          <Tenue>Se abre Mercado Pago con tus cuotas pendientes. El pago se registra solo.</Tenue>
          {mp.isError && <Aviso texto={mp.error instanceof Error ? mp.error.message : 'No se pudo abrir Mercado Pago.'} tono="peligro" />}
        </View>
      )}
    </Tarjeta>
  );
}
