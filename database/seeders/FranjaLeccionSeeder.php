<?php

namespace Database\Seeders;

use App\Models\FranjaLeccion;
use Illuminate\Database\Seeder;

class FranjaLeccionSeeder extends Seeder
{
    public function run(): void
    {
        // Las 9 lecciones del día: [número, hora de inicio, hora de fin]
        // Los recesos (8:20, 9:55, 11:30 y 1:30) no se guardan: no son lecciones
        $franjas = [
            [1, '07:00', '07:40'],
            [2, '07:40', '08:20'],
            [3, '08:35', '09:15'],
            [4, '09:15', '09:55'],
            [5, '10:10', '10:50'],
            [6, '10:50', '11:30'],
            [7, '12:10', '12:50'],
            [8, '12:50', '13:30'],
            [9, '13:40', '14:20'],
        ];

        foreach ($franjas as [$numero, $inicio, $fin]) {
            FranjaLeccion::firstOrCreate(
                ['numero' => $numero],                                  // QUÉ BUSCAR
                ['hora_inicio' => $inicio, 'hora_fin' => $fin, 'es_receso' => false] // DATOS EXTRA
            );
        }
    }
}
