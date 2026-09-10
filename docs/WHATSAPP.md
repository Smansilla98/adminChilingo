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
TWILIO_STATUS_CALLBACK_URL=https://tu-dominio.up.railway.app/webhooks/twilio/whatsapp-status
```

(Sin el prefijo `whatsapp:` en `TWILIO_WHATSAPP_FROM`; el código lo agrega solo.)

`TWILIO_STATUS_CALLBACK_URL` tiene que ser una URL **pública HTTPS**. Twilio no puede llamar a `localhost`; ver sección 8.

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

## 5.1 Resumen semanal para administradores

Cada **lunes a las 9:00** (hora Argentina) el sistema puede enviar por WhatsApp un resumen de pendientes del panel: asistencias sin cargar y cuotas del mes sin registrar. Usa la misma lógica que el chatbot de recordatorios.

**Destinatarios** (al menos uno):

1. Usuarios **admin** con teléfono cargado en **Accesos → WhatsApp (resumen semanal)**, o
2. Números en `.env`: `WHATSAPP_ADMIN_NUMBERS=+5491112345678,+5491198765432`

**Probar sin enviar:**

```bash
php artisan whatsapp:resumen-admin --dry-run
```

**Enviar manualmente:**

```bash
php artisan whatsapp:resumen-admin
```

**Desde el panel (solo admin):**

1. Iniciá sesión como administrador.
2. Abrí el botón de **Recordatorios** (abajo a la derecha).
3. Usá **Ver mensaje de WhatsApp** para la vista previa, o **Enviar resumen por WhatsApp** para mandarlo al instante.

Los botones solo aparecen si Twilio está configurado y hay al menos un número destino.

**Cron del servidor** (Laravel Scheduler — obligatorio en producción):

```cron
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

El comando `whatsapp:resumen-admin` ya está programado en la app para los lunes a las 9:00.

## 6. Producción con Twilio

Para producción necesitás un **número de WhatsApp Business** aprobado por Twilio/Meta (no el sandbox). Ese número se configura en `TWILIO_WHATSAPP_FROM`. Los mensajes iniciados por el negocio suelen requerir **plantillas aprobadas** por Meta; para mensajes de texto libres (como estos recordatorios) Twilio tiene restricciones según el tipo de cuenta. Revisá la documentación de [Twilio WhatsApp](https://www.twilio.com/docs/whatsapp) para tu caso.

## 7. Uso desde código

Para enviar un mensaje desde cualquier parte de la app:

```php
use App\Services\WhatsAppService;

$whatsapp = app(WhatsAppService::class);
$result = $whatsapp->send('Texto del mensaje', '+5491112345678');

if ($result['success']) {
    // Twilio aceptó (hay SID). No implica entregado.
    // $result['sid'], $result['status'], $result['delivered_to_recipient']
} else {
    // $result['error'] tiene el mensaje de error
}
```

`success = true` **no** significa que WhatsApp entregó el mensaje. Solo que Twilio aceptó la solicitud y devolvió un Message SID. El estado real (`queued` → `sent` → `delivered` / `read`, o `failed` / `undelivered`) llega por **status callback**.

## 8. Status callback (estado real)

Twilio notifica los cambios de estado con un POST firmado.

### Variables

```env
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=tu_auth_token
TWILIO_WHATSAPP_FROM=+14155238886
TWILIO_STATUS_CALLBACK_URL=https://tu-dominio.up.railway.app/webhooks/twilio/whatsapp-status
```

- `TWILIO_WHATSAPP_FROM`: número sandbox o WhatsApp Business, **sin** el prefijo `whatsapp:`.
- `TWILIO_STATUS_CALLBACK_URL`: URL **pública HTTPS** del webhook. Si está vacía, la app usa `APP_URL` + `/webhooks/twilio/whatsapp-status`, salvo que `APP_URL` sea localhost (Twilio no puede llamar a tu máquina).

En la consola de Twilio, el mismo URL puede configurarse a nivel de messaging service; el envío ya manda `statusCallback` en cada mensaje.

### Localhost

Twilio no alcanza `http://localhost`. Para probar callbacks en desarrollo:

1. Exponé la app con un túnel (`ngrok http 8000` o similar).
2. Poné esa URL HTTPS en `TWILIO_STATUS_CALLBACK_URL` y en `APP_URL` si hace falta que la firma coincida.

### Staging / producción

`APP_URL` y `TWILIO_STATUS_CALLBACK_URL` tienen que ser el dominio público real (HTTPS). Railway: `https://admin-chilingo.up.railway.app/webhooks/twilio/whatsapp-status`.

### Estados

| Estado Twilio | En pantalla (cuota) | Significado |
|---|---|---|
| `queued` / `sending` | Pendiente de confirmación | Twilio aceptó; todavía no hay entrega |
| `sent` | Enviado | Salió hacia WhatsApp |
| `delivered` | Entregado | Llegó al dispositivo |
| `read` | Leído | WhatsApp informó lectura (si el receptor lo permite) |
| `failed` | Fallido | Error (se guarda `ErrorCode` / `ErrorMessage`) |
| `undelivered` | No entregado | No llegó |

Progresión: no se retrocede (`delivered` no vuelve a `sent`). Los callbacks pueden repetirse o llegar desordenados; se ignora un estado más viejo. `failed` y `undelivered` son terminales (no pasan a `sent`/`delivered`). Un mensaje ya `delivered`/`read` no pasa a `failed`.

`--dry-run` / vista previa **no** llama a Twilio y **no** crea filas en `whatsapp_mensajes`.

En **Cuotas → ver cuota** aparece el último recordatorio WhatsApp por alumno, con SID y error si falló.

## 9. Sandbox y producción Meta

El **sandbox** de Twilio solo entrega a números que hicieron `join …`. Un SID aceptado puede terminar en `failed` si el destinatario no se unió.

Producción: número WhatsApp Business aprobado por Twilio/Meta. Los mensajes iniciados por el negocio suelen requerir **plantillas** aprobadas; el texto libre de estos recordatorios puede estar restringido según la cuenta.

## 10. Firma del webhook

El endpoint `POST /webhooks/twilio/whatsapp-status` valida `X-Twilio-Signature` con `Twilio\Security\RequestValidator` y el `TWILIO_AUTH_TOKEN`. No lleva CSRF (excepción solo de esa ruta). No loguea el Auth Token.
