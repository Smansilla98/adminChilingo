import type { ExpoConfig } from 'expo/config';

/**
 * Variantes de la app (una instalación por variante en el mismo teléfono):
 *   APP_VARIANT=development | staging | production  (production por defecto)
 * La URL de la API se toma de EXPO_PUBLIC_API_URL (ver .env.example y eas.json).
 * No hay credenciales en este archivo: las firmas y claves las administra EAS.
 */
const VARIANT = (process.env.APP_VARIANT ?? 'production') as 'development' | 'staging' | 'production';

const BASE_ID = 'org.lachilinga.ito';
const sufijo = VARIANT === 'production' ? '' : `.${VARIANT === 'development' ? 'dev' : 'staging'}`;
const nombre = VARIANT === 'production' ? 'ITO' : `ITO (${VARIANT === 'development' ? 'dev' : 'staging'})`;

/**
 * Firebase (FCM) es obligatorio para que las notificaciones push lleguen a Android.
 * En EAS se sube como variable de tipo archivo GOOGLE_SERVICES_JSON; en local, mobile/google-services.json
 * (ignorado por git). Sin él la app funciona igual, pero Android no recibe push.
 */
// eslint-disable-next-line @typescript-eslint/no-require-imports -- app.config corre en Node; el proyecto no tipa los módulos de Node
const { existsSync } = require('fs') as { existsSync: (ruta: string) => boolean };
const googleServices = process.env.GOOGLE_SERVICES_JSON ?? (existsSync('./google-services.json') ? './google-services.json' : undefined);

/**
 * Staging no tiene URL por defecto: se define en EAS como variable de entorno del proyecto
 * (EXPO_PUBLIC_API_URL, ver .env.example). Si falta, el build corta con un mensaje claro
 * en vez de compilar una app que apunta a una dirección inventada.
 */
if (VARIANT === 'staging') {
  const url = process.env.EXPO_PUBLIC_API_URL ?? '';
  if (!/^https:\/\/[^/\s]+\.[^/\s]+\/api\/v1\/?$/.test(url)) {
    throw new Error('Falta EXPO_PUBLIC_API_URL para el build de staging (https://<host>/api/v1). Definila con `eas env:create` (ver mobile/.env.example).');
  }
}

/** Proyecto EAS para las actualizaciones OTA (expo-updates). No se publica nada desde esta configuración. */
const PROYECTO_EAS = process.env.EAS_PROJECT_ID ?? '92097bd6-c5a8-43fd-b41e-87e6981b6891';

const config: ExpoConfig = {
  name: nombre,
  // Slug técnico del proyecto EAS existente; no es el nombre visible de la app.
  slug: 'chilinga',
  version: '1.0.0',
  orientation: 'default', // el visor de partituras necesita horizontal; las pantallas se diseñan en vertical
  icon: './assets/images/icon.png',
  scheme: 'ito',
  userInterfaceStyle: 'dark',
  backgroundColor: '#000000',
  ios: {
    bundleIdentifier: BASE_ID + sufijo,
    supportsTablet: true,
    infoPlist: {
      ITSAppUsesNonExemptEncryption: false,
    },
  },
  android: {
    package: BASE_ID + sufijo,
    ...(googleServices ? { googleServicesFile: googleServices } : {}),
    adaptiveIcon: {
      backgroundColor: '#000000',
      foregroundImage: './assets/images/android-icon-foreground.png',
      backgroundImage: './assets/images/android-icon-background.png',
      monochromeImage: './assets/images/android-icon-monochrome.png',
    },
    predictiveBackGestureEnabled: false,
    softwareKeyboardLayoutMode: 'resize',
  },
  web: {
    output: 'static',
    favicon: './assets/images/favicon.png',
  },
  plugins: [
    'expo-router',
    'expo-secure-store',
    [
      'expo-audio',
      {
        microphonePermission: false,
        recordAudioAndroid: false,
        enableBackgroundPlayback: false,
      },
    ],
    [
      'expo-camera',
      {
        cameraPermission: 'La cámara se usa para escanear el código QR de los instrumentos.',
        recordAudioAndroid: false,
      },
    ],
    [
      'expo-notifications',
      {
        color: '#f26422',
      },
    ],
    [
      'expo-image-picker',
      {
        photosPermission: 'Las fotos se usan para adjuntar comprobantes, imágenes de diseños y archivos de la biblioteca.',
        cameraPermission: 'La cámara se usa para fotografiar comprobantes y escanear el código QR de los instrumentos.',
        microphonePermission: false,
      },
    ],
    'expo-sharing',
    '@react-native-community/datetimepicker',
    [
      'expo-splash-screen',
      {
        backgroundColor: '#000000',
        image: './assets/images/splash-icon.png',
        imageWidth: 220,
      },
    ],
  ],
  // OTA (eas update): la versión de runtime es la versión de la app, así una actualización
  // solo llega a builds con el mismo código nativo. Cambiar dependencias nativas => nueva versión.
  runtimeVersion: { policy: 'appVersion' },
  updates: {
    url: `https://u.expo.dev/${PROYECTO_EAS}`,
    enabled: VARIANT !== 'development',
    checkAutomatically: 'ON_LOAD',
    fallbackToCacheTimeout: 0,
  },
  experiments: {
    typedRoutes: false,
    reactCompiler: true,
  },
  owner: 'smansilla',
  extra: {
    variante: VARIANT,
    // Proyecto de EAS (cuenta smansilla). Necesario para builds y notificaciones push. No es secreto.
    eas: { projectId: PROYECTO_EAS },
  },
};

export default config;
