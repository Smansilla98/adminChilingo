import { useQuery } from '@tanstack/react-query';
import { Stack } from 'expo-router';
import { useState } from 'react';

import { Aviso, Boton, Cargando, Pantalla, Tenue } from '@/components/ui';
import { Selector } from '@/components/form';
import { api } from '@/lib/api';
import { elegirDocumento, subirArchivo } from '@/lib/archivos';

/** Importa el mismo CSV o Excel que el panel, por la API. */
export default function ImportarAlumnos() {
  const cat = useQuery({ queryKey: ['alumnos', 'catalogo'], queryFn: () => api<{ sedes: { id: number; nombre: string }[]; bloques: { id: number; nombre: string; sede_id: number }[] }>('alumnos/catalogo') });
  const [sede, setSede] = useState<number | null>(null);
  const [bloque, setBloque] = useState<number | null>(null);
  const [mensaje, setMensaje] = useState<string | null>(null);
  const [errores, setErrores] = useState<string[]>([]);
  const [cargando, setCargando] = useState(false);

  if (cat.isPending) return <Cargando />;

  const enviar = async () => {
    if (!sede) {
      setMensaje('Elegí una sede.');
      return;
    }
    const archivo = await elegirDocumento(['text/csv', 'text/comma-separated-values', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    if (!archivo) return;
    setCargando(true);
    setMensaje(null);
    setErrores([]);
    try {
      const r = await subirArchivo<{ importados: number; omitidos: number; errores: string[] }>('alumnos/importar', archivo, { datos: { sede_id: sede, bloque_id: bloque } });
      setMensaje(`Importados: ${r.importados}. Omitidos: ${r.omitidos}.`);
      setErrores(r.errores ?? []);
    } catch (e) {
      setMensaje(e instanceof Error ? e.message : 'No se pudo importar.');
    } finally {
      setCargando(false);
    }
  };

  const listaBloques = (cat.data?.bloques ?? []).filter((b) => !sede || b.sede_id === sede);

  return (
    <Pantalla>
      <Stack.Screen options={{ title: 'Importar alumnos' }} />
      <Tenue>El archivo necesita columnas de nombre y fecha de nacimiento. DNI, teléfono y tambor son opcionales.</Tenue>
      <Selector etiqueta="Sede" valor={sede} onChange={setSede} opciones={(cat.data?.sedes ?? []).map((s) => ({ valor: s.id, etiqueta: s.nombre }))} />
      <Selector etiqueta="Bloque" valor={bloque} permitirVacio="Sin bloque" onChange={setBloque} opciones={listaBloques.map((b) => ({ valor: b.id, etiqueta: b.nombre }))} />
      <Boton titulo="Elegir archivo e importar" icono="upload-file" cargando={cargando} onPress={() => void enviar()} />
      {mensaje && <Aviso tono={errores.length ? 'alerta' : 'info'} texto={mensaje} />}
      {errores.slice(0, 8).map((e) => <Tenue key={e}>{e}</Tenue>)}
    </Pantalla>
  );
}
