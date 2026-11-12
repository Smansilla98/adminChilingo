# Cobro de cuotas desde el sistema

El alumno entra a **Pagar mi cuota → ¿Todavía no pagaste?** (`/mi-cuota/pagar`), marca las
cuotas que debe —de todos sus bloques, sedes y meses— y elige cómo pagar. El sistema crea un
comprobante **"Esperando el pago"** con un código de referencia (`CH-XXXXXX`).

## Medios

| Medio | Qué ve el alumno | Cómo se registra |
|---|---|---|
| Transferencia | Alias, CBU, titular, monto y el código para poner en el concepto. Botones para copiar y abrir la billetera. | Sube el comprobante → queda **Pendiente de revisión** → administración lo aprueba como siempre. |
| Mercado Pago | Lo lleva al checkout de Mercado Pago (cuenta, dinero en la app, tarjeta). | Mercado Pago avisa a `/webhooks/mercadopago`; si el pago está aprobado y cubre el total, se registra solo. |

Si la transferencia entra y el alumno no sube el comprobante, administración lo encuentra en
**Comprobantes → Esperando el pago** (buscando el código en el concepto del banco) y lo aprueba.

## Cómo se reparte

Cada cuota queda en su propio detalle de pago (alumno, cuota, bloque). Lo que le toca al docente
sale de la regla de liquidación de la **sede del bloque** (retención y porcentaje, en la ficha de
la sede), igual que al aprobar cualquier comprobante. Ejemplo: Juan en 4 bloques de 4 sedes paga
todo junto → un pago con 4 detalles, cada uno con el abono de su docente.

Solo se ofrecen cuotas del año actual y el anterior **con saldo** (ver *Pagos parciales*). Una
cuota con un pago en curso no se puede volver a elegir hasta que ese pago se complete o se cancele.

## Pagos parciales

Una cuota puede pagarse en partes, tanto en el cobro online como en caja:

- **Online:** cada cuota marcada tiene un campo *Cuánto pagás de esta cuota* (por defecto, el
  saldo). No puede ser más que el saldo ni menos de $1. Si la cuota ya tiene pagos, aparece
  «Ya pagaste X de Y». Lo que no se paga queda como saldo.
- **Caja** (Pagos → Registrar): cada línea no puede superar el saldo de su alumno en esa cuota. Si
  el alumno ya no debe nada en la cuota, la línea se rechaza con un mensaje claro.
- **Saldo:** el estado de cuenta (`EstadoCuentaService`) muestra por cuota el neto, lo pagado, el
  saldo y el estado `parcial`. Las vencidas y la morosidad usan ese mismo saldo.
- **Abono docente:** se prorratea según lo que se cobra sobre el neto de la cuota. Entre todos los
  pagos parciales, el docente cobra en total el abono de la cuota (no más).

## Reglas de cobro (recargo, descuento familiar, pago adelantado)

Configuración en **Cuotas → Reglas de cobro** (permiso `cuotas.update`). Todo arranca apagado.

- **Recargo por mora:** porcentaje o monto fijo, a partir de X días después del vencimiento.
  No se cobra si el alumno pagó dentro del plazo.
- **Descuento por grupo familiar:** porcentaje para los alumnos que no son el primero del grupo
  (grupo familiar cargado en la ficha de la persona/alumno). El primero paga completo.
- **Descuento por pago adelantado:** porcentaje si se paga hasta X días antes del vencimiento.

Estos valores se aplican en el estado de cuenta: el alumno ve el neto ya con el descuento o recargo.
Se guardan en Ajustes (`reglas_cobro`).

## Registro de pagos en caja (RegistrarPago)

El panel web y la API usan la misma acción (`app/Domain/Finanzas/RegistrarPago.php`). Es
idempotente: si el mismo pago llega dos veces (doble clic en el formulario, reintento de la app),
se devuelve el pago ya registrado y no se duplica.

- API: `POST /api/v1/pagos` acepta `client_uuid` en el cuerpo o el encabezado `Idempotency-Key`.
  Si el reintento devuelve el mismo pago, la respuesta trae `"duplicado": true` (código 200).
