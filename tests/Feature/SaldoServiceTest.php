<?php

namespace Tests\Feature;

use App\Models\Ausencia;
use App\Models\AusenciaLeccion;
use App\Models\CursoLectivo;
use App\Models\Docente;
use App\Models\FranjaLeccion;
use App\Models\Grupo;
use App\Models\Movimiento;
use App\Models\User;
use App\Services\SaldoService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas automáticas de las reglas de saldo.
 * RefreshDatabase usa una base de datos TEMPORAL en memoria: nunca toca tus datos reales.
 */
class SaldoServiceTest extends TestCase
{
    use RefreshDatabase;

    private SaldoService $service;
    private CursoLectivo $curso;
    private Docente $falta;      // la docente que faltó
    private User $directora;

    // Se ejecuta antes de CADA prueba: deja el escenario base listo
    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SaldoService();
        $this->curso = CursoLectivo::create([
            'anio' => 2026,
            'fecha_vencimiento' => '2026-12-12',
            'cerrado' => false,
        ]);
        $this->falta = Docente::create(['nombre' => 'Docente Ausente', 'activo' => true]);
        $this->directora = User::factory()->create();
    }

    // Crea una ausencia con UNA lección de 40 min cubierta por $cubre
    private function leccion(Docente $cubre, ?CursoLectivo $curso = null): AusenciaLeccion
    {
        $curso ??= $this->curso;

        $grupo = Grupo::firstOrCreate([
            'curso_lectivo_id' => $curso->id,
            'nivel' => 1,
            'seccion' => 'A',
        ]);

        $franja = FranjaLeccion::firstOrCreate(
            ['numero' => 1],
            ['hora_inicio' => '07:00', 'hora_fin' => '07:40', 'es_receso' => false]
        );

        $ausencia = Ausencia::create([
            'docente_id' => $this->falta->id,
            'curso_lectivo_id' => $curso->id,
            'registrada_por' => $this->directora->id,
            'fecha' => '2026-03-02',
            'motivo' => 'Cita médica',
            'aviso_previo' => true,
            'estado' => 'activa',
        ]);

        return AusenciaLeccion::create([
            'ausencia_id' => $ausencia->id,
            'grupo_id' => $grupo->id,
            'cubre_docente_id' => $cubre->id,
            'fecha' => '2026-03-02',
            'franja_id' => $franja->id,
            'minutos' => 40,
        ]);
    }

    // Le da a una docente N lecciones acumuladas (N x 40 minutos)
    private function conSaldo(Docente $docente, int $lecciones): void
    {
        for ($i = 0; $i < $lecciones; $i++) {
            $this->service->acumular($this->leccion($docente));
        }
    }

    private function saldo(Docente $docente): int
    {
        return $docente->saldoEn($this->curso->id);
    }

    public function test_una_leccion_cubierta_suma_40_minutos_a_quien_la_cubre(): void
    {
        $cubre = Docente::create(['nombre' => 'Guia Uno', 'activo' => true]);

        $this->service->acumular($this->leccion($cubre));

        $this->assertSame(40, $this->saldo($cubre));
    }

    public function test_la_misma_leccion_no_puede_acumular_dos_veces(): void
    {
        $cubre = Docente::create(['nombre' => 'Guia Uno', 'activo' => true]);
        $leccion = $this->leccion($cubre);

        $this->service->acumular($leccion);

        $this->assertThrows(fn () => $this->service->acumular($leccion), DomainException::class);
        $this->assertSame(40, $this->saldo($cubre));
    }

    public function test_usar_minutos_resta_del_saldo(): void
    {
        $cubre = Docente::create(['nombre' => 'Guia Uno', 'activo' => true]);
        $this->conSaldo($cubre, 3); // 120 minutos

        $this->service->usar($cubre, $this->curso->id, 80, '2026-04-01');

        $this->assertSame(40, $this->saldo($cubre));
    }

    public function test_no_se_puede_usar_mas_que_el_saldo(): void
    {
        $cubre = Docente::create(['nombre' => 'Guia Uno', 'activo' => true]);
        $this->conSaldo($cubre, 1); // 40 minutos

        $this->assertThrows(
            fn () => $this->service->usar($cubre, $this->curso->id, 80, '2026-04-01'),
            DomainException::class
        );

        $this->assertSame(40, $this->saldo($cubre)); // el saldo no cambió
    }

    public function test_revertir_un_uso_devuelve_los_minutos(): void
    {
        $cubre = Docente::create(['nombre' => 'Guia Uno', 'activo' => true]);
        $this->conSaldo($cubre, 3);
        $uso = $this->service->usar($cubre, $this->curso->id, 80, '2026-04-01');

        $reversion = $this->service->revertir($uso);

        $this->assertSame(120, $this->saldo($cubre));
        $this->assertSame($uso->id, $reversion->revierte_id);
        // Nada se borra: 3 acumulaciones + 1 uso + 1 reversión = 5 movimientos
        $this->assertSame(5, Movimiento::count());
    }

    public function test_un_movimiento_no_se_puede_revertir_dos_veces(): void
    {
        $cubre = Docente::create(['nombre' => 'Guia Uno', 'activo' => true]);
        $this->conSaldo($cubre, 3);
        $uso = $this->service->usar($cubre, $this->curso->id, 80, '2026-04-01');
        $this->service->revertir($uso);

        $this->assertThrows(fn () => $this->service->revertir($uso), DomainException::class);
        $this->assertSame(120, $this->saldo($cubre));
    }

    public function test_el_descuento_general_descuenta_el_menor_entre_el_descuento_y_el_saldo(): void
    {
        $a = Docente::create(['nombre' => 'Docente A', 'activo' => true]);
        $b = Docente::create(['nombre' => 'Docente B', 'activo' => true]);
        $this->conSaldo($a, 2); // 80 minutos
        $this->conSaldo($b, 1); // 40 minutos

        $this->service->aplicarDescuentoGeneral($this->curso->id, 60, '2026-05-10', 'Día libre');

        $this->assertSame(20, $this->saldo($a)); // 80 - 60
        $this->assertSame(0, $this->saldo($b));  // 40 - 40: no queda negativo
        $this->assertSame(2, Movimiento::where('tipo', SaldoService::DESCUENTO)->count());
    }

    public function test_revertir_el_descuento_general_devuelve_los_minutos_a_todas(): void
    {
        $a = Docente::create(['nombre' => 'Docente A', 'activo' => true]);
        $b = Docente::create(['nombre' => 'Docente B', 'activo' => true]);
        $this->conSaldo($a, 2);
        $this->conSaldo($b, 1);
        $descuento = $this->service->aplicarDescuentoGeneral($this->curso->id, 60, '2026-05-10', 'Día libre');

        $this->service->revertirDescuentoGeneral($descuento);

        $this->assertSame(80, $this->saldo($a));
        $this->assertSame(40, $this->saldo($b));
        $this->assertTrue($descuento->fresh()->revertido);
    }

    public function test_el_descuento_general_no_toca_a_docentes_inactivas(): void
    {
        $activa = Docente::create(['nombre' => 'Docente Activa', 'activo' => true]);
        $inactiva = Docente::create(['nombre' => 'Docente Inactiva', 'activo' => false]);
        $this->conSaldo($activa, 1);
        $this->conSaldo($inactiva, 1);

        $this->service->aplicarDescuentoGeneral($this->curso->id, 40, '2026-05-10', 'Día libre');

        $this->assertSame(0, $this->saldo($activa));
        $this->assertSame(40, $this->saldo($inactiva));
    }

    public function test_un_curso_cerrado_no_permite_movimientos_nuevos(): void
    {
        $cubre = Docente::create(['nombre' => 'Guia Uno', 'activo' => true]);
        $cerrado = CursoLectivo::create([
            'anio' => 2025,
            'fecha_vencimiento' => '2025-12-12',
            'cerrado' => true,
        ]);
        $leccion = $this->leccion($cubre, $cerrado);

        $this->assertThrows(fn () => $this->service->acumular($leccion), DomainException::class);
        $this->assertThrows(
            fn () => $this->service->usar($cubre, $cerrado->id, 40, '2026-04-01'),
            DomainException::class
        );
        $this->assertSame(0, Movimiento::count());
    }

    public function test_anular_una_leccion_quita_sus_minutos_y_la_marca_anulada(): void
    {
        $cubre = Docente::create(['nombre' => 'Guia Uno', 'activo' => true]);
        $leccion = $this->leccion($cubre);
        $this->service->acumular($leccion);

        $this->service->anularLeccion($leccion);

        $this->assertSame(0, $this->saldo($cubre));
        $this->assertTrue($leccion->fresh()->anulada);
    }

    public function test_no_se_puede_anular_una_leccion_si_sus_minutos_ya_se_usaron(): void
    {
        $cubre = Docente::create(['nombre' => 'Guia Uno', 'activo' => true]);
        $leccion = $this->leccion($cubre);
        $this->service->acumular($leccion);
        $this->service->usar($cubre, $this->curso->id, 40, '2026-04-01');

        $this->assertThrows(fn () => $this->service->anularLeccion($leccion), DomainException::class);

        $this->assertFalse($leccion->fresh()->anulada); // sigue activa
        $this->assertSame(0, $this->saldo($cubre));
    }

    public function test_un_ajuste_manual_suma_pero_nunca_deja_el_saldo_negativo(): void
    {
        $cubre = Docente::create(['nombre' => 'Guia Uno', 'activo' => true]);

        $this->service->ajustar($cubre, $this->curso->id, 80, '2026-06-01', 'Saldo histórico');
        $this->assertSame(80, $this->saldo($cubre));

        $this->assertThrows(
            fn () => $this->service->ajustar($cubre, $this->curso->id, -120, '2026-06-02', 'Error de digitación'),
            DomainException::class
        );
        $this->assertSame(80, $this->saldo($cubre));
    }

    public function test_un_ajuste_manual_exige_motivo(): void
    {
        $cubre = Docente::create(['nombre' => 'Guia Uno', 'activo' => true]);

        $this->assertThrows(
            fn () => $this->service->ajustar($cubre, $this->curso->id, 40, '2026-06-01', '   '),
            DomainException::class
        );
        $this->assertSame(0, $this->saldo($cubre));
    }
}
