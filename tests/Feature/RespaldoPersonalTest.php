<?php

namespace Tests\Feature;

use App\Models\CursoLectivo;
use App\Models\Docente;
use App\Models\Movimiento;
use App\Models\User;
use App\Services\SaldoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RespaldoPersonalTest extends TestCase
{
    use RefreshDatabase;

    private CursoLectivo $curso;
    private Docente $docente;
    private User $directora;

    protected function setUp(): void
    {
        parent::setUp();

        $this->curso = CursoLectivo::create([
            'anio' => 2026, 'fecha_vencimiento' => '2026-12-12', 'cerrado' => false,
        ]);
        $this->docente = Docente::create(['nombre' => 'Docente A', 'activo' => true]);
        $this->directora = User::factory()->create(['rol' => 'directora']);
    }

    private function saldo(): int
    {
        return $this->docente->saldoEn($this->curso->id);
    }

    // ---------- Respaldo ----------

    public function test_la_pantalla_de_respaldo_se_muestra_a_la_directora(): void
    {
        $this->actingAs($this->directora)->get('/respaldo')->assertOk()->assertSee('Descargar respaldo');
    }

    public function test_el_respaldo_responde_con_un_aviso_si_no_hay_archivo_de_base_de_datos(): void
    {
        // En las pruebas la base vive en memoria: no hay archivo que copiar, y no debe romperse
        $this->actingAs($this->directora)
            ->post('/respaldo/descargar')
            ->assertSessionHasErrors('respaldo');
    }

    public function test_un_usuario_de_consulta_no_puede_descargar_respaldos(): void
    {
        $consulta = User::factory()->create(['rol' => 'consulta']);

        $this->actingAs($consulta)->get('/respaldo')->assertForbidden();
        $this->actingAs($consulta)->post('/respaldo/descargar')->assertForbidden();
    }

    // ---------- Personal ----------

    public function test_se_puede_agregar_una_docente(): void
    {
        // Ahora hay que decir qué es: aquí, guía de un grupo que no tiene guía
        $grupo = \App\Models\Grupo::create(['curso_lectivo_id' => $this->curso->id, 'nivel' => 1, 'seccion' => 'A', 'guia_id' => null]);

        $this->actingAs($this->directora)
            ->post('/personal', ['nombre' => 'Docente Nueva', 'tipo' => 'guia', 'grupo_id' => $grupo->id])
            ->assertRedirect(route('personal.index'));

        $this->assertTrue(Docente::where('nombre', 'Docente Nueva')->where('activo', true)->exists());
    }

    public function test_no_se_puede_repetir_el_nombre(): void
    {
        $this->actingAs($this->directora)
            ->post('/personal', ['nombre' => 'Docente A'])
            ->assertSessionHasErrors('nombre');

        $this->assertSame(1, Docente::where('nombre', 'Docente A')->count());
    }

    public function test_dar_de_baja_deja_el_saldo_en_cero_y_la_docente_inactiva(): void
    {
        (new SaldoService())->ajustar($this->docente, $this->curso->id, 120, '2026-03-02', 'Saldo inicial');

        $this->actingAs($this->directora)
            ->post(route('personal.baja', $this->docente))
            ->assertRedirect(route('personal.index'));

        $this->assertSame(0, $this->saldo());
        $this->assertFalse($this->docente->fresh()->activo);
        $this->assertTrue(
            Movimiento::where('docente_id', $this->docente->id)
                ->where('tipo', SaldoService::AJUSTE)
                ->where('nota', 'Salida del personal')
                ->where('minutos', -120)
                ->exists()
        );
    }

    public function test_dar_de_baja_sin_saldo_no_crea_movimientos(): void
    {
        $this->actingAs($this->directora)->post(route('personal.baja', $this->docente));

        $this->assertFalse($this->docente->fresh()->activo);
        $this->assertSame(0, Movimiento::count());
    }

    public function test_no_se_puede_dar_de_baja_con_el_curso_cerrado(): void
    {
        (new SaldoService())->ajustar($this->docente, $this->curso->id, 80, '2026-03-02', 'Saldo inicial');
        $this->curso->update(['cerrado' => true]);

        $this->actingAs($this->directora)
            ->post(route('personal.baja', $this->docente))
            ->assertSessionHasErrors('personal');

        $this->assertTrue($this->docente->fresh()->activo);
        $this->assertSame(80, $this->saldo());
    }

    public function test_reactivar_vuelve_a_activar_sin_recuperar_el_saldo(): void
    {
        (new SaldoService())->ajustar($this->docente, $this->curso->id, 80, '2026-03-02', 'Saldo inicial');
        $this->actingAs($this->directora)->post(route('personal.baja', $this->docente));

        $this->actingAs($this->directora)->post(route('personal.reactivar', $this->docente));

        $this->assertTrue($this->docente->fresh()->activo);
        $this->assertSame(0, $this->saldo());
    }

    public function test_un_usuario_de_consulta_no_puede_gestionar_el_personal(): void
    {
        $consulta = User::factory()->create(['rol' => 'consulta']);

        $this->actingAs($consulta)->get('/personal')->assertForbidden();
        $this->actingAs($consulta)->post('/personal', ['nombre' => 'X'])->assertForbidden();
        $this->actingAs($consulta)->post(route('personal.baja', $this->docente))->assertForbidden();

        $this->assertTrue($this->docente->fresh()->activo);
    }
}
