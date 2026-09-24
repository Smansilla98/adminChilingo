# App móvil — La Chilinga

App nativa (React Native + Expo SDK 57, Expo Router, TypeScript) que consume `/api/v1` del
backend Laravel. Documentación completa: [`../docs/APP_MOVIL.md`](../docs/APP_MOVIL.md).

```bash
cp .env.example .env.local      # EXPO_PUBLIC_API_URL apuntando al backend
npm install
npm start                       # Expo Go / development build
npm run typecheck && npm run lint
```

Builds (requieren cuenta de Expo/EAS, no se ejecutan en CI): `npm run build:dev | build:staging | build:prod`.
