<?php

namespace Database\Seeders;

use App\Models\AsignacionMateria;
use App\Models\CursoLectivo;
use App\Models\Docente;
use App\Models\Materia;
use Illuminate\Database\Seeder;

class AsignacionMateriaSeeder extends Seeder
{
    public function run(): void
    {
        // El curso lectivo al que pertenecen estas asignaciones
        $curso = CursoLectivo::where('anio', 2026)->firstOrFail();

        // Cada renglón: [docente, materia, [niveles que atiende]]
        $asignaciones = [
            ['Elena Campos Barrantes', 'Música',              [1, 2, 3, 4, 5, 6]],
            ['Silvia Arguedas Lobo',     'Artes Industriales',  [1, 2, 3, 4, 5, 6]],
            ['Patricia Umaña Robles',   'Educación Religiosa', [1, 2, 3, 4, 5, 6]],
            ['Andrea Montoya Chacón',      'Educación Física',    [1, 2, 3, 4, 5, 6]],
            ['Lucía Badilla Navarro',    'Hogar',               [1, 2, 3, 4, 5, 6]],
            ['Diego Granados Alpízar',     'Artes Plásticas',     [1, 2, 3, 4, 5, 6]],
            ['Fernanda Ruiz Madrigal',        'Inglés',              [1]],
            ['Isabel Porras Aguilar',        'Inglés',              [2]],
            ['Verónica Sibaja Durán',         'Inglés',              [3]],
            ['Camila Torres Villegas',     'Inglés',              [4, 5, 6]],
            ['Mónica Bonilla Ortiz',       'Informática',         [4, 5, 6]],
            ['Tamara Céspedes Fonseca',    'Informática',         [1, 2, 3]],
        ];

        // Ciclo de afuera: una vuelta por cada renglón de la lista
        foreach ($asignaciones as [$nombreDocente, $nombreMateria, $niveles]) {

            // Buscamos las filas reales para obtener sus id
            $docente = Docente::where('nombre', $nombreDocente)->firstOrFail();
            $materia = Materia::where('nombre', $nombreMateria)->firstOrFail();

            // Ciclo de adentro: una vuelta por cada nivel de ese renglón
            foreach ($niveles as $nivel) {
                AsignacionMateria::firstOrCreate([
                    'docente_id'       => $docente->id,
                    'materia_id'       => $materia->id,
                    'curso_lectivo_id' => $curso->id,
                    'nivel'            => $nivel,
                ]);
            }
        }
    }
}