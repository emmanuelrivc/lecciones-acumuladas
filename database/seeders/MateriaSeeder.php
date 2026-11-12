<?php

namespace Database\Seeders;

use App\Models\Materia; // El conserje de la tabla materias
use Illuminate\Database\Seeder;

class MateriaSeeder extends Seeder
{
    public function run(): void // Laravel ejecuta esto al correr el seeder
    {
        // Lista de materias que imparten especialistas
        $materias = [
            'Música',
            'Artes Industriales',
            'Educación Religiosa',
            'Educación Física',
            'Hogar',
            'Artes Plásticas',
            'Inglés',
            'Informática',
        ];

        // Una vuelta por cada nombre de la lista
        foreach ($materias as $nombre) {
            // Busca la materia por nombre; si no existe, la crea
            Materia::firstOrCreate(
                ['nombre' => $nombre],      // QUÉ BUSCAR
                ['es_especial' => true]     // DATO EXTRA, solo si se crea
            );
        }
    }
}
