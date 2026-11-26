# Auditoría QA: circuitos, números y permisos

Rama `qa-auditoria` (desde `estabilizacion`). Pruebas en `tests/Feature/Auditoria/`.

## Circuitos cubiertos

- **Cobro y morosidad** (`CircuitoCobroAuditoriaTest`): cuota vencida hace 40 días ($10.000) → morosidad
  (tramo 31-60) → pago parcial $4.000 (saldo $6.000, estado parcial) → sobrepago de $6.500 rechazado sin dejar
  rastro → pago total $6.000 (sale de morosos) → anulación del primer pago (vuelve la deuda de $4.000 y el
  alumno vuelve a morosos). Cuadre de líneas vigentes vs. crudas (10.000 con anulados, 6.000 vigentes).
  Recibo PDF y detalle del pago responden.
- **Totales de gastos** (`TotalesGastosAuditoriaTest`): ingreso $6.000, gastos aprobado $3.000, pendiente $2.000,
  rechazado $5.000. Resultado esperado: gastos $5.000 y resultado $1.000.
- **Permisos y menú** (`PermisosMenuAuditoriaTest`): el alumno no ve ni abre pantallas de administración; el
  profesor (alcance de bloque) ve solo sus alumnos y sus pagos en su pantalla de cobro, y no abre gastos,
  reportes, dirección ni liquidaciones; el admin ve y abre todo el menú de administración.
- **Barrido de pantallas** (`BarridoPantallasAuditoriaTest`): toda ruta web GET sin parámetros responde sin 5xx
  con el admin y base vacía.

Total agregado: 8 pruebas (3 + 1 + 3 + 1), todas pasan.

## Hallazgos

### ALTO (corregido): gastos rechazados restaban en reportes y tableros

- Circuito: Gastos → rechazar un gasto → Reportes / Tablero de dirección / Dashboard.
- Esperado: un gasto rechazado no es gasto; no suma a gastos totales, al resultado, a gastos por sede, a insumos
  ni a la tendencia mensual.
- Obtenido: `ReportesService::compilar` daba gastos totales de $10.000 (incluía el rechazado de $5.000) y un
  resultado de -$4.000 en vez de $1.000.
- Impacto: el resultado financiero y los gastos que ve dirección estaban inflados. Afecta decisiones de dinero.
- Corrección: filtro `estado != 'rechazado'` en las consultas de gastos de `ReportesService` (por sede, insumos y
  total), `TendenciasService` y `Services/Dashboard/RecaudacionService`. Prueba de regresión:
  `TotalesGastosAuditoriaTest`.
- Nota: el gráfico de recaudación del dashboard recibe la misma corrección, pero no tiene prueba propia.

### BAJO (documentado, no corregido): tolerancia de centavos entre total y líneas

- `RegistroPagoService::preparar` acepta `monto_total` distinto de la suma de líneas hasta $0,02. Se guarda el
  total tal como lo cargó. Los reportes suman líneas (`pago_detalles`) y el dashboard suma `monto_total`, así que
  pueden diferir en centavos. Hallazgo por lectura de código, sin prueba. Sugerencia: tolerancia 0 o redondeo
  explícito.

### A DEFINIR (decisión de negocio): gastos pendientes

- Los gastos `pendiente` (cargados por quien no puede aprobar) siguen sumando en reportes. Se dejó así porque es
  una decisión de la dirección: confirmar si un gasto pendiente debe contar como gasto del período.

### INFO (no es fallo): alcance del profesor

- El rol profesor tiene `pagos.view` y `morosos` y `alumnos.view` por diseño. Con alcance de bloque, el listado
  general de pagos y de morosos no muestra datos (alcance de sede `[0]`), y el padrón de alumnos sí muestra
  solo su bloque. No se encontró fuga de datos en las pruebas. Su pantalla de cobro es `profesor.pagos-cuotas`.

## No cubierto en esta pasada

Liquidación docente (cálculo y pago), cierre de mes, Villa Gesell (seña, saldo, gastos, plan), generación masiva
de cuotas por lote, flujo de comprobantes por el alumno y cifras del tablero de dirección. Quedan para la
próxima auditoría.

## Entorno

- El worktree no tiene `.env` (no versionado). Las pruebas corren con `APP_KEY` por variable de entorno y
  `public/build` copiado del repo principal. Por eso la suite reporta advertencias `file_get_contents(.env)`
  en todas las pruebas. No hay fallos.
