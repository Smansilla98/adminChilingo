<?php

namespace App\Models;

use App\Domain\Acceso\PermisosEfectivos;
use App\Domain\Acceso\ResolvedorAcceso;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Auditable, HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'name',
        'email',
        'telefono',
        'password',
        'role',
        'persona_id',
        'activo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'modulos_access',
    ];

    /** Permisos efectivos calculados (una vez por request). */
    protected ?PermisosEfectivos $accesoCalculado = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acceso_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'modulos_access' => 'array',
            'apariencia_json' => 'array',
        ];
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function dispositivos(): HasMany
    {
        return $this->hasMany(Dispositivo::class);
    }

    /**
     * Qué puede hacer este usuario y dónde (roles de todas sus funciones + asignaciones).
     */
    public function acceso(): PermisosEfectivos
    {
        return $this->accesoCalculado ??= app(ResolvedorAcceso::class)->paraUsuario($this);
    }

    /** Recalcular permisos tras cambiar asignaciones o perfiles en el mismo request. */
    public function olvidarAcceso(): static
    {
        $this->accesoCalculado = null;

        return $this;
    }

    public function puede(string $permiso): bool
    {
        return $this->acceso()->puede($permiso);
    }

    /**
     * Preferencia visual del usuario (tokens: accent, font_display, font_body).
     *
     * @return array{accent: string, font_display: string, font_body: string}
     */
    public function aparienciaTema(): array
    {
        return \App\Support\AparienciaTema::normalizar(
            is_array($this->apariencia_json) ? $this->apariencia_json : null
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de compatibilidad
    |--------------------------------------------------------------------------
    | Vistas y controladores existentes preguntan por "roles". Estos métodos
    | se resuelven ahora con permisos efectivos + alcance (App\Domain\Acceso).
    */

    /**
     * Administrador global (dirección) o superadministrador.
     */
    public function isAdmin(): bool
    {
        return $this->acceso()->esAdministrador();
    }

    /**
     * Da clase en al menos un bloque (o tiene el rol asignado).
     */
    public function isProfesor(): bool
    {
        return $this->acceso()->tieneRol('profesor');
    }

    public function isAlumno(): bool
    {
        return $this->acceso()->tieneRol('alumno') || $this->role === 'alumno';
    }

    public function isDireccion(): bool
    {
        return $this->isAdmin();
    }

    public function isCoordinadorSede(): bool
    {
        return $this->acceso()->tieneRol('coordinador');
    }

    public function isCoordinadorArea(): bool
    {
        return $this->acceso()->tieneRol('coordinador_area');
    }

    /**
     * IDs de bloques que el usuario puede ver/editar en asistencias. Vacío + veTodosLosBloques = todos.
     *
     * @return list<int>
     */
    public function bloqueIdsPermitidos(): array
    {
        $alcance = $this->acceso()->alcance('asistencias.view');
        if ($alcance->esGlobal()) {
            return [];
        }
        $ids = $alcance->bloqueIds();
        if ($alcance->sedeIds() !== []) {
            $ids = array_merge($ids, Bloque::query()->whereIn('sede_id', $alcance->sedeIds())->pluck('id')->all());
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));

        return $ids !== [] ? $ids : [0];
    }

    public function veTodosLosBloques(): bool
    {
        return $this->acceso()->puedeGlobal('asistencias.view');
    }

    /**
     * Gestiona alumnos por sede (coordinación) sin ser dirección.
     */
    public function acotaPorSede(): bool
    {
        $alcance = $this->acceso()->alcance('alumnos.view');

        return ! $alcance->esGlobal() && $alcance->sedeIds() !== [];
    }

    /**
     * Sedes donde tiene alcance de gestión sobre alumnos.
     *
     * @return list<int>
     */
    public function sedeIdsOperativas(): array
    {
        return $this->acceso()->alcance('alumnos.view')->sedeIds();
    }

    public function puedeGestionarAlumno(Alumno $alumno): bool
    {
        return $this->acceso()->puedeSobreAlumno('alumnos.view', $alumno);
    }

    public function puedeAccederBloque(int $bloqueId): bool
    {
        return $this->acceso()->puedeEnBloque('asistencias.view', $bloqueId);
    }

    /**
     * Ve el panel de gestión (no solo "Mi espacio").
     */
    public function puedeGestionarOperativo(): bool
    {
        return $this->acceso()->puedeAlguno([
            'alumnos.create', 'sedes.manage', 'bloques.manage', 'reportes.view', 'cuotas.view',
            'pagos.create', 'gastos.view', 'inventario.view', 'facturacion.view', 'usuarios.view', 'personas.view',
        ]);
    }

    public function puedeVerReportes(): bool
    {
        return $this->acceso()->puede('reportes.view');
    }

    /**
     * Etiqueta legible del rol principal.
     */
    public function etiquetaRol(): string
    {
        $acceso = $this->acceso();
        foreach ([
            'superadministrador' => 'Superadministrador',
            'administrador' => 'Administración',
            'coordinador' => 'Coordinación',
            'coordinador_area' => 'Coordinación de área',
            'tesorero' => 'Tesorería',
            'contador' => 'Contaduría',
            'administrativo' => 'Administrativo',
            'profesor' => 'Profesor',
            'encargado' => 'Encargado',
            'responsable_de_sede' => 'Responsable de sede',
            'responsable_de_inventario' => 'Inventario',
            'alumno' => 'Alumno',
        ] as $rol => $etiqueta) {
            if ($acceso->tieneRol($rol)) {
                return $etiqueta;
            }
        }

        return 'Usuario';
    }

    /**
     * IDs de sedes que coordina.
     *
     * @return list<int>
     */
    public function sedeIdsCoordinadas(): array
    {
        $ids = [];
        foreach ($this->acceso()->roles() as $r) {
            if (in_array($r->rol, ['coordinador', 'administrador'], true) && $r->ambito === 'sede' && $r->sedeId) {
                $ids[] = $r->sedeId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Clave de módulo del menú → permiso que la habilita.
     */
    public const PERMISO_POR_MODULO = [
        'programa' => null,
        'ayuda' => null,
        'calendario' => 'calendario.view',
        'comprobantes' => 'comprobantes.view',
        'profesor.mis_bloques' => 'bloques.view',
        'profesor.asistencia' => 'asistencias.create',
        'profesor.mis_alumnos' => 'alumnos.view',
        'profesor.pagos_cuotas' => 'pagos.view',
        'profesor.mis_eventos' => 'eventos.view',
        'admin.alumnos' => 'alumnos.view',
        'admin.importar' => 'alumnos.import',
        'admin.profesores' => 'profesores.view',
        'admin.bloques' => 'bloques.view',
        'admin.sedes' => 'sedes.view',
        'admin.cuotas' => 'cuotas.view',
        'admin.pagos' => 'pagos.view',
        'admin.eventos' => 'eventos.view',
        'admin.asistencias' => 'asistencias.view',
        'admin.reportes' => 'reportes.view',
        'admin.facturacion_mensual' => 'facturacion.view',
        'admin.inventarios' => 'inventario.view',
        'admin.plan_compras' => 'compras.view',
        'admin.ordenes_compra' => 'compras.view',
        'admin.gastos' => 'gastos.view',
        'admin.shows' => 'shows.view',
        'admin.villa_gesell' => 'villa_gesell.manage',
        'admin.disenos' => 'disenos.manage',
        'admin.personas' => 'personas.view',
        'admin.usuarios' => 'usuarios.view',
    ];

    public function puedeVerLinkGestion(string $clave): bool
    {
        return $this->tieneAccesoModulo($clave);
    }

    /**
     * Acceso a un módulo del menú: requiere el permiso correspondiente y que el módulo
     * no esté ocultado explícitamente en la matriz de visibilidad (users.modulos_access).
     */
    public function tieneAccesoModulo(string $clave): bool
    {
        $map = is_array($this->modulos_access) ? $this->modulos_access : [];
        if (! $this->isAdmin() && array_key_exists($clave, $map) && ! (bool) $map[$clave]) {
            return false;
        }

        $permiso = self::PERMISO_POR_MODULO[$clave] ?? null;
        if ($permiso === null) {
            return array_key_exists($clave, self::PERMISO_POR_MODULO);
        }

        return $this->acceso()->puede($permiso);
    }

    /**
     * Perfil como profesor (un usuario puede ser profesor en unos bloques y alumno en otros)
     */
    public function profesor()
    {
        return $this->hasOne(Profesor::class);
    }

    /**
     * Perfil como alumno (el mismo usuario puede ser alumno en uno o más bloques)
     */
    public function alumno()
    {
        return $this->hasOne(Alumno::class);
    }

    /**
     * Relación con eventos creados
     */
    public function eventos()
    {
        return $this->hasMany(Evento::class, 'created_by');
    }
}
