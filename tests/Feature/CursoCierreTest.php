<?php

namespace Tests\Feature;

use App\Models\Ausencia;
use App\Models\CursoLectivo;
use App\Models\Docente;
use App\Models\User;
use App\Services\SaldoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CursoCierreTest extends TestCase
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
        (new SaldoService())->ajustar($this->docente, $this->curso->id, 80, '2026-03-02', 'Saldo inicial');

        $this->directora = User::factory()->create(['rol' => 'directora']);
    }

    private function cerrar(): void
    {
        $this->actingAs($this->directora)->post('/curso/cerrar', ['confirmacion' => '2026']);
    }

    public function test_cerrar_el_curso_exige_escribir_el_anio(): void
    {
        $this->actingAs($this->directora)
            ->post('/curso/cerrar', ['confirmacion' => '2025'])
            ->assertSessionHasErrors('confirmacion');

        $this->assertFalse($this->curso->fresh()->cerrado);
    }

    public function test_la_directora_cierra_el_curso(): void
    {
        $this->actingAs($this->directora)
            ->post('/curso/cerrar', ['confirmacion' => '2026'])
            ->assertRedirect(route('curso.index'));

        $this->assertTrue($this->curso->fresh()->cerrado);
    }

    public function test_con_el_curso_cerrado_los_saldos_se_pueden_consultar(): void
    {
        $this->cerrar();

        $this->actingAs($this->directora)->get('/saldos')->assertOk()->assertSee('Docente A');
        $this->actingAs($this->directora)->get(route('saldos.show', $this->docente))->assertOk();
        $this->actingAs($this->directora)->get('/ausencias')->assertOk();
        $this->actingAs($this->directora)->get('/descuentos')->assertOk();
    }

    public function test_con_el_curso_cerrado_no_se_puede_modificar_nada(): void
    {
        $this->cerrar();

        $this->actingAs($this->directora)
            ->post(route('saldos.usar', $this->docente), ['minutos' => 40, 'fecha' => '2026-04-01'])
            ->assertSessionHasErrors('minutos');

        $this->actingAs($this->directora)
            ->post('/ausencias', ['docente_id' => $this->docente->id, 'fecha' => '2026-04-01', 'motivo' => 'x'])
            ->assertSessionHasErrors('docente_id');

        $this->assertSame(80, $this->docente->saldoEn($this->curso->id));
        $this->assertSame(0, Ausencia::count());
    }

    public function test_reabrir_el_curso_permite_modificar_de_nuevo(): void
    {
        $this->cerrar();
        $this->actingAs($this->directora)->post('/curso/reabrir');

        $this->actingAs($this->directora)
            ->post(route('saldos.usar', $this->docente), ['minutos' => 40, 'fecha' => '2026-04-01'])
            ->assertSessionHasNoErrors();

        $this->assertSame(40, $this->docente->saldoEn($this->curso->id));
    }

    public function test_un_usuario_de_consulta_no_puede_cerrar_ni_reabrir(): void
    {
        $consulta = User::factory()->create(['rol' => 'consulta']);

        $this->actingAs($consulta)->get('/curso')->assertForbidden();
        $this->actingAs($consulta)->post('/curso/cerrar', ['confirmacion' => '2026'])->assertForbidden();
        $this->actingAs($consulta)->post('/curso/reabrir')->assertForbidden();

        $this->assertFalse($this->curso->fresh()->cerrado);
    }
}
