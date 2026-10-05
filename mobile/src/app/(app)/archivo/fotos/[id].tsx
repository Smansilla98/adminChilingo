import { useLocalSearchParams } from 'expo-router';

import { FichaFoto } from '@/features/archivo/FichaFoto';

export default function FotoDelArchivo() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return <FichaFoto id={id} modo="equipo" />;
}
