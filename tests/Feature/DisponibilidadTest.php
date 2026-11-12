<?php

namespace Tests\Feature;

use App\Models\Ausencia;
use App\Models\AusenciaLeccion;
use App\Models\CursoLectivo;
use App\Models\Docente;
use App\Models\FranjaLeccion;
use App\Models\Grupo;
use App\Models\Horario;
use App\Models\Materia;
use App\Models\User;
use App\Services\DisponibilidadService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisponibilidadTest extends TestCase
{
    use RefreshDatabase;

    private const LUNES = '2026-03-02';

    private User $directora;
    private CursoLectivo $curso;
    private FranjaLeccion $franja1;
    private FranjaLeccion $franja2;
    private Docente $ana, $bea, $carla, $dora, $elena, $fiona;
    private Grupo $g4a, $g4b;
    private Materia $musica;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directora = User::factory()->create(['rol' => 'directora']);
        $this->curso = CursoLectivo::create(['anio' => 2026, 'fecha_vencimiento' => '2026-12-12', 'cerrado' => false]);
        $this->franja1 = FranjaLeccion::create(['numero' => 1, 'hora_inicio' => '07:00', 'hora_fin' => '07:40', 'es_receso' => false]);
        $this->franja2 = FranjaLeccion::create(['numero' => 2, 'hora_inicio' => '07:40', 'hora_fin' => '08:20', 'es_receso' => false]);

        // Ana (especialista) falta. Bea (especialista) da clase.
        $this->ana = Docente::create(['nombre' => 'Ana', 'activo' => true]);
        $this->bea = Docente::create(['nombre' => 'Bea', 'activo' => true]);
        // Las dos guías de 4°: Carla (4A) y Fiona (4B); ambas dan clase en las dos secciones
        $this->carla = Docente::create(['nombre' => 'Carla', 'activo' => true]);
        $this->fiona = Docente::create(['nombre' => 'Fiona', 'activo' => true]);
        // Dora es la guía de 5A (único grupo de su nivel en esta prueba)
        $this->dora = Docente::create(['nombre' => 'Dora', 'activo' => true]);
        // Elena no es guía ni da clase
        $this->elena = Docente::create(['nombre' => 'Elena', 'activo' => true]);

        $this->g4a = Grupo::create(['curso_lectivo_id' => $this->curso->id, 'nivel' => 4, 'seccion' => 'A', 'guia_id' => $this->carla->id]);
        $this->g4b = Grupo::create(['curso_lectivo_id' => $this->curso->id, 'nivel' => 4, 'seccion' => 'B', 'guia_id' => $this->fiona->id]);
        Grupo::create(['curso_lectivo_id' => $this->curso->id, 'nivel' => 5, 'seccion' => 'A', 'guia_id' => $this->dora->id]);

        $this->musica = Materia::create(['nombre' => 'Música', 'es_especial' => true]);
        $ingles = Materia::create(['nombre' => 'Inglés', 'es_especial' => true]);

        // Franja 1: las DOS secciones de 4° están con especialistas (Ana y Bea)
        $this->clase($this->ana, $this->g4a, $this->musica, $this->franja1);
        $this->clase($this->bea, $this->g4b, $ingles, $this->franja1);

        // Franja 2: solo 4A está con una especialista (Ana); 4B sigue con sus guías
        $this->clase($this->ana, $this->g4a, $this->musica, $this->franja2);
    }

    private function clase(Docente $d, Grupo $g, Materia $m, FranjaLeccion $f): Horario
    {
        return Horario::create([
            'docente_id' => $d->id, 'grupo_id' => $g->id, 'materia_id' => $m->id,
            'franja_id' => $f->id, 'dia_semana' => 1, 'vigente_desde' => '2026-01-01',
        ]);
    }

    /** Resultado de disponibilidad para la lección de Ana en la franja dada */
    private function resultado(FranjaLeccion $franja): array
    {
        $docentes = Docente::where('activo', true)->get();
        $lecciones = Horario::where('docente_id', $this->ana->id)->get();
        $leccion = $lecciones->firstWhere('franja_id', $franja->id);

        return (new DisponibilidadService())
            ->libres(Carbon::parse(self::LUNES), $this->ana, $lecciones, $docentes)[$leccion->id];
    }

    private function ausenciaDe(Docente $d): Ausencia
    {
        return Ausencia::create([
            'docente_id' => $d->id, 'curso_lectivo_id' => $this->curso->id, 'registrada_por' => $this->directora->id,
            'fecha' => self::LUNES, 'motivo' => 'Cita', 'estado' => 'activa',
        ]);
    }

    public function test_si_las_dos_secciones_tienen_especialista_las_dos_guias_estan_libres(): void
    {
        $r = $this->resultado($this->franja1);

        $this->assertEqualsCanonicalizing([$this->carla->id, $this->fiona->id, $this->elena->id], $r['libres']);
        $this->assertSame([], $r['quiza']);
        $this->assertNotContains($this->bea->id, $r['libres']);   // da una lección de especialidad
        $this->assertNotContains($this->dora->id, $r['libres']);  // única guía de su nivel, con su grupo
        $this->assertNotContains($this->ana->id, $r['libres']);   // es la que faltó
    }

    public function test_si_solo_una_seccion_tiene_especialista_sobra_una_guia_pero_no_se_sabe_cual(): void
    {
        $r = $this->resultado($this->franja2);

        // Una de las dos guías atiende la sección B; la otra está libre, pero no sabemos cuál
        $this->assertEqualsCanonicalizing([$this->carla->id, $this->fiona->id], $r['quiza']);
        // Bea no da clase en la franja 2 y Elena tampoco: están libres seguro
        $this->assertEqualsCanonicalizing([$this->bea->id, $this->elena->id], $r['libres']);
    }

    public function test_si_una_guia_del_nivel_falta_la_que_queda_esta_ocupada(): void
    {
        $this->ausenciaDe($this->fiona);

        $r = $this->resultado($this->franja2);

        $this->assertNotContains($this->carla->id, $r['libres']);
        $this->assertNotContains($this->carla->id, $r['quiza']);
    }

    public function test_quien_ya_cubre_otra_leccion_a_esa_hora_no_esta_libre(): void
    {
        $otra = Docente::create(['nombre' => 'Otra', 'activo' => true]);
        $ausencia = $this->ausenciaDe($otra);
        AusenciaLeccion::create([
            'ausencia_id' => $ausencia->id, 'grupo_id' => $this->g4a->id, 'cubre_docente_id' => $this->elena->id,
            'fecha' => self::LUNES, 'franja_id' => $this->franja1->id, 'minutos' => 40,
        ]);

        $this->assertNotContains($this->elena->id, $this->resultado($this->franja1)['libres']);
    }

    public function test_quien_tiene_ausencia_ese_dia_no_esta_libre(): void
    {
        $this->ausenciaDe($this->elena);

        $this->assertNotContains($this->elena->id, $this->resultado($this->franja1)['libres']);
    }

    public function test_la_pantalla_separa_disponibles_de_ocupadas(): void
    {
        $this->actingAs($this->directora)
            ->get('/ausencias/nueva?docente_id='.$this->ana->id.'&fecha='.self::LUNES)
            ->assertOk()
            ->assertSee('Disponibles en esta lección')
            ->assertSee('Quizá disponibles')
            ->assertSee('Ocupadas a esa hora')
            ->assertSee('Elena');
    }

    public function test_la_guia_del_grupo_sale_primero_en_su_propio_bloque(): void
    {
        $this->actingAs($this->directora)
            ->get('/ausencias/nueva?docente_id='.$this->ana->id.'&fecha='.self::LUNES)
            ->assertOk()
            ->assertSee('Guía del grupo 4A')
            ->assertSee('Carla (');
    }
}
