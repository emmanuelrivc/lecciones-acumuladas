<?php

namespace Database\Seeders;

use App\Models\CursoLectivo;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Carga TODOS los datos de la escuela, en el orden correcto.
    // Se usa al crear el paquete: php artisan migrate:fresh --seed
    public function run(): void
    {
        // El curso lectivo 2026 (vence el 12 de diciembre)
        CursoLectivo::firstOrCreate(
            ['anio' => 2026],
            ['fecha_vencimiento' => '2026-12-12', 'cerrado' => false]
        );

        $this->call([
            MateriaSeeder::class,
            FranjaLeccionSeeder::class,
            DocenteSeeder::class,
            GrupoSeeder::class,
            HorarioSeeder::class,
            AsignacionMateriaSeeder::class,
        ]);
    }
}
