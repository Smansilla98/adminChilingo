import { Tabs } from 'expo-router';

import { Icon } from '@/components/ui';
import { useAvisos } from '@/lib/queries';
import { C } from '@/lib/theme';

export default function TabsLayout() {
  const avisos = useAvisos();
  const noLeidas = avisos.data?.no_leidas ?? 0;

  return (
    <Tabs
      screenOptions={{
        headerStyle: { backgroundColor: C.fondo },
        headerTintColor: C.texto,
        tabBarStyle: { backgroundColor: C.superficie, borderTopColor: C.borde, height: 64, paddingBottom: 8 },
        tabBarActiveTintColor: C.acento,
        tabBarInactiveTintColor: C.tenue,
        tabBarLabelStyle: { fontSize: 12, fontWeight: '600' },
      }}>
      <Tabs.Screen name="index" options={{ title: 'Inicio', tabBarIcon: ({ color }) => <Icon name="home" size={26} color={color} /> }} />
      <Tabs.Screen name="agenda" options={{ title: 'Agenda', tabBarIcon: ({ color }) => <Icon name="calendar-month" size={26} color={color} /> }} />
      <Tabs.Screen
        name="avisos"
        options={{
          title: 'Avisos',
          tabBarBadge: noLeidas > 0 ? noLeidas : undefined,
          tabBarIcon: ({ color }) => <Icon name="notifications" size={26} color={color} />,
        }}
      />
      <Tabs.Screen name="perfil" options={{ title: 'Yo', tabBarIcon: ({ color }) => <Icon name="person" size={26} color={color} /> }} />
    </Tabs>
  );
}
