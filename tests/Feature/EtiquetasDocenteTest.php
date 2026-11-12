<?php

namespace Tests\Feature;

use App\Models\AsignacionMateria;
use App\Models\CursoLectivo;
use App\Models\Docente;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EtiquetasDocenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_lista_de_docentes_muestra_su_materia_o_su_grupo(): void
    {
        $curso = CursoLectivo::create(['anio' => 2026, 'fecha_vencimiento' => '2026-12-12', 'cerrado' => false]);
        $carla = Docente::create(['nombre' => 'Carla Prueba', 'activo' => true]);
        $rosa = Docente::create(['nombre' => 'Rosa Guia', 'activo' => true]);
        $ingles = Materia::create(['nombre' => 'Inglés', 'es_especial' => true]);
        AsignacionMateria::create(['docente_id' => $carla->id, 'materia_id' => $ingles->id, 'curso_lectivo_id' => $curso->id, 'nivel' => 2]);
        Grupo::create(['curso_lectivo_id' => $curso->id, 'nivel' => 2, 'seccion' => 'B', 'guia_id' => $rosa->id]);

        $this->actingAs(User::factory()->create(['rol' => 'directora']))
            ->get('/ausencias/nueva')
            ->assertOk()
            ->assertSee('Carla Prueba · Inglés')
            ->assertSee('Rosa Guia · Guía 2B');
    }

    public function test_las_especialistas_salen_primero_y_las_guias_abajo(): void
    {
        $curso = CursoLectivo::create(['anio' => 2026, 'fecha_vencimiento' => '2026-12-12', 'cerrado' => false]);
        // La guía empieza con A y la especialista con Z: sin agrupar, la guía saldría primero
        $guia = Docente::create(['nombre' => 'Ana Guia', 'activo' => true]);
        $esp = Docente::create(['nombre' => 'Zoe Especial', 'activo' => true]);
        $ingles = Materia::create(['nombre' => 'Inglés', 'es_especial' => true]);
        AsignacionMateria::create(['docente_id' => $esp->id, 'materia_id' => $ingles->id, 'curso_lectivo_id' => $curso->id, 'nivel' => 4]);
        AsignacionMateria::create(['docente_id' => $esp->id, 'materia_id' => $ingles->id, 'curso_lectivo_id' => $curso->id, 'nivel' => 5]);
        AsignacionMateria::create(['docente_id' => $esp->id, 'materia_id' => $ingles->id, 'curso_lectivo_id' => $curso->id, 'nivel' => 6]);
        Grupo::create(['curso_lectivo_id' => $curso->id, 'nivel' => 1, 'seccion' => 'A', 'guia_id' => $guia->id]);

        $this->actingAs(User::factory()->create(['rol' => 'directora']))
            ->get('/ausencias/nueva')
            ->assertOk()
            ->assertSeeInOrder(['Zoe Especial', 'Ana Guia'])
            ->assertSee('Inglés (4° a 6°)')
            ->assertSee('Docentes guía');
    }
}
