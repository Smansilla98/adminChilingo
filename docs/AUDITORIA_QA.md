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

Segunda tanda:

- **Liquidación docente** (`LiquidacionAuditoriaTest`, octubre 2026): dos sedes con valor hora propio y general
  ($1.500 y $1.000), una ausencia que resta una hora. Total $13.000 = 6 h × 1.500 + 4 h × 1.000. El pago genera un
  gasto por sede que suma exactamente $13.000. Pagar dos veces se rechaza sin duplicar gastos. Un mes pagado no se
  recalcula.
- **Cierre de mes** (`CierreMesAuditoriaTest`): checklist con cifras exactas (1 bloque sin asistencia del mes, 1
  comprobante pendiente, 2 cuotas activas, la inactiva no cuenta). Es idempotente y no modifica datos. Solo lo ve
  quien tiene `facturacion.view`.
- **Villa Gesell** (`VillaGesellAuditoriaTest`): saldo por inscripto, aporte por días, plan de gastos (valor único
  y por día), insumos, balance pagado y esperado.
- **Tablero de dirección** (`TableroDireccionAuditoriaTest`): cobrado, gastos, resultado, margen, emitido y cobranza
  del mes en curso.

Total agregado: 18 pruebas en la primera y segunda tanda; la suite completa da 569 pasando.

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

### ALTO (corregido): cuotas desactivadas se emitían en el tablero

- Circuito: Cuotas (cuota reemplazada, queda `activo = false`) → Tablero de dirección.
- Esperado: emitido del mes = 3 alumnos activos × $10.000 = $30.000.
- Obtenido: $60.000, porque `TendenciasService::emitidoPorMes` no filtraba `activo`. Eso bajaba la cobranza
  (33,3 % en vez de 16,7 %) y distorsionaba la proyección de ingresos del mes siguiente.
- Corrección: `where('activo', true)` en `emitidoPorMes`. Regresión: `TableroDireccionAuditoriaTest`.

### ALTO (corregido): lista de espera inflaba los ingresos esperados de Villa Gesell

- Circuito: Villa Gesell → inscriptos (2 con plaza, 1 en lista de espera de $15.000) → Plan de gastos.
- Esperado: ingresos esperados $17.500 (solo plazas), balance esperado $10.500.
- Obtenido: ingresos esperados $32.500 y balance esperado $25.500. La lista de espera no ocupa plaza, así que no es
  ingreso previsto.
- Corrección: `plan()` suma `monto_esperado` solo de `lista_espera = false`. Regresión:
  `VillaGesellAuditoriaTest::test_ingresos_esperados_no_incluyen_lista_de_espera`.

### INFO: comprobantes pendientes del checklist de cierre

- El checklist cuenta los comprobantes pendientes de cualquier mes, no solo del mes cerrado. Puede ser deliberado (no
  cerrar con deuda de revisión) y no se cambió. Confirmar con la dirección.

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

Generación masiva de cuotas por lote, flujo de comprobantes cargados por el alumno, alta de inscriptos de Villa
Gesell desde la web (solo se probó el modelo y el plan), morosidad del tablero por sede y tendencias de 12 meses
con pagos en meses anteriores. Quedan para la próxima auditoría.

## Entorno

- El worktree no tiene `.env` (no versionado). Las pruebas corren con `APP_KEY` por variable de entorno y
  `public/build` copiado del repo principal. Por eso la suite reporta advertencias `file_get_contents(.env)`
  en todas las pruebas. No hay fallos.
