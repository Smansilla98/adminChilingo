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

Solo se ofrecen cuotas del año actual y el anterior con saldo y sin pagos parciales (las
parciales se resuelven con administración). Una cuota con un pago en curso no se puede volver a
elegir hasta que ese pago se complete o se cancele.

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
  la firma. Igual, el sistema nunca confía en el aviso: consulta el pago a la API.
- `APP_URL` tiene que ser el dominio real: con él se arman la vuelta del checkout y el aviso.

## Código

`app/Domain/Finanzas/CobroCuotasService.php` (cuotas pendientes, iniciar, adjuntar, cancelar,
aviso), `MercadoPagoClient.php`, `CobroCuotaController`, `MercadoPagoWebhookController`.
Pruebas: `tests/Feature/CobroCuotasTest.php`.
