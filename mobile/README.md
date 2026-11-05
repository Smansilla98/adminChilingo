# App móvil — ITO

App nativa (React Native + Expo SDK 57, Expo Router, TypeScript) que consume `/api/v1` del
backend Laravel. Documentación completa: [`../docs/APP_MOVIL.md`](../docs/APP_MOVIL.md).

```bash
cp .env.example .env.local      # EXPO_PUBLIC_API_URL apuntando al backend
npm install
npm start                       # Expo Go / development build
npm run typecheck && npm run lint
```

Builds (requieren cuenta de Expo/EAS, no se ejecutan en CI): `npm run build:dev | build:staging | build:prod`.

Nombre visible de la aplicación: `ITO`.
Identificador Android estable: `org.lachilinga.ito`.

Para generar el Android App Bundle (`.aab`) de producción:

```bash
npm run build:aab
```

## Notificaciones push (que lleguen al celular)

El backend manda los avisos (cuota nueva, por vencer, vencida, pago registrado) por Expo Push al
canal Android `avisos` (alta prioridad). Al tocar la notificación la app abre la pantalla del aviso
(por ejemplo **Mi cuenta**). Solo funcionan en un build de EAS (no en Expo Go).

Una sola vez:

1. **Servidor** (Railway): `EXPO_PUSH_ENABLED=true` (es el valor por defecto). `EXPO_ACCESS_TOKEN`
   solo si activás "Enhanced security for push" en expo.dev.
2. **Android (Firebase / FCM)**, obligatorio para que Android reciba push:
   - En [console.firebase.google.com](https://console.firebase.google.com) crear un proyecto y una app
     Android por cada paquete que uses: `org.lachilinga.ito` (y `.staging` / `.dev` si los usás).
   - Descargar `google-services.json` y subirlo a EAS como variable de tipo archivo:
     `npx eas-cli@latest env:create --name GOOGLE_SERVICES_JSON --type file --value ./google-services.json --visibility secret --environment production`
     (en local alcanza con dejarlo en `mobile/google-services.json`, que git ignora).
   - En Firebase → Configuración del proyecto → Cuentas de servicio, generar una clave privada (JSON) y
     cargarla en `npx eas-cli@latest credentials` → Android → *FCM V1 service account key*.
3. **iOS**: `npx eas-cli@latest credentials` → iOS → *Push Notifications key* (EAS la crea con tu cuenta de Apple).
4. Hacer un build nuevo (`npx eas-cli@latest build --profile production`).

Para ver que anda: en el panel, Configuración → Avisos y cobro muestra cuántos alumnos tienen la app
con notificaciones activadas, y "Mandarme una prueba" te manda una al celular.
