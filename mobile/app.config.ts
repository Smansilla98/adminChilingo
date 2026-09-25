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
    adaptiveIcon: {
      backgroundColor: '#000000',
      foregroundImage: './assets/images/android-icon-foreground.png',
      backgroundImage: './assets/images/android-icon-background.png',
      monochromeImage: './assets/images/android-icon-monochrome.png',
    },
    predictiveBackGestureEnabled: false,
  },
  web: {
    output: 'static',
    favicon: './assets/images/favicon.png',
  },
  plugins: [
    'expo-router',
    'expo-secure-store',
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
      'expo-splash-screen',
      {
        backgroundColor: '#000000',
        image: './assets/images/splash-icon.png',
        imageWidth: 220,
      },
    ],
  ],
  experiments: {
    typedRoutes: false,
    reactCompiler: true,
  },
  owner: 'smansilla',
  extra: {
    variante: VARIANT,
    // Proyecto de EAS (cuenta smansilla). Necesario para builds y notificaciones push. No es secreto.
    eas: { projectId: process.env.EAS_PROJECT_ID ?? '92097bd6-c5a8-43fd-b41e-87e6981b6891' },
  },
};

export default config;
