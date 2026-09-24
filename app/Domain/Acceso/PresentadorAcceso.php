<?php

namespace App\Domain\Acceso;

use App\Models\Bloque;
use App\Models\Sede;

/**
 * Traduce permisos efectivos a textos legibles: funciones, contextos de trabajo y
 * "qué puede hacer" agrupado por módulo. Lo usan la ficha de persona, Usuarios y
 * permisos, y la API (/me).
 */
class PresentadorAcceso
{
    /** @var array<int, string> */
    private array $sedes = [];

    /** @var array<int, array{nombre: string, sede_id: ?int}> */
    private array $bloques = [];

    /**
     * Funciones (rol + ámbito + origen).
     *
     * @return list<array{clave: string, rol: string, rol_nombre: string, ambito: string, ambito_nombre: string, sede_id: ?int, bloque_id: ?int, origen: string, origen_etiqueta: string, asignacion_id: ?int, editable: bool}>
     */
    public function funciones(PermisosEfectivos $acceso): array
    {
        $roles = array_values(array_filter($acceso->roles(), fn (RolContextual $r) => ! str_starts_with($r->rol, 'permiso:')));
        $this->precargar($roles);

        $out = [];
        foreach ($roles as $r) {
            $out[] = [
                'clave' => $r->clave(),
                'rol' => $r->rol,
                'rol_nombre' => CatalogoPermisos::nombreRol($r->rol),
                'ambito' => $r->ambito,
                'ambito_nombre' => $this->nombreAmbito($r),
                'sede_id' => $r->sedeId,
                'bloque_id' => $r->bloqueId,
                'origen' => $r->origen,
                'origen_etiqueta' => RolContextual::etiquetaOrigen($r->origen),
                'asignacion_id' => $r->asignacionId,
                'editable' => $r->esEditable(),
            ];
        }
        usort($out, fn ($a, $b) => [$a['rol_nombre'], $a['ambito_nombre']] <=> [$b['rol_nombre'], $b['ambito_nombre']]);

        return $out;
    }

    /**
     * Contextos de trabajo para el selector "Estoy trabajando como":
     * un contexto por rol y sede (los bloques de una misma sede se agrupan).
     *
     * @return list<array{clave: string, rol: string, etiqueta: string, sede_id: ?int, sede: ?string, bloques: list<array{id: int, nombre: string}>}>
     */
    public function contextos(PermisosEfectivos $acceso): array
    {
        $roles = array_values(array_filter(
            $acceso->roles(),
            fn (RolContextual $r) => ! str_starts_with($r->rol, 'permiso:') && $r->rol !== 'becado'
        ));
        $this->precargar($roles);

        $grupos = [];
        foreach ($roles as $r) {
            $sedeId = $r->ambito === 'global' ? null : $r->sedeId;
            $clave = $r->rol.':'.($sedeId ?? 'global');
            $grupos[$clave] ??= [
                'clave' => $clave,
                'rol' => $r->rol,
                'etiqueta' => CatalogoPermisos::nombreRol($r->rol).' — '.($sedeId ? ($this->sedes[$sedeId] ?? 'Sede') : 'Global'),
                'sede_id' => $sedeId,
                'sede' => $sedeId ? ($this->sedes[$sedeId] ?? null) : null,
                'bloques' => [],
            ];
            if ($r->bloqueId) {
                $grupos[$clave]['bloques'][] = ['id' => $r->bloqueId, 'nombre' => $this->bloques[$r->bloqueId]['nombre'] ?? 'Bloque #'.$r->bloqueId];
            }
        }

        $orden = array_flip(array_keys(CatalogoPermisos::roles()));
        uasort($grupos, fn ($a, $b) => [$orden[$a['rol']] ?? 99, $a['etiqueta']] <=> [$orden[$b['rol']] ?? 99, $b['etiqueta']]);

        return array_values($grupos);
    }

    /**
     * Permisos efectivos agrupados por módulo, con dónde valen y de dónde vienen.
     *
     * @return array<string, list<array{permiso: string, etiqueta: string, alcance: string, via: list<string>}>>
     */
    public function permisosAgrupados(PermisosEfectivos $acceso): array
    {
        $this->precargar($acceso->roles());
        $out = [];
        foreach ($acceso->permisos() as $permiso) {
            $alcance = $acceso->alcance($permiso);
            $via = [];
            foreach ($acceso->origenesDe($permiso) as $origen) {
                [$tipo, $clave] = explode(':', $origen, 2) + [1 => ''];
                $rol = explode(':', $clave)[0] ?? '';
                $via[] = $tipo === 'permiso' ? 'Permiso adicional' : CatalogoPermisos::nombreRol($rol);
            }
            if ($acceso->esSuperadmin() && $via === []) {
                $via[] = 'Superadministrador';
            }
            $out[CatalogoPermisos::grupoDe($permiso)][] = [
                'permiso' => $permiso,
                'etiqueta' => CatalogoPermisos::etiqueta($permiso),
                'alcance' => $this->textoAlcance($alcance),
                'via' => array_values(array_unique($via)),
            ];
        }
        ksort($out);

        return $out;
    }

    public function textoAlcance(Alcance $alcance): string
    {
        if ($alcance->esGlobal()) {
            return 'Toda la escuela';
        }
        $partes = [];
        foreach ($alcance->sedeIds() as $id) {
            $partes[] = $this->sedes[$id] ?? Sede::query()->whereKey($id)->value('nombre') ?? 'Sede #'.$id;
        }
        foreach ($alcance->bloqueIds() as $id) {
            $partes[] = $this->bloques[$id]['nombre'] ?? Bloque::query()->whereKey($id)->value('nombre') ?? 'Bloque #'.$id;
        }

        return implode(', ', $partes);
    }

    private function nombreAmbito(RolContextual $r): string
    {
        return match ($r->ambito) {
            'sede' => $this->sedes[$r->sedeId] ?? 'Sede #'.$r->sedeId,
            'bloque' => ($this->bloques[$r->bloqueId]['nombre'] ?? 'Bloque #'.$r->bloqueId)
                .($r->sedeId && isset($this->sedes[$r->sedeId]) ? ' ('.$this->sedes[$r->sedeId].')' : ''),
            default => 'Global',
        };
    }

    /** @param  list<RolContextual>  $roles */
    private function precargar(array $roles): void
    {
        $sedes = [];
        $bloques = [];
        foreach ($roles as $r) {
            if ($r->sedeId) {
                $sedes[] = $r->sedeId;
            }
            if ($r->bloqueId) {
                $bloques[] = $r->bloqueId;
            }
        }
        $faltanBloques = array_diff(array_unique($bloques), array_keys($this->bloques));
        if ($faltanBloques !== []) {
            foreach (Bloque::query()->whereIn('id', $faltanBloques)->get(['id', 'nombre', 'sede_id']) as $b) {
                $this->bloques[$b->id] = ['nombre' => $b->nombre, 'sede_id' => $b->sede_id];
                if ($b->sede_id) {
                    $sedes[] = (int) $b->sede_id;
                }
            }
        }
        $faltanSedes = array_diff(array_unique($sedes), array_keys($this->sedes));
        if ($faltanSedes !== []) {
            $this->sedes += Sede::query()->whereIn('id', $faltanSedes)->pluck('nombre', 'id')->all();
        }
    }
}
