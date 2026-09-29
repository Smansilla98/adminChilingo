import { useQueryClient } from '@tanstack/react-query';
import { router, Stack } from 'expo-router';
import { useState } from 'react';

import { Campo, ErrorFormulario, Selector } from '@/components/form';
import { Boton, Pantalla, Tenue } from '@/components/ui';
import { api, ApiError } from '@/lib/api';

const ANIOS = [1, 2, 3, 4, 5, 6, 7].map((n) => ({ valor: n, etiqueta: `${n}°` }));

/** Alta de un toque. Queda oculto hasta que se publique, con la grilla vacía para escribir. */
export default function NuevoToque() {
  const qc = useQueryClient();
  const [nombre, setNombre] = useState('');
  const [autor, setAutor] = useState('');
  const [anio, setAnio] = useState<number | null>(1);
  const [error, setError] = useState<string | null>(null);
  const [cargando, setCargando] = useState(false);

  const crear = async () => {
    if (nombre.trim().length < 2 || !anio) {
      setError('El nombre necesita al menos 2 letras y un año.');
      return;
    }
    setCargando(true);
    setError(null);
    try {
      const r = await api<{ slug: string }>('partituras', { method: 'POST', body: { nombre: nombre.trim(), anio, autor: autor.trim() || null } });
      await qc.invalidateQueries({ queryKey: ['partituras'] });
      router.replace({ pathname: '/partituras/[slug]', params: { slug: r.slug } } as never);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'No se pudo crear el toque.');
    } finally {
      setCargando(false);
    }
  };

  return (
    <Pantalla>
      <Stack.Screen options={{ title: 'Nuevo toque' }} />
      <Campo etiqueta="Nombre" valor={nombre} onChange={setNombre} requerido />
      <Campo etiqueta="Autor" valor={autor} onChange={setAutor} />
      <Selector etiqueta="Año" opciones={ANIOS} valor={anio} onChange={setAnio} requerido />
      <Tenue>Se crea oculto. Escribí la grilla, escuchala y publicalo cuando esté listo.</Tenue>
      <ErrorFormulario mensaje={error} />
      <Boton titulo="Crear y escribir" icono="add" cargando={cargando} onPress={() => void crear()} />
    </Pantalla>
  );
}
