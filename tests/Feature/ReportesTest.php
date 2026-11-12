<?php

namespace Tests\Feature;

use App\Models\Ausencia;
use App\Models\CursoLectivo;
use App\Models\Docente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $curso = CursoLectivo::create(['anio' => 2026, 'fecha_vencimiento' => '2026-12-12', 'cerrado' => false]);
        $docente = Docente::create(['nombre' => 'Docente A', 'activo' => true]);
        $directora = User::factory()->create(['rol' => 'directora']);
        Ausencia::create([
            'docente_id' => $docente->id, 'curso_lectivo_id' => $curso->id, 'registrada_por' => $directora->id,
            'fecha' => '2026-03-02', 'motivo' => 'Cita médica', 'estado' => 'activa',
        ]);
    }

    public function test_la_pantalla_de_reportes_se_muestra(): void
    {
        $this->actingAs(User::factory()->create(['rol' => 'consulta']))
            ->get('/reportes')->assertOk()->assertSee('Saldos del personal');
    }

    public function test_el_pdf_de_saldos_se_genera(): void
    {
        $r = $this->actingAs(User::factory()->create(['rol' => 'directora']))->get('/reportes/saldos');

        $r->assertOk();
        $this->assertStringContainsString('application/pdf', $r->headers->get('content-type'));
    }

    public function test_el_pdf_de_ausencias_se_genera(): void
    {
        $r = $this->actingAs(User::factory()->create(['rol' => 'consulta']))->get('/reportes/ausencias');

        $r->assertOk();
        $this->assertStringContainsString('application/pdf', $r->headers->get('content-type'));
    }

    public function test_un_visitante_es_enviado_al_login(): void
    {
        $this->get('/reportes/saldos')->assertRedirect('/login');
    }
}
