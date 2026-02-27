# WhatsApp (Twilio) — Recordatorios y pruebas

El sistema puede enviar mensajes por WhatsApp usando la API de **Twilio**. Sirve para probar envíos y, en producción, para recordatorios de cuotas y avisos de eventos.

## 1. Configuración en Twilio

1. Creá una cuenta en [twilio.com](https://www.twilio.com).
2. En la consola: **Messaging** → **Try it out** → **Send a WhatsApp message** (o **WhatsApp** en el menú).
3. En **Sandbox** (pruebas), Twilio te da un número y un código para unirte (ej. "join xxx-xxx"). El número que te dan es el que vas a usar como `TWILIO_WHATSAPP_FROM`.
4. Para poder recibir mensajes de prueba, **enviales un WhatsApp** desde tu celular al número del sandbox con el texto que Twilio te indica (ej. `join yellow-tiger`).
5. En la consola obtené:
   - **Account SID** → `TWILIO_ACCOUNT_SID`
   - **Auth Token** → `TWILIO_AUTH_TOKEN`
   - Número del sandbox (ej. `+14155238886`) → `TWILIO_WHATSAPP_FROM`

## 2. Variables en `.env`

```env
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=tu_auth_token
TWILIO_WHATSAPP_FROM=+14155238886
```

(Sin el prefijo `whatsapp:`; el código lo agrega solo.)

## 3. Probar envío (mensaje de prueba)

Enviar un mensaje de prueba a un número (el número debe haber “joined” el sandbox si usás sandbox):

```bash
php artisan whatsapp:test +5491112345678
```

Con mensaje personalizado:

```bash
php artisan whatsapp:test +5491112345678 --message="Hola, recordá la muestra del sábado."
```

El número puede ser con código de país (`+54` para Argentina) o solo el número; el servicio normaliza a formato internacional.

## 4. Recordatorios (cuotas y eventos)

**Solo cuotas** (alumnos sin pago registrado en la cuota activa del mes):

```bash
php artisan whatsapp:recordatorios --cuotas
```

**Solo eventos** (avisos de eventos en los próximos 7 días):

```bash
php artisan whatsapp:recordatorios --eventos
```

**Cuotas y eventos:**

```bash
php artisan whatsapp:recordatorios --cuotas --eventos
```

**Simular sin enviar** (ver a quién se enviaría):

```bash
php artisan whatsapp:recordatorios --cuotas --eventos --dry-run
```

**Eventos en los próximos 14 días:**

```bash
php artisan whatsapp:recordatorios --eventos --dias=14
```

- **Cuotas:** se considera “cuota activa” la del mes/año actual; se envía a alumnos activos con teléfono que no tengan un pago registrado para esa cuota.
- **Eventos:** se listan eventos entre hoy y hoy + N días y se envía un resumen a todos los alumnos activos con teléfono.

## 5. Programar recordatorios (cron)

Para que los recordatorios se envíen solos, agregá en el crontab del servidor (o en el panel de Railway/cron del hosting):

```cron
0 10 * * * cd /ruta/al/proyecto && php artisan whatsapp:recordatorios --cuotas --eventos >> /dev/null 2>&1
```

(Ejemplo: todos los días a las 10:00.)

## 6. Producción con Twilio

Para producción necesitás un **número de WhatsApp Business** aprobado por Twilio/Meta (no el sandbox). Ese número se configura en `TWILIO_WHATSAPP_FROM`. Los mensajes iniciados por el negocio suelen requerir **plantillas aprobadas** por Meta; para mensajes de texto libres (como estos recordatorios) Twilio tiene restricciones según el tipo de cuenta. Revisá la documentación de [Twilio WhatsApp](https://www.twilio.com/docs/whatsapp) para tu caso.

## 7. Uso desde código

Para enviar un mensaje desde cualquier parte de la app:

```php
use App\Services\WhatsAppService;

$whatsapp = app(WhatsAppService::class);
$result = $whatsapp->send('Texto del mensaje', '+5491112345678');

if ($result['success']) {
    // enviado; $result['sid'] tiene el ID del mensaje
} else {
    // $result['error'] tiene el mensaje de error
}
```
