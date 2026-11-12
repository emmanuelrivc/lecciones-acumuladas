<?php

namespace Tests\Feature;

use App\Models\Ausencia;
use App\Models\CursoLectivo;
use App\Models\Docente;
use App\Models\User;
use App\Services\SaldoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InicioTest extends TestCase
{
    use RefreshDatabase;

    private function curso(string $vence): CursoLectivo
    {
        return CursoLectivo::create(['anio' => 2026, 'fecha_vencimiento' => $vence, 'cerrado' => false]);
    }

    public function test_el_inicio_muestra_el_resumen(): void
    {
        $curso = $this->curso(now()->addDays(100)->toDateString());
        $a = Docente::create(['nombre' => 'Docente A', 'activo' => true]);
        $b = Docente::create(['nombre' => 'Docente B', 'activo' => true]);
        $inactiva = Docente::create(['nombre' => 'Docente C', 'activo' => false]);

        $s = new SaldoService();
        $s->ajustar($a, $curso->id, 80, now()->toDateString(), 'Inicial');
        $s->ajustar($b, $curso->id, 40, now()->toDateString(), 'Inicial');

        $directora = User::factory()->create(['rol' => 'directora']);
        Ausencia::create([
            'docente_id' => $a->id, 'curso_lectivo_id' => $curso->id, 'registrada_por' => $directora->id,
            'fecha' => now()->toDateString(), 'motivo' => 'Cita médica', 'estado' => 'activa',
        ]);

        $this->actingAs($directora)->get('/dashboard')
            ->assertOk()
            ->assertSee('120')            // 80 + 40 minutos
            ->assertSee('Cita médica')
            ->assertSee('Registrar ausencia');
    }

    public function test_avisa_cuando_el_vencimiento_esta_cerca(): void
    {
        $this->curso(now()->addDays(10)->toDateString());

        $this->actingAs(User::factory()->create(['rol' => 'consulta']))
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('vencen en')
            ->assertDontSee('Registrar ausencia');
    }

    public function test_avisa_cuando_ya_vencio(): void
    {
        $this->curso(now()->subDays(3)->toDateString());

        $this->actingAs(User::factory()->create(['rol' => 'directora']))
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('ya vencieron');
    }
}
