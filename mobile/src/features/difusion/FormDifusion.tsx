import { router } from 'expo-router';
import { useState } from 'react';

import { confirmar } from '@/components/feedback';
import { Campo, CampoFecha, CampoHora, ErrorFormulario, Interruptor, requeridos, Seccion, Selector, SelectorMultiple, useFormulario } from '@/components/form';
import { Aviso, Boton, Cargando, ErrorVista, Pantalla, Tarjeta, Tenue, Texto } from '@/components/ui';
import { api } from '@/lib/api';
import { useCatalogo, useOperacion } from '@/lib/recursos';

import { type Alcance, type CatalogoDifusion, type Canal, cuerpoDifusion, type DatosDifusion, type Difusion, type VistaPrevia } from './tipos';

/** Mensaje a un grupo: a quiénes, por dónde, qué decir y cuándo. Antes de mandar, cuántos lo reciben. */
export function FormDifusion() {
  const cat = useCatalogo<CatalogoDifusion>('difusiones/catalogo', 1);
  const f = useFormulario<DatosDifusion>({
    titulo: '', mensaje: '', enlace: '', canales: ['interna', 'push'], alcance: 'bloques',
    sede_ids: [], bloque_ids: [], show_id: null, alumnos: true, docentes: false, solo_con_deuda: false,
    fecha: '', hora: '', confirmar_whatsapp: false,
  });
  const { valores: v, set, errores: e } = f;
  const [previa, setPrevia] = useState<VistaPrevia | null>(null);
  const [calculando, setCalculando] = useState(false);
  const [errorPrevia, setErrorPrevia] = useState<string | null>(null);

  const mandar = useOperacion(
    (d: DatosDifusion) => api<{ data: Difusion }>('difusiones', { method: 'POST', body: cuerpoDifusion(d) }),
    {
      exito: (r) => (r.data.estado === 'enviada' ? `Mensaje enviado a ${r.data.destinatarios} personas` : 'Mensaje programado'),
      invalidar: ['difusiones'],
      alTerminar: (r) => router.replace({ pathname: '/difusion/[id]', params: { id: String(r.data.id) } } as never),
    },
  );

  const calcular = async () => {
    setCalculando(true);
    setErrorPrevia(null);
    try {
      const r = await api<{ data: VistaPrevia }>('difusiones/vista-previa', { method: 'POST', body: cuerpoDifusion(v) });
      setPrevia(r.data);
      return r.data;
    } catch (err) {
      setErrorPrevia(err instanceof Error ? err.message : 'No se pudo calcular.');
      return null;
    } finally {
      setCalculando(false);
    }
  };

  if (cat.isPending) return <Cargando />;
  if (cat.isError) return <ErrorVista error={cat.error} onReintentar={() => cat.refetch()} />;
  const c = cat.data;
  const alcances = Object.entries(c.alcances) as [Alcance, string][];
  const cambiar = <K extends keyof DatosDifusion>(k: K, x: DatosDifusion[K]) => { set(k, x); setPrevia(null); };

  const enviar = async () => {
    const p = previa ?? (await calcular());
    if (!p) return;
    if (p.cantidad === 0) { setErrorPrevia('El grupo elegido no tiene personas con cuenta.'); return; }
    let confirmarWa = v.confirmar_whatsapp;
    if (p.whatsapp.requiere_confirmacion && !confirmarWa) {
      confirmarWa = await confirmar({
        titulo: `¿Mandar ${p.whatsapp.cantidad} WhatsApp?`,
        mensaje: 'Cada mensaje de WhatsApp tiene costo para la escuela.',
        accion: 'Mandar igual',
        destructiva: false,
      });
      if (!confirmarWa) return;
      set('confirmar_whatsapp', true);
    }
    const ok = await confirmar({
      titulo: v.fecha && v.hora ? 'Programar el mensaje' : `¿Mandar a ${p.cantidad} personas?`,
      mensaje: v.titulo,
      accion: v.fecha && v.hora ? 'Programar' : 'Mandar',
      destructiva: false,
    });
    if (!ok) return;
    await f.enviar((d) => mandar.mutateAsync({ ...d, confirmar_whatsapp: confirmarWa }), (d) => ({
      ...requeridos(d, { titulo: 'El título', mensaje: 'El mensaje', canales: 'El canal' }),
      ...(!d.alumnos && !d.docentes ? { alumnos: 'Elegí alumnos, docentes o ambos.' } : {}),
    }));
  };

  return (
    <Pantalla>
      <ErrorFormulario mensaje={f.errorGeneral} />
      <Seccion titulo="A quiénes">
        <Selector etiqueta="Grupo" opciones={alcances.map(([valor, etiqueta]) => ({ valor, etiqueta }))} valor={v.alcance} onChange={(x) => x && cambiar('alcance', x)} error={e.alcance} />
        {v.alcance === 'sedes' && (
          <SelectorMultiple etiqueta="Sedes" opciones={c.sedes.map((s) => ({ valor: s.id, etiqueta: s.nombre }))} valores={v.sede_ids} onChange={(x) => cambiar('sede_ids', x)} error={e.sede_ids} />
        )}
        {v.alcance === 'bloques' && (
          <SelectorMultiple etiqueta="Bloques" opciones={c.bloques.map((b) => ({ valor: b.id, etiqueta: `${b.nombre}${b.sede ? ` · ${b.sede}` : ''}` }))} valores={v.bloque_ids} onChange={(x) => cambiar('bloque_ids', x)} error={e.bloque_ids} />
        )}
        {v.alcance === 'show' && (
          <Selector etiqueta="Show" opciones={c.shows.map((s) => ({ valor: s.id, etiqueta: s.titulo }))} valor={v.show_id} onChange={(x) => cambiar('show_id', x)} error={e.show_id} />
        )}
        <Interruptor etiqueta="Alumnos" valor={v.alumnos} onChange={(x) => cambiar('alumnos', x)} />
        {v.alumnos && <Interruptor etiqueta="Solo alumnos con cuotas vencidas" valor={v.solo_con_deuda} onChange={(x) => cambiar('solo_con_deuda', x)} />}
        <Interruptor etiqueta="Docentes" valor={v.docentes} onChange={(x) => cambiar('docentes', x)} />
        {e.alumnos && <Aviso tono="peligro" texto={e.alumnos} />}
      </Seccion>
      <Seccion titulo="Por dónde" ayuda="WhatsApp tiene costo por mensaje: si son muchos te pedimos confirmar.">
        <SelectorMultiple opciones={(Object.entries(c.canales) as [Canal, string][]).map(([valor, etiqueta]) => ({ valor, etiqueta }))} valores={v.canales} onChange={(x) => cambiar('canales', x)} error={e.canales} />
      </Seccion>
      <Seccion titulo="Qué decir">
        <Campo etiqueta="Título" requerido valor={v.titulo} onChange={(x) => set('titulo', x)} error={e.titulo} maxLength={120} />
        <Campo etiqueta="Mensaje" requerido multilinea valor={v.mensaje} onChange={(x) => set('mensaje', x)} error={e.mensaje} maxLength={1000} />
        <Campo etiqueta="Enlace (opcional)" placeholder="app://mis-shows o https://…" autoCapitalize="none" valor={v.enlace} onChange={(x) => set('enlace', x)} error={e.enlace} />
      </Seccion>
      <Seccion titulo="Cuándo" ayuda="Sin fecha y hora, sale apenas lo mandás.">
        <CampoFecha etiqueta="Fecha" opcional valor={v.fecha} onChange={(x) => set('fecha', x)} error={e.programada_para} />
        <CampoHora etiqueta="Hora" opcional valor={v.hora} onChange={(x) => set('hora', x)} />
      </Seccion>
      <Tarjeta>
        {previa ? (
          <>
            <Texto style={{ fontWeight: '700' }}>Le llega a {previa.cantidad} {previa.cantidad === 1 ? 'persona' : 'personas'}</Texto>
            <Tenue>{previa.con_app} con la app · {previa.con_email} con correo · {previa.con_telefono} con teléfono</Tenue>
            {previa.muestra.length > 0 && <Tenue>Por ejemplo: {previa.muestra.join(', ')}</Tenue>}
            {previa.whatsapp.supera_maximo && <Aviso tono="peligro" texto={`Por WhatsApp se pueden mandar hasta ${previa.whatsapp.maximo} por vez.`} />}
          </>
        ) : (
          <Tenue>{errorPrevia ?? 'Mirá a cuántas personas les llega antes de mandar.'}</Tenue>
        )}
        <Boton titulo="Ver a cuántos les llega" icono="groups" variante="secundario" cargando={calculando} onPress={() => void calcular()} />
      </Tarjeta>
      <Boton titulo={v.fecha && v.hora ? 'Programar mensaje' : 'Mandar mensaje'} icono="send" grande cargando={f.enviando} onPress={() => void enviar()} />
    </Pantalla>
  );
}