- Web: el formulario manda un `client_uuid` oculto; un doble envío muestra
  «Este pago ya estaba registrado».
- Un `client_uuid` usado por otra persona se rechaza (422).

## Morosidad y recordatorios

Pantalla **Morosos** (web: menú Administración; app: Finanzas → Morosos). Lista de alumnos con
cuotas vencidas y saldo, con la antigüedad de la cuota vencida más antigua:

| Tramo | Días de mora |
|---|---|
| 0 a 30 días | 1 a 30 |
| 31 a 60 días | 31 a 60 |
| 61 a 90 días | 61 a 90 |
| Más de 90 días | más de 90 |

Filtros por sede, bloque y tramo. Recordatorio **individual** o **masivo** (a los marcados o a
todos los que tienen cuenta), por los canales elegidos (aviso en la app, notificación, mail,
WhatsApp). Los envíos pasan por `NotificacionService`: a un mismo alumno no se le manda dos veces
por el mismo canal en el mismo mes (clave `morosidad:<alumno>:<AAAAMM>`). Si ya no debe nada, no
se envía.

API: `GET /api/v1/morosos`, `POST /api/v1/morosos/{alumno}/recordatorio`,
`POST /api/v1/morosos/recordatorios`.

## Cuotas por lote

**Cuotas → Por lote** (web) o `POST /api/v1/cuotas/lote`. Arma un rango de meses de un año, para
bloques, sedes o toda la escuela, con monto, nombre y día de vencimiento (si el mes no tiene ese
día, vence el último). Antes de crear, `POST /api/v1/cuotas/lote/vista-previa` muestra qué se
crea, qué ya existe y qué no se puede definir. Lo que ya existe no se duplica. Máximo 500 cuotas
por lote.

## Débito automático (pendiente)

No está implementado. Requiere las credenciales de Mercado Pago de producción y que la cuenta
de la asociación habilite suscripciones (preapproval). Cuando esté, el alta y la baja las hace el
alumno desde la app, y la conciliación usa el mismo aviso de Mercado Pago que hoy.

## Configuración (variables de entorno)

```
COBRO_ALIAS=cifra.jardin.pez
COBRO_CBU=
COBRO_TITULAR=Asociación Civil La Chilinga
COBRO_CUIT=
COBRO_BANCO=
MERCADOPAGO_ACCESS_TOKEN=APP_USR-...
MERCADOPAGO_WEBHOOK_SECRET=...
```

- Sin alias ni CBU no aparece la transferencia; sin token no aparece Mercado Pago.
- El token es el de **producción** de la cuenta de Mercado Pago que cobra: la plata entra a esa
  cuenta. Si el alias es de otra cuenta, las dos opciones cobran en lugares distintos.
- En Mercado Pago → Tus integraciones → Webhooks: URL `https://<APP_URL>/webhooks/mercadopago`,
  evento **Pagos**. Copiá la clave secreta en `MERCADOPAGO_WEBHOOK_SECRET` para que se verifique
  la firma. **En producción es obligatoria**: sin ella los avisos se rechazan (queda un error en el
  log) y los pagos entran solo por la conciliación cada 30 minutos. Igual, el sistema nunca confía
  en el aviso: consulta el pago a la API.
- `APP_URL` tiene que ser el dominio real: con él se arman la vuelta del checkout y el aviso.

## Código

`app/Domain/Finanzas/CobroCuotasService.php` (cuotas pendientes, iniciar, adjuntar, cancelar,
aviso), `MercadoPagoClient.php`, `CobroCuotaController`, `MercadoPagoWebhookController`.
Caja y saldo: `RegistroPagoService.php`, `EstadoCuentaService.php`, `ReglasCobro.php`,
`GrupoFamiliar.php`. Morosidad: `MorosidadService.php`, `MorososController`. Cuotas por lote:
`CuotaLoteService.php`.
Pruebas: `tests/Feature/CobroCuotasTest.php`, `tests/Feature/FinanzasPlataSeguraTest.php`
(pago parcial de punta a punta, reglas de cobro, registro simultáneo, papelera),
`tests/Feature/MorosidadTest.php`, `tests/Feature/CuotasLoteTest.php`.
