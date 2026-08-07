# MVP Administrativo — La Chilinga

Checklist de lo que debe estar cargado y configurado para usar el sistema como MVP administrativo.

## Lo que se agregó para cerrar el MVP

1. **Gastos (CRUD)**  
   Sin gastos cargados, los reportes no muestran egresos.  
   - Menú: **Gastos** → Nuevo gasto.  
   - Tipos: Sueldos, Alquiler, Servicios (luz/agua), Reparaciones (edilicio/tambores), Insumos, Servicios externos (electricista, plomero, etc.), Otros.  
   - Asignar sede (y opcionalmente bloque), fecha, monto y subtipo cuando aplique.

2. **Sedes: tipo de propiedad y alquiler**  
   En **Sedes** → crear o editar una sede podés indicar:  
   - Tipo de propiedad: Alquilada, Propia, Compartida, Otro.  
   - Costo alquiler mensual (para que aparezca en Reportes).

3. **Vistas de Eventos**  
   **Eventos** tiene listado, alta, edición y detalle (tipos: show, taller, muestra, gira, aniversario, fiesta, rifa, otro).  
   Se reflejan en Calendario.

4. **Vistas de Sedes**  
   **Sedes** tiene listado, alta, edición y detalle con los campos de propiedad y alquiler.

5. **Reportes solo para admin**  
   La ruta y el ítem de menú **Reportes** quedan dentro del middleware de admin; solo usuarios admin los ven.

---

## Uso recomendado del MVP

| Módulo | Uso |
|--------|-----|
| **Sedes** | Dar de alta sedes con tipo de propiedad y costo de alquiler. |
| **Gastos** | Cargar todos los egresos (sueldos, alquiler, luz, agua, reparaciones, servicios externos, insumos) por sede/fecha para que Reportes sea útil. |
| **Cuotas** | Definir cuotas por mes/año. |
| **Pagos** | Registrar pagos de alumnos (uno o varios), cuota, fecha y opcionalmente PDF. |
| **Facturación** | Resumen mensual por sede (cantidad alumnos, monto facturado). |
| **Inventarios** | Ítems por sede (tambores, herramientas, repuestos, parches, etc.). |
| **Órdenes de compra** | Formalizar compras con ítems y justificación. |
| **Reportes** | Revisar alumnos por profesor/bloque, ingresos por sede/bloque, egresos por tipo (sueldos, alquiler, luz, agua, reparaciones, servicios externos), propiedad de sedes y resumen invertido vs recuperado. |

---

## Opcional para una próxima iteración

- **Filtros por período en Reportes** (año, mes o rango de fechas).  
- **Seeders** para datos de prueba (sedes, usuarios, cuotas, algunos gastos).  
- **Enlace Plan de compras → Nueva orden** para pre-cargar ítems sugeridos.  
- **Exportación** de reportes (Excel/PDF).  
- **Backup** de base de datos documentado (cron o comando artisan).
