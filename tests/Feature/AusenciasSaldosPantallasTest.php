<?php

namespace Tests\Feature;

use App\Models\Ausencia;
use App\Models\CursoLectivo;
use App\Models\Docente;
use App\Models\FranjaLeccion;
use App\Models\Grupo;
use App\Models\Horario;
use App\Models\Materia;
use App\Models\Movimiento;
use App\Models\User;
use App\Services\SaldoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de las pantallas "Registrar ausencia" y "Usar minutos".
 * Usan una base de datos temporal: no tocan tus datos reales.
 */
class AusenciasSaldosPantallasTest extends TestCase
{
    use RefreshDatabase;

    private CursoLectivo $curso;
    private Docente $falta;   // la especialista que faltó
    private Docente $guia;    // la guía que cubre
    private Horario $horario; // una lección de la que faltó (lunes 07:00)
    private User $directora;

    protected function setUp(): void
    {
        parent::setUp();

        $this->curso = CursoLectivo::create([
            'anio' => 2026, 'fecha_vencimiento' => '2026-12-12', 'cerrado' => false,
        ]);
        $this->falta = Docente::create(['nombre' => 'Especialista Ausente', 'activo' => true]);
        $this->guia = Docente::create(['nombre' => 'Guia Uno', 'activo' => true]);

        $grupo = Grupo::create([
            'curso_lectivo_id' => $this->curso->id, 'nivel' => 1, 'seccion' => 'A', 'guia_id' => $this->guia->id,
        ]);
        $materia = Materia::create(['nombre' => 'Música', 'es_especial' => true]);
        $franja = FranjaLeccion::create([
            'numero' => 1, 'hora_inicio' => '07:00', 'hora_fin' => '07:40', 'es_receso' => false,
        ]);

        $this->horario = Horario::create([
            'docente_id' => $this->falta->id, 'grupo_id' => $grupo->id, 'materia_id' => $materia->id,
            'franja_id' => $franja->id, 'dia_semana' => 1, 'vigente_desde' => '2026-01-01',
        ]);

        $this->directora = User::factory()->create(['rol' => 'directora']);
    }

    private function datosAusencia(array $extra = []): array
    {
        return array_merge([
            'docente_id' => $this->falta->id,
            'fecha'      => '2026-03-02', // es lunes
            'motivo'     => 'Cita médica',
            'cubre'      => [$this->horario->id => $this->guia->id],
        ], $extra);
    }

    public function test_la_pantalla_muestra_las_lecciones_de_ese_dia(): void
    {
        $this->actingAs($this->directora)
            ->get('/ausencias/nueva?docente_id='.$this->falta->id.'&fecha=2026-03-02')
            ->assertOk()
            ->assertSee('Música')
            ->assertSee('07:00');
    }

    public function test_registrar_una_ausencia_suma_40_minutos_a_quien_cubre(): void
    {
        $this->actingAs($this->directora)
            ->post('/ausencias', $this->datosAusencia())
            ->assertRedirect(route('saldos.index'));

        $this->assertSame(1, Ausencia::count());
        $this->assertSame(40, $this->guia->saldoEn($this->curso->id));
    }

    public function test_una_leccion_sin_quien_cubra_no_suma_minutos(): void
    {
        $this->actingAs($this->directora)
            ->post('/ausencias', $this->datosAusencia(['cubre' => [$this->horario->id => '']]))
            ->assertRedirect(route('saldos.index'))
            ->assertSessionHas('exito', fn ($mensaje) => str_contains($mensaje, 'sin quien cubra'));

        $this->assertSame(1, Ausencia::count());
        $this->assertSame(0, Movimiento::count());
    }

    public function test_no_se_puede_registrar_dos_veces_la_misma_ausencia(): void
    {
        $this->actingAs($this->directora)->post('/ausencias', $this->datosAusencia());
        $this->actingAs($this->directora)->post('/ausencias', $this->datosAusencia())
            ->assertSessionHasErrors('docente_id');

        $this->assertSame(1, Ausencia::count());
        $this->assertSame(40, $this->guia->saldoEn($this->curso->id)); // no se sumó dos veces
    }

    public function test_el_motivo_es_obligatorio(): void
    {
        $this->actingAs($this->directora)
            ->post('/ausencias', $this->datosAusencia(['motivo' => '']))
            ->assertSessionHasErrors('motivo');

        $this->assertSame(0, Ausencia::count());
    }

    public function test_la_docente_que_falto_no_puede_cubrirse_a_si_misma(): void
    {
        $this->actingAs($this->directora)
            ->post('/ausencias', $this->datosAusencia(['cubre' => [$this->horario->id => $this->falta->id]]))
            ->assertSessionHasErrors('cubre.'.$this->horario->id);

        $this->assertSame(0, Ausencia::count());
    }

    public function test_un_usuario_de_consulta_no_puede_registrar_ausencias(): void
    {
        $consulta = User::factory()->create(['rol' => 'consulta']);

        $this->actingAs($consulta)->post('/ausencias', $this->datosAusencia())->assertForbidden();
        $this->actingAs($consulta)->get('/ausencias/nueva')->assertForbidden();

        $this->assertSame(0, Ausencia::count());
    }

    public function test_usar_minutos_baja_el_saldo(): void
    {
        (new SaldoService())->ajustar($this->guia, $this->curso->id, 80, '2026-03-02', 'Saldo inicial');

        $this->actingAs($this->directora)
            ->post(route('saldos.usar', $this->guia), ['minutos' => 40, 'fecha' => '2026-03-10'])
            ->assertRedirect(route('saldos.show', $this->guia));

        $this->assertSame(40, $this->guia->saldoEn($this->curso->id));
    }

    public function test_no_se_puede_usar_mas_de_lo_que_hay(): void
    {
        (new SaldoService())->ajustar($this->guia, $this->curso->id, 40, '2026-03-02', 'Saldo inicial');

        $this->actingAs($this->directora)
            ->post(route('saldos.usar', $this->guia), ['minutos' => 500, 'fecha' => '2026-03-10'])
            ->assertSessionHasErrors('minutos');

        $this->assertSame(40, $this->guia->saldoEn($this->curso->id));
    }

    public function test_un_usuario_de_consulta_ve_el_historial_pero_no_puede_usar_minutos(): void
    {
        $consulta = User::factory()->create(['rol' => 'consulta']);

        $this->actingAs($consulta)->get(route('saldos.show', $this->guia))->assertOk();
        $this->actingAs($consulta)
            ->post(route('saldos.usar', $this->guia), ['minutos' => 40, 'fecha' => '2026-03-10'])
            ->assertForbidden();
    }
}
