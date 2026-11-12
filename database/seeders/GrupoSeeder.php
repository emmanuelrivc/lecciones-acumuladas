<?php

namespace Database\Seeders;

use App\Models\CursoLectivo;
use App\Models\Docente;
use App\Models\Grupo;
use Illuminate\Database\Seeder;

class GrupoSeeder extends Seeder
{
    public function run(): void
    {
        $curso = CursoLectivo::where('anio', 2026)->firstOrFail();

        $guias = [
            [1, 'A', 'Ana Lucía Mora Jiménez'],
            [1, 'B', 'Daniela Castro Solís'],
            [2, 'A', 'María José Vargas Rojas'],
            [2, 'B', 'Laura Quesada Brenes'],
            [3, 'A', 'Sofía Herrera Calvo'],
            [3, 'B', 'Valeria Segura Pacheco'],
            [4, 'A', 'Carolina Méndez Ulate'],
            [4, 'B', 'Paola Salas Arce'],
            [5, 'A', 'Natalia Brenes Soto'],
            [5, 'B', 'Gabriela Alfaro Vega'],
            [6, 'A', 'Adriana León Esquivel'],
            [6, 'B', 'Mariana Picado Zúñiga'],
        ];

        foreach ($guias as [$nivel, $seccion, $nombreGuia]) {
            $guia = Docente::where('nombre', $nombreGuia)->firstOrFail();

            Grupo::firstOrCreate(
                [
                    'curso_lectivo_id' => $curso->id,
                    'nivel' => $nivel,
                    'seccion' => $seccion,
                ],
                ['guia_id' => $guia->id]
            );
        }
    }
}
