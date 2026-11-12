<?php

namespace Tests\Feature;

use App\Models\AsignacionMateria;
use App\Models\CursoLectivo;
use App\Models\Docente;
use App\Models\FranjaLeccion;
use App\Models\Grupo;
use App\Models\Horario;
use App\Models\Materia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalHorarioTest extends TestCase
{
    use RefreshDatabase;

    private User $directora;
    private CursoLectivo $curso;
    private Materia $ingles;
    private Grupo $g4a;
    private Grupo $g5a;
    private FranjaLeccion $f1;
    private FranjaLeccion $f2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directora = User::factory()->create(['rol' => 'directora']);
        $this->curso = CursoLectivo::create(['anio' => 2026, 'fecha_vencimiento' => '2026-12-12', 'cerrado' => false]);
        $this->ingles = Materia::create(['nombre' => 'Inglés', 'es_especial' => true]);
        $this->f1 = FranjaLeccion::create(['numero' => 1, 'hora_inicio' => '07:00', 'hora_fin' => '07:40', 'es_receso' => false]);
        $this->f2 = FranjaLeccion::create(['numero' => 2, 'hora_inicio' => '07:40', 'hora_fin' => '08:20', 'es_receso' => false]);

        $guia = Docente::create(['nombre' => 'Guía Actual', 'activo' => true]);
        $this->g4a = Grupo::create(['curso_lectivo_id' => $this->curso->id, 'nivel' => 4, 'seccion' => 'A', 'guia_id' => $guia->id]);
        $this->g5a = Grupo::create(['curso_lectivo_id' => $this->curso->id, 'nivel' => 5, 'seccion' => 'A', 'guia_id' => $guia->id]);
    }

    private function especialista(array $extra = []): array
    {
        return array_merge([
            'nombre'     => 'Docente Nueva',
            'tipo'       => 'especialista',
            'materia_id' => $this->ingles->id,
            'horario'    => [
                1 => [$this->f1->id => $this->g4a->id, $this->f2->id => $this->g5a->id],
                3 => [$this->f1->id => $this->g4a->id],
            ],
        ], $extra);
    }

    public function test_una_especialista_se_guarda_con_su_materia_y_su_horario(): void
    {
        $this->actingAs($this->directora)->post('/personal', $this->especialista())
            ->assertRedirect(route('personal.index'));

        $d = Docente::where('nombre', 'Docente Nueva')->firstOrFail();
        $this->assertTrue($d->activo);
        $this->assertSame(3, Horario::where('docente_id', $d->id)->count());
        $this->assertEqualsCanonicalizing(
            [4, 5],
            AsignacionMateria::where('docente_id', $d->id)->pluck('nivel')->all()
        );
        $this->assertTrue(Horario::where('docente_id', $d->id)->where('dia_semana', 3)->where('grupo_id', $this->g4a->id)->exists());
    }

    public function test_la_especialista_debe_traer_materia(): void
    {
        $this->actingAs($this->directora)->post('/personal', $this->especialista(['materia_id' => null]))
            ->assertSessionHasErrors('materia_id');

        $this->assertSame(0, Docente::where('nombre', 'Docente Nueva')->count());
    }

    public function test_la_especialista_debe_traer_al_menos_una_leccion(): void
    {
        $this->actingAs($this->directora)->post('/personal', $this->especialista(['horario' => [1 => [$this->f1->id => '']]]))
            ->assertSessionHasErrors('horario');

        $this->assertSame(0, Docente::where('nombre', 'Docente Nueva')->count());
    }

    public function test_hay_que_decir_si_es_especialista_o_guia(): void
    {
        $this->actingAs($this->directora)->post('/personal', ['nombre' => 'Docente Nueva'])
            ->assertSessionHasErrors('tipo');
    }

    public function test_un_grupo_no_puede_tener_dos_especialistas_a_la_misma_hora(): void
    {
        $otra = Docente::create(['nombre' => 'Otra Activa', 'activo' => true]);
        Horario::create([
            'docente_id' => $otra->id, 'grupo_id' => $this->g4a->id, 'materia_id' => $this->ingles->id,
            'franja_id' => $this->f1->id, 'dia_semana' => 1, 'vigente_desde' => '2026-01-01',
        ]);

        $this->actingAs($this->directora)->post('/personal', $this->especialista())
            ->assertSessionHasErrors('horario');

        $this->assertSame(0, Docente::where('nombre', 'Docente Nueva')->count()); // no se guardó nada
    }

    public function test_si_la_anterior_ya_salio_su_leccion_pasa_a_la_nueva(): void
    {
        $vieja = Docente::create(['nombre' => 'Ya Salio', 'activo' => false]);
        $horarioViejo = Horario::create([
            'docente_id' => $vieja->id, 'grupo_id' => $this->g4a->id, 'materia_id' => $this->ingles->id,
            'franja_id' => $this->f1->id, 'dia_semana' => 1, 'vigente_desde' => '2026-01-01',
        ]);

        $this->actingAs($this->directora)->post('/personal', $this->especialista())
            ->assertRedirect(route('personal.index'));

        $this->assertNotNull($horarioViejo->fresh()->vigente_hasta);
        $this->assertSame(3, Horario::where('docente_id', Docente::where('nombre', 'Docente Nueva')->value('id'))->count());
    }

    public function test_una_guia_se_asigna_a_un_grupo_sin_guia_activa(): void
    {
        $sale = Docente::create(['nombre' => 'Guía Que Salió', 'activo' => false]);
        $vacante = Grupo::create(['curso_lectivo_id' => $this->curso->id, 'nivel' => 6, 'seccion' => 'A', 'guia_id' => $sale->id]);

        $this->actingAs($this->directora)
            ->post('/personal', ['nombre' => 'Guía Nueva', 'tipo' => 'guia', 'grupo_id' => $vacante->id])
            ->assertRedirect(route('personal.index'));

        $this->assertSame('Guía Nueva', $vacante->fresh()->guia->nombre);
    }

    public function test_no_se_puede_poner_guia_a_un_grupo_que_ya_tiene_una_activa(): void
    {
        $this->actingAs($this->directora)
            ->post('/personal', ['nombre' => 'Guía Nueva', 'tipo' => 'guia', 'grupo_id' => $this->g4a->id])
            ->assertSessionHasErrors('grupo_id');

        $this->assertSame(0, Docente::where('nombre', 'Guía Nueva')->count());
    }

    public function test_la_pantalla_muestra_el_formulario_con_materias_y_grupos(): void
    {
        $this->actingAs($this->directora)->get('/personal')
            ->assertOk()
            ->assertSee('Especialista')
            ->assertSee('Guía de un grupo')
            ->assertSee('Inglés')
            ->assertSee('4A');
    }
}
