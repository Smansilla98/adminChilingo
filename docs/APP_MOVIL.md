# App móvil

App **nativa** (no WebView) en [`mobile/`](../mobile): React Native + **Expo SDK 57**, Expo Router,
TypeScript, TanStack Query. Consume `/api/v1` ([API.md](API.md)); el backend Laravel es la
única fuente de verdad.

## Decisiones

| Tema | Decisión | Por qué |
|------|----------|---------|
| Framework | Expo (React Native) | JS/TS como el resto del proyecto; builds Android/iOS en la nube (EAS) sin Mac ni Android Studio locales |
| Navegación | Expo Router: pestañas (Inicio, Agenda, Avisos, Yo) + pila | Pocas pantallas, rutas por archivo |
| Datos | TanStack Query con caché persistida (AsyncStorage, 24 h) | Rápida con red lenta: muestra lo último y actualiza detrás |
| Sesión | Token en SecureStore (Keychain / Keystore); renovación automática < 7 días | No se guardan contraseñas |
| Offline | Solo **asistencia** tiene cola de escritura | Es lo que se hace en el aula; el resto es consulta (caché) |
| Menú | Construido con `/me.modulos` | La app no decide permisos: muestra lo que el backend habilita |
| Partituras | El visor web existente (VexFlow + audio) se abre a pantalla completa | Reescribir el renderer no aporta; el navegador del sistema permite zoom y horizontal |
| Módulos sin pantalla nativa | Se abren en el panel web (personas, gastos, reportes, usuarios…) | La app prioriza el uso cotidiano |

## Pantallas

| Pantalla | Para quién | Qué hace |
|----------|-----------|----------|
| Login | todos | usuario/email + contraseña |
| Inicio | todos | tarjetas según funciones: *Mi cursada* (próxima clase, cuota del mes), *Clases de hoy* (tomar asistencia), *Finanzas del mes*, *Inventario* (escanear QR), *Próximos eventos*; selector de contexto; accesos a módulos |
| Asistencia | docentes / coordinación | lista de bloques (hoy primero) → planilla: tocar alterna presente/ausente, mantener apretado para tarde/justificado, **Todos presentes**, guardado parcial y corrección posterior |
| Agenda | todos | clases, eventos y shows de 2 semanas del alcance |
| Avisos | todos | bandeja de notificaciones, marcar leídas |
| Yo | todos | funciones con su origen, contexto, planillas pendientes de envío, cerrar sesión |
| Mis cuotas | alumnos | saldo, becas, cuotas con estado; *Enviar comprobante* (formulario público existente) |
| Finanzas | contador / tesorería | pagos (con anulados) y cuotas del alcance |
| Alumnos | docentes / coordinación | búsqueda, ficha, llamar, estado de cuenta si tiene permiso |
| Inventario | encargados | buscar, **escanear QR**/código, ficha con historial, registrar movimiento/estado, alta |
| Partituras | todos | programa por año → visor, PDF, partes por instrumento, videos |

UX: fondo negro y acento naranja del panel, objetivos táctiles ≥ 48 px, botones grandes en las
acciones principales, textos cortos, lectores de pantalla (roles y etiquetas accesibles).

## Offline

- **Consulta**: todas las pantallas muestran la última información guardada si no hay red.
- **Asistencia**: al guardar se encola (AsyncStorage) con `client_uuid` y `capturado_en`; se
  envía al instante o cuando vuelve la conexión (NetInfo). El servidor es idempotente por UUID
  y no pisa correcciones posteriores de otra persona (las informa como conflicto).
- Con señal, el inicio **precarga las planillas de las clases de hoy**: se puede tomar
  asistencia aunque en el aula no haya internet.
- Una planilla nueva del mismo bloque y fecha reemplaza a la pendiente anterior.
- Errores definitivos (permiso, validación) no se reintentan: se muestran en *Yo* para descartar.

## Notificaciones push

`expo-notifications` registra el token en `POST /dispositivos` tras el login. Requiere:

1. `projectId` de EAS (`npx eas-cli init` → `EAS_PROJECT_ID`).
2. Development build o build de tienda (Expo Go no recibe push remotos en Android).
3. En el backend: `EXPO_PUSH_ENABLED=true` (y opcional `EXPO_ACCESS_TOKEN`).

Sin eso, los avisos igual llegan a la bandeja interna.

## Configuración y variantes

| Variante | `APP_VARIANT` | Nombre | Paquete / bundle id |
|----------|---------------|--------|---------------------|
| development | `development` | Chilinga (dev) | `org.lachilinga.ito.dev` |
| staging | `staging` | Chilinga (staging) | `org.lachilinga.ito.staging` |
| production | `production` | Chilinga | `org.lachilinga.ito` |

- `app.config.ts`: nombre, ícono, splash, permisos (cámara; sin micrófono), plugins.
- `eas.json`: perfiles `development`, `staging` (APK interno), `production` con su `EXPO_PUBLIC_API_URL`.
  **Reemplazar** `REEMPLAZAR-POR-DOMINIO-DE-RAILWAY` por el dominio real antes del primer build.
- El identificador `org.lachilinga.ito` puede cambiarse **antes** de la primera publicación
  (después las tiendas no lo permiten).
- No hay credenciales en el repo: firmas y claves las guarda EAS.

## Desarrollo

```bash
cd mobile
cp .env.example .env.local        # EXPO_PUBLIC_API_URL
npm install
npm start                          # Expo Go (sin push) o development build
npm run typecheck && npm run lint
npm run doctor                     # expo-doctor
npm run export                     # bundle Android + iOS (valida que compile)
```

Backend local accesible desde el emulador Android: `php artisan serve --host=0.0.0.0` y
`EXPO_PUBLIC_API_URL=http://10.0.2.2:8000/api/v1`.

## Builds (requieren cuenta de Expo)

```bash
npx eas-cli login
npx eas-cli init                   # crea el proyecto y el projectId
npm run build:dev                  # development build
npm run build:staging              # APK para probar
npm run build:prod                 # AAB (Play Store) / IPA (App Store)
npx eas-cli submit --profile production
```

La cuenta de Apple Developer (USD 99/año) y la de Google Play (USD 25 una vez) son necesarias
para publicar; ninguna de estas acciones se ejecuta automáticamente.

## Verificado en este repositorio

- `tsc --noEmit` y `expo lint` sin errores.
- `expo-doctor`: 21/21 checks.
- `expo export` genera los bundles Hermes de Android e iOS.
- `expo prebuild --platform android` genera el proyecto nativo con el paquete y permisos correctos.
- No se compiló el binario nativo (no hay Android SDK / Xcode en el entorno): se hace con EAS.
