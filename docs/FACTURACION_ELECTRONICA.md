# Factura electrónica (AFIP / ARCA): evaluación

Estado: **no implementado**. Este documento evalúa qué haría falta para emitir factura
electrónica desde el sistema. La decisión (y el trámite fiscal) la toma la Asociación Civil con
su contador; no se emite nada desde el código hasta tener esa definición.

## 1. Qué exige la figura de Asociación Civil

- Una asociación civil sin fines de lucro puede estar **inscripta en IVA** (responsable inscripto)
  o **exenta**, según su situación en ARCA. Las cuotas de una escuela de música pueden o no estar
  gravadas según la actividad declarada: esto lo define el contador, no el sistema.
- Si factura, tiene que emitir comprobantes electrónicos (Factura A/B/C según condición del
  receptor) por cada cobro que corresponda. Hoy el sistema **no emite comprobantes fiscales**:
  registra cobros con recibo propio (PDF de comprobante de pago).
- Requisitos previos en ARCA (los da la asociación, no el software):
  - Clave fiscal con nivel de seguridad adecuado y **punto de venta** para "Web Services" (factura
    electrónica, no "Comprobantes en línea" salvo que se use esa modalidad).
  - **Certificado digital** para el servicio `wsfe` (CSR generado en el servidor, certificado
    firmado por ARCA, asociado a un alias y a la asociación). Vence: hay que renovarlo.
  - Autorización del servicio de facturación para el representante/computador fiscal.
- Datos del receptor: si el alumno o el tutor no tiene CUIT/DNI, se factura como consumidor final
  (Factura B/C) por el DNI o sin identificar, según monto y norma vigente.
- Cada factura autorizada devuelve un **CAE** y su vencimiento; sin CAE el comprobante no vale.
  Se guarda el XML/respuesta para auditoría.

## 2. Opciones técnicas

| Opción | Qué es | Pros | Contras |
|---|---|---|---|
| A. Integración directa con WSFEv1 (SOAP) | Llamar a `FECAESolicitar` / `FECompUltimoAutorizado` de ARCA desde PHP | Sin costo de terceros; control total | Hay que manejar firma CMS/WSAA (ticket de acceso), certificados, numeración, reintentos, errores de ARCA; mantenimiento ante cambios de ARCA |
| B. Librería open source PHP sobre WSFE | Paquetes de la comunidad que encapsulan WSAA/WSFE | Menos código propio | Calidad variable, mantenimiento incierto, hay que auditarla antes de usar con certificados reales |
| C. Servicio intermediario (SDK/API de terceros que factura por nosotros) | Un proveedor con certificado propio o cargado en su plataforma; el sistema llama a su API | Trámite y actualizaciones los absorbe el proveedor | Costo mensual o por comprobante; los datos fiscales y del alumno salen del servidor (hay que revisar privacidad y contrato); dependencia |
| D. Facturar a mano en el portal de ARCA | Se carga cada comprobante en "Comprobantes en línea" y se registra el número en el sistema | Cero desarrollo | Trabajo manual; no escala con muchos cobros |

Recomendación: **C** si el volumen es bajo y se quiere salir rápido, o **A** si la asociación prefiere
no depender de un tercero y hay un desarrollador que mantenga la integración. **D** como plan
transitorio hasta decidir.

## 3. Cambios de dominio que haría falta (cualquier opción)

- Modelo `ComprobanteFiscal` (tipo A/B/C, punto de venta, número, CAE, vencimiento, importe,
  receptor con tipo y número de documento, pago asociado, estado, respuesta cruda de ARCA).
- Relación con `Pago` (un pago puede generar uno o varios comprobantes según la regla que se defina).
- Regla de receptor: consumidor final cuando no hay CUIT.
- Anulación: nota de crédito (no se borra un comprobante autorizado).
- Auditoría de cada emisión (ya existe `Auditoria`).
- Configuración: CUIT, punto de venta, condición ante IVA, certificado (fuera del repositorio,
  en variables de entorno o almacenamiento privado; nunca en git).
- Reintentos idempotentes: ARCA puede responder con timeout sin haber autorizado; antes de
  reintentar hay que consultar `FECompUltimoAutorizado` para no duplicar numeración.

## 4. Esfuerzo estimado

| Trabajo | Estimación |
|---|---|
| Trámite en ARCA (clave fiscal, punto de venta, certificado) | 1 a 2 semanas, a cargo de la asociación y su contador |
| Opción C (proveedor) | 1 a 2 semanas de desarrollo (modelo, emisión, notas de crédito, UI de consulta) |
| Opción A (WSFEv1 directo) | 4 a 6 semanas de desarrollo y pruebas en homologación, más mantenimiento continuo |
| Opción D (manual) | Unas horas: solo registrar número y CAE en el pago |

Las pruebas van en **homologación** de ARCA antes de producción; no hay que emitir comprobantes reales
para probar.

## 5. Pasos sugeridos

1. Contador confirma condición ante IVA y si las cuotas están gravadas.
2. Definir opción (A, C o D) según volumen y mantenimiento disponible.
3. Si es C o A: sacar certificado en homologación primero.
4. Implementar modelo y emisión detrás de un permiso nuevo (`facturacion.emitir`), apagado por defecto.
5. Prueba en homologación con un juego de pagos reales anonimizados.

## 6. Qué NO hace hoy el sistema

- No emite ni consulta comprobantes de ARCA.
- No guarda CUIT ni condición de IVA de los receptores.
- La "facturación mensual" (`facturacion_mensual`) es una carga manual de montos por sede y período,
  no un comprobante fiscal.
