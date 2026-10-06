<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ubicación pública de las sedes para el mapa del programa.
 *
 * Completa las sedes del programa que ya existen (por nombre) con la dirección
 * publicada y, cuando OpenStreetMap la ubica a la altura exacta, sus coordenadas.
 * Palomar y Varela no tienen la altura cargada en OSM: quedan sin punto, para que la
 * escuela las ubique desde la ficha de la sede (no se adivina una ubicación).
 * No crea sedes: las sedes son también la estructura interna (bloques, cuotas).
 */
return new class extends Migration
{
    /** @var array<string, array{direccion: string, lat: float|null, lng: float|null}> */
    private const SEDES_DEL_PROGRAMA = [
        'palomar' => ['direccion' => 'Ing. Marconi 181, El Palomar', 'lat' => null, 'lng' => null],
        'saavedra' => ['direccion' => 'Ruiz Huidobro 4228, Saavedra, CABA', 'lat' => -34.5526009, 'lng' => -58.4894420],
        'varela' => ['direccion' => 'Cerro Aconcagua 2153, Florencio Varela', 'lat' => null, 'lng' => null],
        'quilmes' => ['direccion' => 'Humberto Primo 320, Quilmes', 'lat' => -34.7231682, 'lng' => -58.2534036],
        'banfield' => ['direccion' => 'Av. Alsina 251, Banfield', 'lat' => -34.7391702, 'lng' => -58.3925883],
        'tacheles' => ['direccion' => 'Alsina 1475, Congreso, CABA', 'lat' => -34.6110612, 'lng' => -58.3870983],
    ];

    public function up(): void
    {
        Schema::table('sedes', function (Blueprint $table): void {
            $table->decimal('latitud', 10, 7)->nullable()->after('direccion');
            $table->decimal('longitud', 10, 7)->nullable()->after('latitud');
            $table->boolean('en_mapa')->default(false)->after('longitud');
        });

        foreach (DB::table('sedes')->get(['id', 'nombre', 'direccion', 'latitud']) as $sede) {
            $clave = collect(array_keys(self::SEDES_DEL_PROGRAMA))
                ->first(fn ($k) => str_contains(mb_strtolower($sede->nombre), $k));
            if ($clave === null) {
                continue;
            }
            $datos = self::SEDES_DEL_PROGRAMA[$clave];
            $cambios = ['en_mapa' => true];
            if (blank($sede->direccion)) {
                $cambios['direccion'] = $datos['direccion'];
            }
            if ($sede->latitud === null && $datos['lat'] !== null) {
                $cambios['latitud'] = $datos['lat'];
                $cambios['longitud'] = $datos['lng'];
            }
            DB::table('sedes')->where('id', $sede->id)->update($cambios);
        }
    }

    public function down(): void
    {
        Schema::table('sedes', function (Blueprint $table): void {
            $table->dropColumn(['latitud', 'longitud', 'en_mapa']);
        });
    }
};
