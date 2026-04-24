<?php

namespace Database\Seeders;

use App\Models\Alumno;
use App\Models\Sede;
use Illuminate\Database\Seeder;

class BanfieldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sede = Sede::where('nombre', 'Banfield')->firstOrFail();

        $alumnos = [
            ['nombre' => 'Alejandro Santa Lucía', 'dni' => '33509120', 'fecha_nacimiento' => '1987-12-15', 'telefono' => '1162827346', 'tipo_tambor' => 'Redoblante', 'tambor_procedencia' => 'Sede'],
            ['nombre' => 'Alessandra Cataldo', 'dni' => '40947484', 'fecha_nacimiento' => '1998-03-15', 'telefono' => '1168029814', 'tipo_tambor' => 'Repique', 'tambor_procedencia' => 'Sede'],
            ['nombre' => 'Analía Mora', 'dni' => '23853748', 'fecha_nacimiento' => '1974-09-28', 'telefono' => '1157572259', 'tipo_tambor' => 'Medio', 'tambor_procedencia' => 'Propio'],
            ['nombre' => 'Andrea Paula Silvera', 'dni' => '36066157', 'fecha_nacimiento' => '1991-10-24', 'telefono' => '1121765054', 'tipo_tambor' => 'Redoblante', 'tambor_procedencia' => 'Sede'],
            ['nombre' => 'Camilo Gael Mussanti', 'dni' => '54892297', 'fecha_nacimiento' => '2015-06-03', 'telefono' => '1150359856', 'tipo_tambor' => 'Repique', 'tambor_procedencia' => 'Propio'],
            ['nombre' => 'Carina Rodriguez', 'dni' => '31014862', 'fecha_nacimiento' => '1984-08-04', 'telefono' => '1169993406', 'tipo_tambor' => 'Fondo Agudo', 'tambor_procedencia' => 'Propio'],
            ['nombre' => 'Federico Galarraga', 'dni' => '35000489', 'fecha_nacimiento' => '1990-07-05', 'telefono' => '1168677519', 'tipo_tambor' => 'Repique', 'tambor_procedencia' => 'Sede'],
            ['nombre' => 'Joaquin Monti', 'dni' => '50968737', 'fecha_nacimiento' => '2011-05-03', 'telefono' => '1128584767', 'tipo_tambor' => 'Repique', 'tambor_procedencia' => 'Sede'],
            ['nombre' => 'Kiara Marquez', 'dni' => '51435395', 'fecha_nacimiento' => '2011-10-26', 'telefono' => '1141417491', 'tipo_tambor' => 'Redoblante', 'tambor_procedencia' => 'Propio'],
            ['nombre' => 'Marcela Fabiana Alcario', 'dni' => '26721417', 'fecha_nacimiento' => '1970-02-25', 'telefono' => '1150359856', 'tipo_tambor' => 'Fondo Agudo', 'tambor_procedencia' => 'Sede'],
            ['nombre' => 'Mariela A Romero', 'dni' => '22848925', 'fecha_nacimiento' => '1972-08-14', 'telefono' => '1163740186', 'tipo_tambor' => 'Medio', 'tambor_procedencia' => 'Propio'],
            ['nombre' => 'Marisol Andrea Gómez', 'dni' => '27865594', 'fecha_nacimiento' => '1980-02-14', 'telefono' => '1128424217', 'tipo_tambor' => 'Medio', 'tambor_procedencia' => 'Sede'],
            ['nombre' => 'Jimena Martinez', 'dni' => '36156012', 'fecha_nacimiento' => '1991-03-27', 'telefono' => '1132646146', 'tipo_tambor' => 'Medio', 'tambor_procedencia' => 'Sede'],
            ['nombre' => 'Matias Billera', 'dni' => '39654493', 'fecha_nacimiento' => '1996-06-10', 'telefono' => '1160146427', 'tipo_tambor' => 'Fondo Grave', 'tambor_procedencia' => 'Sede'],
            ['nombre' => 'Miguel Ángel Sanchez', 'dni' => '33400756', 'fecha_nacimiento' => '1987-10-13', 'telefono' => '1164813222', 'tipo_tambor' => 'Medio', 'tambor_procedencia' => 'Sede'],
            ['nombre' => 'Noelía Di Benedetto', 'dni' => '30664329', 'fecha_nacimiento' => '1983-12-16', 'telefono' => '1143997904', 'tipo_tambor' => 'Medio', 'tambor_procedencia' => 'Propio'],
            ['nombre' => 'Pablo Rubén Salazar', 'dni' => '40472216', 'fecha_nacimiento' => '1997-05-07', 'telefono' => '1177189090', 'tipo_tambor' => 'Repique', 'tambor_procedencia' => 'Sede'],
            ['nombre' => 'Patricio Kachonosky', 'dni' => '39371910', 'fecha_nacimiento' => '1995-11-16', 'telefono' => '1158428126', 'tipo_tambor' => 'Medio', 'tambor_procedencia' => 'Sede'],
            ['nombre' => 'Patricio Silva', 'dni' => '33914850', 'fecha_nacimiento' => '1988-08-20', 'telefono' => '1123626529', 'tipo_tambor' => 'Repique', 'tambor_procedencia' => 'Sede'],
            ['nombre' => 'Paula Caffarel', 'dni' => '32421369', 'fecha_nacimiento' => '1986-04-17', 'telefono' => '1150471499', 'tipo_tambor' => 'Repique', 'tambor_procedencia' => 'Sede'],
            ['nombre' => 'Priscila Benítez', 'dni' => '94181485', 'fecha_nacimiento' => '1991-11-22', 'telefono' => '1123210575', 'tipo_tambor' => 'Medio', 'tambor_procedencia' => 'Propio'],
            ['nombre' => 'Rocío Barreto', 'dni' => '40642812', 'fecha_nacimiento' => '1997-08-15', 'telefono' => '1141635205', 'tipo_tambor' => 'Repique', 'tambor_procedencia' => 'Sede'],
            ['nombre' => 'Rodrigo Valdéz', 'dni' => '36149376', 'fecha_nacimiento' => '1991-04-24', 'telefono' => '1568280749', 'tipo_tambor' => 'Timbal', 'tambor_procedencia' => 'Sede'],
            ['nombre' => 'Sofia Sol Luchini', 'dni' => '38304181', 'fecha_nacimiento' => '1994-05-23', 'telefono' => '1168451117', 'tipo_tambor' => 'Repique', 'tambor_procedencia' => 'Sede'],
            ['nombre' => 'Sol Frattura', 'dni' => '40382006', 'fecha_nacimiento' => '1997-04-10', 'telefono' => '1134039146', 'tipo_tambor' => 'Medio', 'tambor_procedencia' => 'Sede'],
            ['nombre' => 'Sonia Argüelles', 'dni' => '23032383', 'fecha_nacimiento' => '1973-01-25', 'telefono' => '1156535346', 'tipo_tambor' => 'Fondo Grave', 'tambor_procedencia' => 'Sede'],
            ['nombre' => 'Susana Edith Martinez Troyon', 'dni' => '14774522', 'fecha_nacimiento' => '1961-10-17', 'telefono' => '1166112140', 'tipo_tambor' => 'Medio', 'tambor_procedencia' => 'Sede'],
            ['nombre' => 'Thiago Benitez', 'dni' => '42885778', 'fecha_nacimiento' => '2000-09-25', 'telefono' => '1158959856', 'tipo_tambor' => 'Fondo Grave', 'tambor_procedencia' => 'Sede'],
            ['nombre' => 'Veronica Oviedo', 'dni' => '20477545', 'fecha_nacimiento' => '1968-09-13', 'telefono' => '1159987802', 'tipo_tambor' => 'Medio', 'tambor_procedencia' => 'Sede'],
            ['nombre' => 'Yamila Battiato', 'dni' => '33343397', 'fecha_nacimiento' => '1988-03-18', 'telefono' => '1149362706', 'tipo_tambor' => 'Medio', 'tambor_procedencia' => 'Sede'],
        ];

        foreach ($alumnos as $data) {
            $dni = $data['dni'] ?? null;
            $nombre = $data['nombre'];

            $payload = [
                'nombre_apellido' => $nombre,
                'dni' => $dni,
                'fecha_nacimiento' => $data['fecha_nacimiento'],
                'telefono' => $data['telefono'] ?? null,
                'instrumento_principal' => $data['tipo_tambor'] ?? 'Otro',
                'instrumento_secundario' => null,
                'tipo_tambor' => $data['tipo_tambor'] ?? null,
                'tambor_procedencia' => $data['tambor_procedencia'] ?? null,
                'bloque_id' => null,
                'sede_id' => $sede->id,
                'activo' => true,
            ];

            if ($dni !== null && $dni !== '') {
                Alumno::updateOrCreate(
                    ['dni' => $dni],
                    $payload
                );
                continue;
            }

            $yaExiste = Alumno::query()
                ->where('sede_id', $sede->id)
                ->where('nombre_apellido', $nombre)
                ->exists();

            if (!$yaExiste) {
                Alumno::create($payload);
            }
        }
    }
}
