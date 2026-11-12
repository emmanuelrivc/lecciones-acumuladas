<?php

namespace Tests\Feature;

use App\Models\CursoLectivo;
use App\Models\DescuentoGeneral;
use App\Models\Docente;
use App\Models\Grupo;
use App\Models\User;
use App\Services\SaldoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DescuentosPantallaTest extends TestCase
{
    use RefreshDatabase;

    private CursoLectivo $curso;
    private Docente $a; // 80 minutos
    private Docente $b; // 40 minutos
    private User $directora;

    protected function setUp(): void
    {
        parent::setUp();

        $this->curso = CursoLectivo::create([
            'anio' => 2026, 'fecha_vencimiento' => '2026-12-12', 'cerrado' => false,
        ]);
        $this->a = Docente::create(['nombre' => 'Docente A', 'activo' => true]);
        $this->b = Docente::create(['nombre' => 'Docente B', 'activo' => true]);

        $servicio = new SaldoService();
        $servicio->ajustar($this->a, $this->curso->id, 80, '2026-03-02', 'Saldo inicial');
        $servicio->ajustar($this->b, $this->curso->id, 40, '2026-03-02', 'Saldo inicial');

        // Solo las docentes guía reciben el descuento general
        Grupo::create(['curso_lectivo_id' => $this->curso->id, 'nivel' => 1, 'seccion' => 'A', 'guia_id' => $this->a->id]);
        Grupo::create(['curso_lectivo_id' => $this->curso->id, 'nivel' => 1, 'seccion' => 'B', 'guia_id' => $this->b->id]);

        $this->directora = User::factory()->create(['rol' => 'directora']);
    }

    private function datos(array $extra = []): array
    {
        return array_merge(['minutos' => 60, 'fecha' => '2026-05-10', 'motivo' => 'Día libre', 'docente_ids' => [$this->a->id, $this->b->id]], $extra);
    }

    public function test_la_directora_aplica_un_descuento_y_nadie_queda_en_negativo(): void
    {
        $this->actingAs($this->directora)
            ->post('/descuentos', $this->datos())
            ->assertRedirect(route('descuentos.index'));

        $this->assertSame(20, $this->a->saldoEn($this->curso->id)); // 80 - 60
        $this->assertSame(0, $this->b->saldoEn($this->curso->id));  // 40 - 40
        $this->assertSame(1, DescuentoGeneral::count());
    }

    public function test_revertir_devuelve_los_minutos_a_todas(): void
    {
        $this->actingAs($this->directora)->post('/descuentos', $this->datos());
        $descuento = DescuentoGeneral::first();

        $this->actingAs($this->directora)
            ->post(route('descuentos.revertir', $descuento))
            ->assertRedirect(route('descuentos.index'));

        $this->assertSame(80, $this->a->saldoEn($this->curso->id));
        $this->assertSame(40, $this->b->saldoEn($this->curso->id));
        $this->assertTrue($descuento->fresh()->revertido);
    }

    public function test_un_descuento_no_se_puede_revertir_dos_veces(): void
    {
        $this->actingAs($this->directora)->post('/descuentos', $this->datos());
        $descuento = DescuentoGeneral::first();

        $this->actingAs($this->directora)->post(route('descuentos.revertir', $descuento));
        $this->actingAs($this->directora)->post(route('descuentos.revertir', $descuento))
            ->assertSessionHasErrors('descuento');

        $this->assertSame(80, $this->a->saldoEn($this->curso->id)); // no se devolvió doble
    }

    public function test_el_motivo_es_obligatorio(): void
    {
        $this->actingAs($this->directora)
            ->post('/descuentos', $this->datos(['motivo' => '']))
            ->assertSessionHasErrors('motivo');

        $this->assertSame(0, DescuentoGeneral::count());
        $this->assertSame(80, $this->a->saldoEn($this->curso->id));
    }

    public function test_la_pantalla_lista_los_descuentos_aplicados(): void
    {
        $this->actingAs($this->directora)->post('/descuentos', $this->datos(['motivo' => 'Día de la madre']));

        $this->actingAs($this->directora)
            ->get('/descuentos')
            ->assertOk()
            ->assertSee('Día de la madre');
    }

    public function test_un_usuario_de_consulta_no_puede_ver_ni_aplicar_descuentos(): void
    {
        $consulta = User::factory()->create(['rol' => 'consulta']);

        $this->actingAs($consulta)->get('/descuentos')->assertForbidden();
        $this->actingAs($consulta)->post('/descuentos', $this->datos())->assertForbidden();

        $this->assertSame(80, $this->a->saldoEn($this->curso->id));
    }

    public function test_la_lista_muestra_a_cuales_docentes_se_les_aplico(): void
    {
        $this->actingAs($this->directora)->post('/descuentos', $this->datos());

        $this->actingAs($this->directora)->get('/descuentos')
            ->assertSee('Docente A')
            ->assertSee('Docente B')
            ->assertSee('60 min')
            ->assertSee('40 min');
    }

    public function test_solo_se_descuenta_a_las_docentes_elegidas(): void
    {
        $this->actingAs($this->directora)
            ->post('/descuentos', $this->datos(['docente_ids' => [$this->a->id]]));

        $this->assertSame(20, $this->a->saldoEn($this->curso->id));
        $this->assertSame(40, $this->b->saldoEn($this->curso->id)); // no se tocó
    }

    public function test_no_se_descuenta_a_quien_no_es_guia(): void
    {
        $especialista = Docente::create(['nombre' => 'Especialista', 'activo' => true]);
        (new SaldoService())->ajustar($especialista, $this->curso->id, 50, '2026-03-02', 'Saldo inicial');

        $this->actingAs($this->directora)
            ->post('/descuentos', $this->datos(['docente_ids' => [$especialista->id]]))
            ->assertSessionHasErrors('docente_ids');

        $this->assertSame(50, $especialista->saldoEn($this->curso->id));
        $this->assertSame(0, DescuentoGeneral::count());
    }

    public function test_hay_que_elegir_al_menos_una_docente(): void
    {
        $this->actingAs($this->directora)
            ->post('/descuentos', $this->datos(['docente_ids' => []]))
            ->assertSessionHasErrors('docente_ids');
    }

    public function test_la_pantalla_ofrece_solo_las_guias_para_elegir(): void
    {
        Docente::create(['nombre' => 'Especialista Inglés', 'activo' => true]);

        $this->actingAs($this->directora)->get('/descuentos')
            ->assertSee('Docente A')
            ->assertSee('Marcar todas')
            ->assertDontSee('Especialista Inglés');
    }
}
