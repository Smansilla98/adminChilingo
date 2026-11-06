import { useQueryClient } from '@tanstack/react-query';
import * as Clipboard from 'expo-clipboard';
import { useState } from 'react';
import { Share } from 'react-native';

import { confirmar, useToast } from '@/components/feedback';
import { Boton, Fila, Tarjeta, Tenue, Texto } from '@/components/ui';
import { api } from '@/lib/api';
import { C } from '@/lib/theme';

interface Acceso {
  usuario: string;
  clave: string;
  nueva: boolean;
}

/**
 * Administración le da acceso a la app a un alumno: crea su cuenta (usuario = DNI) o le da una
 * contraseña provisoria nueva. La clave se ve una sola vez; el alumno la cambia al entrar.
 */
export function DarAccesoApp({ personaId, tieneCuenta }: { personaId: number; tieneCuenta: boolean }) {
  const qc = useQueryClient();
  const avisar = useToast();
  const [acceso, setAcceso] = useState<Acceso | null>(null);
  const [cargando, setCargando] = useState(false);

  const dar = async () => {
    const ok = await confirmar({
      titulo: tieneCuenta ? '¿Darle una contraseña provisoria nueva?' : '¿Darle acceso a la app?',
      mensaje: tieneCuenta
        ? 'Se cierran sus sesiones abiertas y entra con la provisoria; al entrar elige la suya.'
        : 'Se crea su cuenta: el usuario es su DNI y la contraseña, 6 números que le pasás vos. Al entrar elige la suya.',
      accion: 'Continuar',
      destructiva: false,
    });
    if (!ok) return;
    setCargando(true);
    try {
      setAcceso(await api<Acceso>(`personas/${personaId}/acceso-app`, { method: 'POST' }));
      void qc.invalidateQueries({ queryKey: ['personas'] });
    } catch (e) {
      avisar(e instanceof Error ? e.message : 'No se pudo dar acceso.', 'error');
    } finally {
      setCargando(false);
    }
  };

  const texto = acceso
    ? `Para entrar a la app de La Chilinga:\nUsuario: ${acceso.usuario}\nContraseña provisoria: ${acceso.clave}\nAl entrar te va a pedir que elijas tu propia contraseña.`
    : '';

  if (!acceso) {
    return <Boton titulo={tieneCuenta ? 'Nueva contraseña provisoria' : 'Dar acceso a la app'} icono="phone-iphone" variante="secundario" cargando={cargando} onPress={() => void dar()} />;
  }

  return (
    <Tarjeta acento={C.exito}>
      <Texto style={{ fontWeight: '800' }}>{acceso.nueva ? 'Cuenta creada' : 'Contraseña provisoria nueva'}</Texto>
      <Tenue>Pasale estos datos (se muestran una sola vez):</Tenue>
      <Texto>Usuario: <Texto style={{ fontWeight: '800' }}>{acceso.usuario}</Texto></Texto>
      <Texto>Contraseña provisoria: <Texto style={{ fontWeight: '800', fontSize: 22 }}>{acceso.clave}</Texto></Texto>
      <Fila style={{ flexWrap: 'wrap' }}>
        <Boton titulo="Compartir" icono="share" onPress={() => void Share.share({ message: texto })} />
        <Boton titulo="Copiar" icono="content-copy" variante="secundario" onPress={() => void Clipboard.setStringAsync(texto).then(() => avisar('Copiado.'))} />
      </Fila>
    </Tarjeta>
  );
}
