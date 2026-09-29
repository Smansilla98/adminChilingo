import { useQuery } from '@tanstack/react-query';
import { Stack } from 'expo-router';
import { useState } from 'react';

import { Aviso, Boton, Cargando, ErrorVista, Pantalla, Subtitulo, Tenue, Texto } from '@/components/ui';
import { api } from '@/lib/api';

interface Chat { saludo: string; todo_ok: boolean; mensajes: { texto: string; tipo?: string }[] }

/** Resumen y envíos de recordatorio. No abre el panel. */
export default function Recordatorios() {
  const q = useQuery({ queryKey: ['recordatorios', 'chat'], queryFn: () => api<Chat>('recordatorios/chat') });
  const [aviso, setAviso] = useState<string | null>(null);
  const [cargando, setCargando] = useState<string | null>(null);

  const enviar = async (canal: 'whatsapp' | 'mail', preview: boolean) => {
    setCargando(canal + (preview ? '-p' : ''));
    setAviso(null);
    try {
      const r = await api<{ ok?: boolean; mensaje?: string }>(`recordatorios/${canal}`, { method: 'POST', body: { preview } });
      setAviso(r.mensaje ?? (preview ? 'Vista previa lista.' : 'Enviado.'));
    } catch (e) {
      setAviso(e instanceof Error ? e.message : 'No se pudo enviar.');
    } finally {
      setCargando(null);
    }
  };

  if (q.isPending && !q.data) return <Cargando />;
  if (q.isError && !q.data) return <ErrorVista error={q.error} onReintentar={() => q.refetch()} />;
  const chat = q.data!;

  return (
    <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
      <Stack.Screen options={{ title: 'Recordatorios' }} />
      <Texto style={{ fontWeight: '800' }}>{chat.saludo}</Texto>
      {chat.mensajes.map((m, i) => <Tenue key={i}>{m.texto}</Tenue>)}
      <Subtitulo>Enviar resumen</Subtitulo>
      <Boton titulo="Vista previa WhatsApp" icono="chat" variante="secundario" cargando={cargando === 'whatsapp-p'} onPress={() => void enviar('whatsapp', true)} />
      <Boton titulo="Enviar WhatsApp" icono="send" cargando={cargando === 'whatsapp'} onPress={() => void enviar('whatsapp', false)} />
      <Boton titulo="Vista previa mail" icono="email" variante="secundario" cargando={cargando === 'mail-p'} onPress={() => void enviar('mail', true)} />
      <Boton titulo="Enviar mail" icono="send" cargando={cargando === 'mail'} onPress={() => void enviar('mail', false)} />
      {aviso && <Aviso tono="info" texto={aviso} />}
    </Pantalla>
  );
}
