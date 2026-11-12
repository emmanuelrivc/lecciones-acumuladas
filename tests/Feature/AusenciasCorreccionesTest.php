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
 * Pruebas de las correcciones: anular ausencia, ajuste manual y revertir un movimiento.
 */
class AusenciasCorreccionesTest extends TestCase
{
    use RefreshDatabase;

    private CursoLectivo $curso;
    private Docente $falta;
    private Docente $guia;
    private Horario $horario;
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

    private function registrarAusencia(): Ausencia
    {
        $this->actingAs($this->directora)->post('/ausencias', [
            'docente_id' => $this->falta->id,
            'fecha'      => '2026-03-02',
            'motivo'     => 'Cita médica',
            'cubre'      => [$this->horario->id => $this->guia->id],
        ]);

        return Ausencia::firstOrFail();
    }

    private function saldo(): int
    {
        return $this->guia->saldoEn($this->curso->id);
    }

    // ---------- Anular ausencia ----------

    public function test_la_lista_muestra_las_ausencias_registradas(): void
    {
        $this->registrarAusencia();

        $this->actingAs($this->directora)
            ->get('/ausencias')
            ->assertOk()
            ->assertSee('Especialista Ausente')
            ->assertSee('Cita médica');
    }

    public function test_anular_una_ausencia_revierte_los_minutos(): void
    {
        $ausencia = $this->registrarAusencia();
        $this->assertSame(40, $this->saldo());

        $this->actingAs($this->directora)
            ->post(route('ausencias.anular', $ausencia))
            ->assertRedirect(route('ausencias.index'));

        $this->assertSame(0, $this->saldo());
        $this->assertSame('anulada', $ausencia->fresh()->estado);
    }

    public function test_despues_de_anular_se_puede_registrar_de_nuevo_la_ausencia(): void
    {
        $ausencia = $this->registrarAusencia();
        $this->actingAs($this->directora)->post(route('ausencias.anular', $ausencia));

        $this->registrarAusencia2();

        $this->assertSame(40, $this->saldo());
        $this->assertSame(2, Ausencia::count());
    }

    private function registrarAusencia2(): void
    {
        $this->actingAs($this->directora)->post('/ausencias', [
            'docente_id' => $this->falta->id,
            'fecha'      => '2026-03-02',
            'motivo'     => 'Corrección de fecha',
            'cubre'      => [$this->horario->id => $this->guia->id],
        ])->assertSessionHasNoErrors();
    }

    public function test_no_se_puede_anular_si_los_minutos_ya_se_usaron(): void
    {
        $ausencia = $this->registrarAusencia();
        (new SaldoService())->usar($this->guia, $this->curso->id, 40, '2026-03-10');

        $this->actingAs($this->directora)
            ->post(route('ausencias.anular', $ausencia))
            ->assertSessionHasErrors('ausencia');

        $this->assertSame('activa', $ausencia->fresh()->estado);
        $this->assertSame(0, $this->saldo());
    }

    // ---------- Ajuste manual ----------

    public function test_un_ajuste_manual_suma_minutos(): void
    {
        $this->actingAs($this->directora)
            ->post(route('saldos.ajustar', $this->guia), [
                'minutos' => 80, 'fecha' => '2026-03-02', 'motivo' => 'Saldo histórico',
            ])
            ->assertRedirect(route('saldos.show', $this->guia));

        $this->assertSame(80, $this->saldo());
    }

    public function test_un_ajuste_no_puede_dejar_el_saldo_negativo(): void
    {
        $this->actingAs($this->directora)
            ->post(route('saldos.ajustar', $this->guia), [
                'minutos' => -40, 'fecha' => '2026-03-02', 'motivo' => 'Error',
            ])
            ->assertSessionHasErrors('minutos');

        $this->assertSame(0, $this->saldo());
    }

    public function test_el_ajuste_exige_motivo(): void
    {
        $this->actingAs($this->directora)
            ->post(route('saldos.ajustar', $this->guia), [
                'minutos' => 40, 'fecha' => '2026-03-02', 'motivo' => '',
            ])
            ->assertSessionHasErrors('motivo');

        $this->assertSame(0, Movimiento::count());
    }

    // ---------- Revertir un movimiento ----------

    public function test_revertir_un_uso_devuelve_los_minutos(): void
    {
        $servicio = new SaldoService();
        $servicio->ajustar($this->guia, $this->curso->id, 80, '2026-03-02', 'Saldo inicial');
        $uso = $servicio->usar($this->guia, $this->curso->id, 40, '2026-03-10');
        $this->assertSame(40, $this->saldo());

        $this->actingAs($this->directora)
            ->post(route('movimientos.revertir', $uso))
            ->assertRedirect(route('saldos.show', $this->guia));

        $this->assertSame(80, $this->saldo());
    }

    // ---------- Permisos ----------

    public function test_un_usuario_de_consulta_no_puede_corregir_nada(): void
    {
        $ausencia = $this->registrarAusencia();
        $uso = (new SaldoService())->ajustar($this->guia, $this->curso->id, 40, '2026-03-02', 'x');
        $consulta = User::factory()->create(['rol' => 'consulta']);

        $this->actingAs($consulta)->get('/ausencias')->assertForbidden();
        $this->actingAs($consulta)->post(route('ausencias.anular', $ausencia))->assertForbidden();
        $this->actingAs($consulta)->post(route('saldos.ajustar', $this->guia), [
            'minutos' => 40, 'fecha' => '2026-03-02', 'motivo' => 'x',
        ])->assertForbidden();
        $this->actingAs($consulta)->post(route('movimientos.revertir', $uso))->assertForbidden();

        $this->assertSame('activa', $ausencia->fresh()->estado);
        $this->assertSame(80, $this->saldo());
    }
}
