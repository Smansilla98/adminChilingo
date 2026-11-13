# Ciclo lectivo, calendario académico y reinscripción

Un ciclo lectivo lleva el año en que empieza (el ciclo **N**). Tiene tres módulos, cada uno con sus fechas:

| Módulo | Fechas por defecto | Para qué |
|---|---|---|
| Cuotas | marzo N a febrero N+1 | Las cuotas mensuales de cada bloque (12 meses). |
| Académico | marzo a diciembre N | Clases regulares. |
| Reinscripción | febrero a mediados de marzo N | Ventana para reinscribirse (se avisa al abrir el ciclo). |

## Calendario académico

Cada mes del ciclo tiene una **lista de actividades**. Marzo a diciembre son clases. Enero y febrero
son **taller de verano** y, en paralelo, la **gira a Villa Gesell**: ambos meses tienen las dos
actividades. Se configura en `config/chilinga.php`, clave `calendario_academico` (mes 1-12 → lista de
`taller_verano` / `gira_gesell`). Un mes sin entrada es solo clases.

Se ve en la pantalla del ciclo (Ciclos lectivos → Reinscripciones) y en la API:
`GET /api/v1/ciclos-lectivos/{ciclo}/calendario` devuelve 12 meses, cada uno con
`actividades: [{tipo, etiqueta}]`.

## Cuotas del ciclo

`POST /api/v1/ciclos-lectivos/{ciclo}/cuotas` (`monto`, opcional `montos` por bloque) o el botón
«Generar cuotas que falten» en la pantalla del ciclo (requiere `cuotas.create` en toda la escuela):

- crea una cuota por cada bloque activo y cada mes marzo N a febrero N+1 que todavía no tenga;
- **no toca** las cuotas que ya existen (monto, nombre, estado y pagos quedan como están), así que se
  puede volver a correr sin duplicar;
- queda en la auditoría.

La generación no se dispara sola: es una acción de administración.

## Reinscripción

Al abrir el ciclo, cada alumno activo queda con una reinscripción pendiente y recibe un aviso.
Confirma desde su cuenta (`/mi-reinscripcion`, o `mi/reinscripcion` en la API). La administración
puede dar de baja a quien no se reinscribió: el alumno queda inactivo, y sus cuotas, pagos,
asistencias y bloques no se borran.
