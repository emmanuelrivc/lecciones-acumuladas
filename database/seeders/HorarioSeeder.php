<?php

namespace Database\Seeders;

use App\Models\CursoLectivo;
use App\Models\Docente;
use App\Models\FranjaLeccion;
use App\Models\Grupo;
use App\Models\Horario;
use App\Models\Materia;
use Illuminate\Database\Seeder;

class HorarioSeeder extends Seeder
{
    public function run(): void
    {
        $curso = CursoLectivo::where('anio', 2026)->firstOrFail();

        // Fecha de referencia desde la cual rige este horario (no afecta los cálculos)
        $desde = '2026-01-01';

        // Diccionario de grupos: '1A' => fila del grupo Primero A, '4B' => Cuarto B...
        $grupos = Grupo::where('curso_lectivo_id', $curso->id)
            ->get()
            ->keyBy(fn ($g) => $g->nivel . $g->seccion);

        // Diccionario de franjas: número de lección => id de la franja
        $franjas = FranjaLeccion::pluck('id', 'numero');

        // Cada bloque: [docente, materia, [día => [lección => grupo]]]
        // Días: 1 = lunes ... 5 = viernes. Grupo: nivel + sección ('2A' = Segundo A)
        // Nota: Artes Industriales y Hogar coinciden con el mismo grupo (el grupo se divide en dos mitades);
        // cada docente cubre la lección completa
        $horarios = [
            ['Elena Campos Barrantes', 'Música', [
                1 => [1 => '6A', 2 => '6A', 3 => '3B', 4 => '3B', 5 => '6B', 6 => '6B', 7 => '4A', 8 => '4A'], // Lunes
                3 => [1 => '1B', 2 => '1B', 3 => '2B', 4 => '2B', 5 => '2A', 6 => '2A', 7 => '1A', 8 => '1A'], // Miércoles
                4 => [1 => '4B', 2 => '4B', 3 => '3A', 4 => '3A', 5 => '5B', 6 => '5B', 7 => '5A', 8 => '5A'], // Jueves
            ]],
            ['Silvia Arguedas Lobo', 'Artes Industriales', [
                2 => [1 => '2A', 2 => '2A', 3 => '2B', 4 => '2B', 5 => '1A', 6 => '1A', 7 => '3A', 8 => '3A', 9 => '1B'], // Martes
                3 => [1 => '5A', 2 => '5A', 3 => '6B', 4 => '6B', 5 => '6A', 6 => '6A', 7 => '4B', 8 => '4B'], // Miércoles
                5 => [1 => '5B', 2 => '5B', 3 => '4A', 4 => '4A', 5 => '3B', 6 => '3B', 7 => '1B'], // Viernes
            ]],
            ['Lucía Badilla Navarro', 'Hogar', [
                2 => [1 => '2A', 2 => '2A', 3 => '2B', 4 => '2B', 5 => '1A', 6 => '1A', 7 => '3A', 8 => '3A', 9 => '1B'], // Martes
                3 => [1 => '5A', 2 => '5A', 3 => '6B', 4 => '6B', 5 => '6A', 6 => '6A', 7 => '5B', 8 => '5B'], // Miércoles
                5 => [1 => '4B', 2 => '4B', 3 => '4A', 4 => '4A', 5 => '3B', 6 => '3B', 7 => '1B'], // Viernes
            ]],
            ['Diego Granados Alpízar', 'Artes Plásticas', [
                2 => [1 => '3B', 2 => '3B', 3 => '6B', 4 => '6B', 5 => '2A', 6 => '2A', 7 => '2B', 8 => '2B'], // Martes
                3 => [1 => '6A', 2 => '6A', 3 => '5B', 4 => '5B', 5 => '5A', 6 => '5A'], // Miércoles
                4 => [5 => '1B', 6 => '1B', 7 => '3A', 8 => '3A'], // Jueves
                5 => [1 => '4A', 2 => '4A', 3 => '4B', 4 => '4B', 5 => '1A', 6 => '1A'], // Viernes
            ]],
            ['Andrea Montoya Chacón', 'Educación Física', [
                1 => [1 => '1B', 2 => '1B', 3 => '2B', 4 => '2B', 5 => '1A', 6 => '1A', 7 => '2A', 8 => '2A'], // Lunes
                2 => [1 => '3A', 2 => '3A', 3 => '4B', 4 => '4B', 5 => '5A', 6 => '5A', 7 => '5B', 8 => '5B'], // Martes
                4 => [1 => '4A', 2 => '4A', 3 => '3B', 4 => '3B', 5 => '6A', 6 => '6A', 7 => '6B', 8 => '6B'], // Jueves
            ]],
            ['Patricia Umaña Robles', 'Educación Religiosa', [
                1 => [1 => '4A', 2 => '4A', 3 => '4B', 4 => '4B', 5 => '6A', 6 => '6A', 7 => '6B', 8 => '6B'], // Lunes
                2 => [1 => '5B', 2 => '5B', 3 => '3B', 4 => '3B', 5 => '3A', 6 => '3A', 7 => '5A', 8 => '5A'], // Martes
                4 => [1 => '2B', 2 => '2B', 3 => '1A', 4 => '1A', 5 => '2A', 6 => '2A', 7 => '1B', 8 => '1B'], // Jueves
            ]],
            ['Fernanda Ruiz Madrigal', 'Inglés', [
                4 => [1 => '1A', 2 => '1A', 3 => '1B', 4 => '1B'], // Jueves
                5 => [1 => '1A', 2 => '1A', 3 => '1A', 4 => '1B', 5 => '1B', 6 => '1B'], // Viernes
            ]],
            ['Isabel Porras Aguilar', 'Inglés', [
                1 => [2 => '2A', 3 => '2A', 4 => '2A', 5 => '2B', 6 => '2B'], // Lunes
                5 => [2 => '2B', 3 => '2B', 4 => '2B', 5 => '2A', 6 => '2A'], // Viernes
            ]],
            ['Verónica Sibaja Durán', 'Inglés', [
                1 => [3 => '3A', 4 => '3A', 5 => '3A', 6 => '3B', 7 => '3B', 8 => '3B'], // Lunes
                3 => [3 => '3A', 4 => '3A', 5 => '3B', 6 => '3B'], // Miércoles
            ]],
            ['Camila Torres Villegas', 'Inglés', [
                1 => [3 => '5A', 4 => '5A', 6 => '5B', 7 => '5B', 8 => '5B', 9 => '4A'], // Lunes
                2 => [1 => '6B', 2 => '6B', 4 => '6A', 5 => '4B', 6 => '4B', 7 => '6A', 8 => '6A', 9 => '4A'], // Martes
                3 => [1 => '4B', 2 => '4B', 5 => '4A', 6 => '4A', 7 => '5A', 8 => '6B', 9 => '5B'], // Miércoles
                4 => [1 => '6A', 2 => '6A', 3 => '5A', 4 => '5A', 5 => '6B', 6 => '6B', 7 => '5B', 8 => '4B', 9 => '4A'], // Jueves
            ]],
            ['Mónica Bonilla Ortiz', 'Informática', [
                3 => [1 => '6B', 2 => '6B', 3 => '6A', 4 => '6A', 5 => '4B', 6 => '4B'], // Miércoles
                4 => [1 => '5B', 2 => '5B', 3 => '4A', 4 => '4A', 5 => '5A', 6 => '5A'], // Jueves
            ]],
            ['Tamara Céspedes Fonseca', 'Informática', [
                4 => [1 => '1B', 2 => '1B', 5 => '1A', 6 => '1A', 7 => '2A', 8 => '2A'], // Jueves
                5 => [1 => '3B', 2 => '3B', 3 => '3A', 4 => '3A', 5 => '2B', 6 => '2B'], // Viernes
            ]],
        ];

        foreach ($horarios as [$nombreDocente, $nombreMateria, $dias]) {
            $docente = Docente::where('nombre', $nombreDocente)->firstOrFail();
            $materia = Materia::where('nombre', $nombreMateria)->firstOrFail();

            foreach ($dias as $diaSemana => $lecciones) {
                foreach ($lecciones as $numeroLeccion => $codigoGrupo) {
                    Horario::firstOrCreate(
                        [
                            'docente_id'  => $docente->id,
                            'grupo_id'    => $grupos[$codigoGrupo]->id,
                            'materia_id'  => $materia->id,
                            'franja_id'   => $franjas[$numeroLeccion],
                            'dia_semana'  => $diaSemana,
                        ],
                        ['vigente_desde' => $desde]
                    );
                }
            }
        }
    }
}
