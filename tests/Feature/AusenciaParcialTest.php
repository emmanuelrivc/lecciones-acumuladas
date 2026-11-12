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

class AusenciaParcialTest extends TestCase
{
    use RefreshDatabase;

    private const LUNES = '2026-03-02';

    private User $directora;
    private CursoLectivo $curso;
    private Docente $carla;
    private Docente $rosa;
    private Horario $h1;
    private Horario $h2;
    private Horario $h3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directora = User::factory()->create(['rol' => 'directora']);
        $this->curso = CursoLectivo::create(['anio' => 2026, 'fecha_vencimiento' => '2026-12-12', 'cerrado' => false]);
        $this->carla = Docente::create(['nombre' => 'Carla', 'activo' => true]);
        $this->rosa = Docente::create(['nombre' => 'Rosa', 'activo' => true]);
        $grupo = Grupo::create(['curso_lectivo_id' => $this->curso->id, 'nivel' => 2, 'seccion' => 'A', 'guia_id' => $this->rosa->id]);
        $ingles = Materia::create(['nombre' => 'Inglés', 'es_especial' => true]);

        $f1 = FranjaLeccion::create(['numero' => 1, 'hora_inicio' => '07:00', 'hora_fin' => '07:40', 'es_receso' => false]);
        $f2 = FranjaLeccion::create(['numero' => 2, 'hora_inicio' => '07:40', 'hora_fin' => '08:20', 'es_receso' => false]);
        $f3 = FranjaLeccion::create(['numero' => 3, 'hora_inicio' => '08:35', 'hora_fin' => '09:15', 'es_receso' => false]);

        $this->h1 = $this->clase($grupo, $ingles, $f1);
        $this->h2 = $this->clase($grupo, $ingles, $f2);
        $this->h3 = $this->clase($grupo, $ingles, $f3);
    }

    private function clase(Grupo $g, Materia $m, FranjaLeccion $f): Horario
    {
        return Horario::create([
            'docente_id' => $this->carla->id, 'grupo_id' => $g->id, 'materia_id' => $m->id,
            'franja_id' => $f->id, 'dia_semana' => 1, 'vigente_desde' => '2026-01-01',
        ]);
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'docente_id' => $this->carla->id,
            'fecha'      => self::LUNES,
            'motivo'     => 'Cita médica',
            'parcial'    => 1,
            'faltas'     => [$this->h1->id, $this->h2->id],
            'cubre'      => [$this->h1->id => $this->rosa->id, $this->h2->id => $this->rosa->id],
        ], $extra);
    }

    public function test_solo_cuentan_las_lecciones_marcadas(): void
    {
        $this->actingAs($this->directora)->post('/ausencias', $this->datos())->assertRedirect();

        $ausencia = Ausencia::first();
        $this->assertSame(2, AusenciaLeccion::count());
        $this->assertSame(80, $this->rosa->saldoEn($this->curso->id));
        $this->assertEqualsCanonicalizing(
            [$this->h1->franja_id, $this->h2->franja_id],
            $ausencia->franjas_faltadas
        );
    }

    public function test_una_leccion_desmarcada_no_suma_aunque_traiga_quien_cubre(): void
    {
        $this->actingAs($this->directora)->post('/ausencias', $this->datos([
            'faltas' => [$this->h1->id],
            'cubre'  => [$this->h1->id => $this->rosa->id, $this->h3->id => $this->rosa->id],
        ]));

        $this->assertSame(1, AusenciaLeccion::count());
        $this->assertSame(40, $this->rosa->saldoEn($this->curso->id));
    }

    public function test_hay_que_marcar_al_menos_una_leccion(): void
    {
        $this->actingAs($this->directora)
            ->post('/ausencias', $this->datos(['faltas' => [], 'cubre' => []]))
            ->assertSessionHasErrors('faltas');

        $this->assertSame(0, Ausencia::count());
    }

    public function test_no_se_aceptan_lecciones_de_otra_docente(): void
    {
        $otroGrupo = Grupo::create(['curso_lectivo_id' => $this->curso->id, 'nivel' => 2, 'seccion' => 'B', 'guia_id' => $this->rosa->id]);
        $ajena = Horario::create([
            'docente_id' => $this->rosa->id, 'grupo_id' => $otroGrupo->id, 'materia_id' => $this->h1->materia_id,
            'franja_id' => $this->h1->franja_id, 'dia_semana' => 1, 'vigente_desde' => '2026-01-01',
        ]);

        $this->actingAs($this->directora)
            ->post('/ausencias', $this->datos(['faltas' => [$ajena->id], 'cubre' => []]))
            ->assertSessionHasErrors('faltas');
    }

    /** Quiénes están libres para las lecciones de Carla (un lunes) */
    private function libresParaCarla(): array
    {
        $lecciones = Horario::where('docente_id', $this->carla->id)->get();
        $docentes = Docente::where('activo', true)->get();

        return (new DisponibilidadService())->libres(Carbon::parse(self::LUNES), $this->carla, $lecciones, $docentes);
    }

    private function ausenciaDe(Docente $d, ?array $franjas): void
    {
        Ausencia::create([
            'docente_id' => $d->id, 'curso_lectivo_id' => $this->curso->id, 'registrada_por' => $this->directora->id,
            'fecha' => self::LUNES, 'motivo' => 'Cita', 'estado' => 'activa', 'franjas_faltadas' => $franjas,
        ]);
    }

    public function test_quien_falto_solo_unas_lecciones_esta_disponible_en_las_demas(): void
    {
        $tere = Docente::create(['nombre' => 'Tere', 'activo' => true]);
        $this->ausenciaDe($tere, [$this->h1->franja_id]); // Tere falta solo a la primera lección

        $res = $this->libresParaCarla();

        $this->assertNotContains($tere->id, $res[$this->h1->id]['libres']); // faltó a esa hora
        $this->assertContains($tere->id, $res[$this->h3->id]['libres']);    // a esta hora sí vino
    }

    public function test_quien_falto_todo_el_dia_no_esta_disponible_en_ninguna_hora(): void
    {
        $tere = Docente::create(['nombre' => 'Tere', 'activo' => true]);
        $this->ausenciaDe($tere, null); // sin lista = todo el día

        $res = $this->libresParaCarla();

        $this->assertNotContains($tere->id, $res[$this->h1->id]['libres']);
        $this->assertNotContains($tere->id, $res[$this->h3->id]['libres']);
    }

    public function test_la_pantalla_muestra_las_casillas_para_marcar(): void
    {
        $this->actingAs($this->directora)
            ->get('/ausencias/nueva?docente_id='.$this->carla->id.'&fecha='.self::LUNES)
            ->assertOk()
            ->assertSee('name="faltas[]"', false)
            ->assertSee('name="parcial"', false);
    }
}
