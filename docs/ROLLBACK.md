# Volver atrás: deploys y backups

> Los comandos `railway ssh` corren dentro del contenedor. Con `RAILWAY_RUN_UID=0` la sesión es root:
> anteponé `gosu www-data` (ej. `railway ssh --service web -- gosu www-data php artisan chilinga:respaldo`)
> para que los archivos que se creen queden con el dueño correcto.

## 1. Un deploy salió mal (la app no levanta o falla)

- **Si fallaron las migraciones o `/salud` no responde**: Railway no pasa el tráfico al deploy nuevo y
  sigue sirviendo el anterior. Mirá los logs del deploy fallido (`✗ Las migraciones fallaron`), arreglá y
  volvé a deployar.
- **Si el deploy quedó activo pero anda mal**: Railway → servicio → Deployments → el deploy anterior que
  andaba → **Redeploy**. Hacelo también en `scheduler` y `worker` para que corran el mismo código.
- **Migraciones**: volver el código atrás **no** deshace migraciones ya aplicadas. Si la migración nueva
  rompió datos, restaurá un backup (sección 3). Si solo agregó columnas/tablas, el código anterior
  normalmente convive con ellas; si hace falta: `railway ssh --service web -- php artisan migrate:rollback --step=1`
  (solo con la migración revisada y un backup recién hecho).

## 2. Dónde están los backups

| Dónde | Qué | Cuántos |
|-------|-----|---------|
| Volumen de Railway, `respaldos/chilinga-AAAAMMDD-HHMMSS.sql.gz` | SQL comprimido, **sin cifrar** | últimos 14 |
| Bucket S3/R2 (`RESPALDO_DISCO_EXTERNO`), `respaldos/chilinga-AAAAMMDD-HHMMSS.sql.gz.enc` | mismo SQL, **cifrado** con `RESPALDO_CLAVE` | últimos 30 (`RESPALDO_EXTERNO_CONSERVAR`) |
| Railway → MySQL → Backups (si el plan los tiene) | backup del servicio MySQL | según Railway |

El backup se genera todos los días a las 3:30 (`chilinga:respaldo`). A mano: `php artisan chilinga:respaldo`.
Estado del sistema (Configuración) muestra el último, si tiene copia externa, y permite descargar el local.

**Contienen datos personales**: no se mandan por mail ni se comparten. La clave `RESPALDO_CLAVE` se
guarda fuera de Railway; sin ella las copias externas no se pueden abrir.

## 3. Probar que un backup sirve (sin tocar la base real)

```bash
railway ssh --service web -- php artisan chilinga:respaldo:probar            # último backup local
railway ssh --service web -- php artisan chilinga:respaldo:probar --externo  # última copia del bucket (descifra con RESPALDO_CLAVE)
railway ssh --service web -- php artisan chilinga:respaldo:probar --archivo=respaldos/chilinga-20261110-033000.sql.gz
```

Crea una base temporal `<base>_prueba_xxxxxx` en el mismo servidor MySQL, carga el backup, cuenta
tablas, filas y migraciones, y la borra (`--conservar-base` la deja para revisarla a mano).
Hacelo **una vez por mes** y anotá el resultado: un backup que nunca se probó no es un backup.

## 4. Restaurar en producción

1. **Avisá y frená la carga**: nadie registra pagos mientras tanto. Si se puede, poné el web en
   mantenimiento: `railway ssh --service web -- php artisan down` (y `up` al terminar).
2. **Hacé un backup del estado actual** aunque esté roto: `railway ssh --service web -- php artisan chilinga:respaldo`
   (se puede necesitar algo de ahí después).
3. **Conseguí el archivo**:
   - Local: Estado del sistema → "Descargar el último backup", o entrando con `railway ssh --service web` y copiándolo del volumen.
   - Externo: descargalo del bucket (panel de Cloudflare R2 o `aws s3 cp --endpoint-url ...`) y descifralo:
     ```bash
     export RESPALDO_CLAVE='...'   # desde el gestor de contraseñas
     openssl enc -d -aes-256-cbc -pbkdf2 -iter 100000 -md sha256 -pass env:RESPALDO_CLAVE \
       -in chilinga-AAAAMMDD-HHMMSS.sql.gz.enc -out chilinga-AAAAMMDD-HHMMSS.sql.gz
     ```
4. **Probalo antes** (sección 3) o cargalo primero en una base vacía de prueba.
5. **Restaurá** (reemplaza todas las tablas que estén en el backup):
   ```bash
   gunzip -c chilinga-AAAAMMDD-HHMMSS.sql.gz | mysql -h HOST -P PUERTO -u USUARIO -p NOMBRE_BASE
   ```
   Los datos de conexión están en Railway → MySQL → Connect (usá la conexión pública/TCP proxy).
6. Redeploy del web (corre migraciones pendientes si el backup es anterior a alguna) y `php artisan up`.
7. Revisá Estado del sistema, un par de alumnos y los últimos pagos. Todo lo cargado **después** del
   backup se perdió: pedí a tesorería los comprobantes de ese intervalo para recargarlos.

## 5. Archivos (comprobantes, fotos)

Viven en el volumen (`PERSISTENT_STORAGE_PATH`) y **no** están en el backup de la base. Si se pierde el
volumen, se pierden. Railway tiene backups de volúmenes en los planes pagos (Settings → Volumes →
Backups): activarlos es la medida recomendada hasta mover los archivos a S3/R2.
