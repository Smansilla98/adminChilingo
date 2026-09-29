import { useMemo, useState } from 'react';
import { TextInput } from 'react-native';
import { Stack } from 'expo-router';

import { Cargando, Chip, ErrorVista, Fila, Pantalla, Subtitulo, Tarjeta, Tenue, Texto, Titulo } from '@/components/ui';
import { useCatalogo } from '@/lib/recursos';
import { C, E } from '@/lib/theme';

interface RolGuia {
  clave: string;
  nombre: string;
  descripcion: string;
  ambitos: string[];
  derivado: boolean;
  todo: boolean;
  no_puede: string[];
  grupos: Record<string, string[]>;
}

interface GuiaRoles {
  intro: string;
  casos: { titulo: string; roles: RolGuia[] }[];
}

export default function Roles() {
  const q = useCatalogo<GuiaRoles>('roles', 1);
  const [busca, setBusca] = useState('');

  const casos = useMemo(() => {
    const texto = busca.trim().toLowerCase();
    if (!q.data) return [];
    return q.data.casos
      .map((caso) => ({
        ...caso,
        roles: caso.roles.filter((rol) => {
          if (!texto) return true;
          const plano = [rol.nombre, rol.descripcion, ...rol.ambitos, ...Object.entries(rol.grupos).flatMap(([grupo, acciones]) => [grupo, ...acciones]), ...rol.no_puede]
            .join(' ')
            .toLowerCase();
          return plano.includes(texto);
        }),
      }))
      .filter((caso) => caso.roles.length > 0);
  }, [q.data, busca]);

  return (
    <>
      <Stack.Screen options={{ title: 'Perfiles y roles' }} />
      {q.isPending && !q.data ? <Cargando /> : null}
      {q.isError && !q.data ? <ErrorVista error={q.error} onReintentar={() => q.refetch()} /> : null}
      {q.data && (
        <Pantalla refrescando={q.isRefetching} onRefrescar={() => q.refetch()}>
          <Titulo>Qué gestiona cada perfil</Titulo>
          <Tenue>{q.data.intro}</Tenue>
          <TextInput
            value={busca}
            onChangeText={setBusca}
            placeholder="Contador, cuotas, inventario…"
            placeholderTextColor={C.tenue}
            autoCapitalize="none"
            autoCorrect={false}
            accessibilityLabel="Buscar un perfil"
            style={{ backgroundColor: C.superficie, color: C.texto, borderRadius: 12, borderWidth: 1, borderColor: C.borde, paddingHorizontal: E.m, minHeight: 48 }}
          />
          {casos.length === 0 && <Tenue>Ningún perfil coincide.</Tenue>}
          {casos.map((caso) => (
            <Tarjeta key={caso.titulo}>
              <Subtitulo>{caso.titulo}</Subtitulo>
              {caso.roles.map((rol) => (
                <Tarjeta key={rol.clave} style={{ marginTop: E.s }}>
                  <Fila style={{ justifyContent: 'space-between' }}>
                    <Texto style={{ fontWeight: '700', flex: 1 }}>{rol.nombre}</Texto>
                    <Chip texto={rol.derivado ? 'Aparece solo' : 'Se asigna'} />
                  </Fila>
                  <Tenue>{rol.descripcion}</Tenue>
                  <Tenue>Alcance: {rol.ambitos.join(' · ')}.</Tenue>
                  {rol.todo && <Texto style={{ marginTop: E.s }}>Gestiona todo el sistema.</Texto>}
                  {!rol.todo && Object.keys(rol.grupos).length === 0 && <Tenue>No abre módulos. Sirve como marca del perfil.</Tenue>}
                  {Object.entries(rol.grupos).map(([grupo, acciones]) => (
                    <Texto key={grupo} style={{ marginTop: E.s }}>
                      <Texto style={{ fontWeight: '700' }}>{grupo}. </Texto>
                      {acciones.join(', ')}.
                    </Texto>
                  ))}
                  {rol.no_puede.length > 0 && (
                    <Texto style={{ marginTop: E.s }}>No puede: {rol.no_puede.join(', ')}.</Texto>
                  )}
                </Tarjeta>
              ))}
            </Tarjeta>
          ))}
        </Pantalla>
      )}
    </>
  );
}
