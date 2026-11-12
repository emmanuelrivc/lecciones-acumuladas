<?php

namespace Tests\Feature;

use App\Models\CursoLectivo;
use App\Models\Docente;
use App\Models\Movimiento;
use App\Models\SolicitudUso;
use App\Models\User;
use App\Services\SaldoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolicitudesTest extends TestCase
{
    use RefreshDatabase;

    private CursoLectivo $curso;
    private Docente $docente;
    private User $directora;

    protected function setUp(): void
    {
        parent::setUp();

        $this->curso = CursoLectivo::create(['anio' => 2026, 'fecha_vencimiento' => '2026-12-12', 'cerrado' => false]);
        $this->docente = Docente::create(['nombre' => 'Docente A', 'activo' => true]);
        $this->directora = User::factory()->create(['rol' => 'directora']);

        (new SaldoService())->ajustar($this->docente, $this->curso->id, 120, '2026-03-02', 'Saldo inicial');
    }

    private function saldo(): int
    {
        return $this->docente->saldoEn($this->curso->id);
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'docente_id' => $this->docente->id,
            'minutos'    => 80,
            'fecha_uso'  => '2026-03-10',
        ], $extra);
    }

    private function pendiente(int $minutos = 80): SolicitudUso
    {
        return SolicitudUso::create([
            'docente_id' => $this->docente->id, 'curso_lectivo_id' => $this->curso->id,
            'minutos' => $minutos, 'fecha_uso' => '2026-03-10', 'motivo' => 'Cita médica',
            'estado' => 'pendiente', 'registrada_por' => $this->directora->id,
        ]);
    }

    public function test_registrar_una_solicitud_la_deja_pendiente_y_no_toca_el_saldo(): void
    {
        $this->actingAs($this->directora)->post('/solicitudes', $this->datos())
            ->assertRedirect(route('solicitudes.index'));

        $this->assertSame('pendiente', SolicitudUso::first()->estado);
        $this->assertSame(120, $this->saldo());
    }

    public function test_no_se_puede_pedir_mas_de_lo_acumulado(): void
    {
        $this->actingAs($this->directora)->post('/solicitudes', $this->datos(['minutos' => 200]))
            ->assertSessionHasErrors('minutos');

        $this->assertSame(0, SolicitudUso::count());
    }

    public function test_se_registra_sin_motivo_porque_los_minutos_son_un_derecho(): void
    {
        $this->actingAs($this->directora)->post('/solicitudes', $this->datos())
            ->assertSessionHasNoErrors();

        $this->assertNull(SolicitudUso::first()->motivo);
    }

    public function test_solo_aparecen_las_docentes_con_minutos(): void
    {
        Docente::create(['nombre' => 'Docente Sin Saldo', 'activo' => true]);

        $this->actingAs($this->directora)->get('/solicitudes')
            ->assertOk()
            ->assertSee('Docente A (120 min)')
            ->assertDontSee('Docente Sin Saldo');
    }

    public function test_aprobar_una_solicitud_sin_motivo_funciona(): void
    {
        $this->actingAs($this->directora)->post('/solicitudes', $this->datos(['minutos' => 40]));
        $s = SolicitudUso::first();

        $this->actingAs($this->directora)->post(route('solicitudes.aprobar', $s))
            ->assertSessionHasNoErrors();

        $this->assertSame(80, $this->saldo());
    }

    public function test_aprobar_descuenta_los_minutos_y_enlaza_el_movimiento(): void
    {
        $s = $this->pendiente(80);

        $this->actingAs($this->directora)->post(route('solicitudes.aprobar', $s))
            ->assertRedirect(route('solicitudes.index'));

        $s->refresh();
        $this->assertSame('aprobada', $s->estado);
        $this->assertSame(40, $this->saldo());
        $this->assertNotNull($s->movimiento_id);
        $this->assertSame(-80, Movimiento::find($s->movimiento_id)->minutos);
        $this->assertSame($this->directora->id, $s->resuelta_por);
    }

    public function test_si_el_saldo_ya_no_alcanza_no_se_aprueba(): void
    {
        $s = $this->pendiente(80);
        (new SaldoService())->ajustar($this->docente, $this->curso->id, -100, '2026-03-05', 'Corrección');

        $this->actingAs($this->directora)->post(route('solicitudes.aprobar', $s))
            ->assertSessionHasErrors('solicitud');

        $this->assertSame('pendiente', $s->fresh()->estado);
        $this->assertSame(20, $this->saldo());
    }

    public function test_una_solicitud_no_se_puede_aprobar_dos_veces(): void
    {
        $s = $this->pendiente(40);
        $this->actingAs($this->directora)->post(route('solicitudes.aprobar', $s));

        $this->actingAs($this->directora)->post(route('solicitudes.aprobar', $s))
            ->assertSessionHasErrors('solicitud');

        $this->assertSame(80, $this->saldo());
    }

    public function test_rechazar_exige_razon_y_no_toca_el_saldo(): void
    {
        $s = $this->pendiente(80);

        $this->actingAs($this->directora)->post(route('solicitudes.rechazar', $s), ['nota' => ''])
            ->assertSessionHasErrors('nota');
        $this->assertSame('pendiente', $s->fresh()->estado);

        $this->actingAs($this->directora)->post(route('solicitudes.rechazar', $s), ['nota' => 'Ese día hay examen'])
            ->assertRedirect(route('solicitudes.index'));

        $this->assertSame('rechazada', $s->fresh()->estado);
        $this->assertSame('Ese día hay examen', $s->fresh()->nota_resolucion);
        $this->assertSame(120, $this->saldo());
    }

    public function test_con_el_curso_cerrado_no_se_registra_ni_se_aprueba(): void
    {
        $s = $this->pendiente(40);
        $this->curso->update(['cerrado' => true]);

        $this->actingAs($this->directora)->post('/solicitudes', $this->datos())
            ->assertSessionHasErrors('docente_id');
        $this->actingAs($this->directora)->post(route('solicitudes.aprobar', $s))
            ->assertSessionHasErrors('solicitud');

        $this->assertSame('pendiente', $s->fresh()->estado);
        $this->assertSame(120, $this->saldo());
    }

    public function test_la_pantalla_lista_pendientes_y_resueltas(): void
    {
        $this->pendiente(40);

        $this->actingAs($this->directora)->get('/solicitudes')
            ->assertOk()
            ->assertSee('Pendientes (1)')
            ->assertSee('Docente A')
            ->assertSee('Cita médica');
    }

    public function test_un_usuario_de_consulta_no_puede_usar_solicitudes(): void
    {
        $consulta = User::factory()->create(['rol' => 'consulta']);
        $s = $this->pendiente(40);

        $this->actingAs($consulta)->get('/solicitudes')->assertForbidden();
        $this->actingAs($consulta)->post('/solicitudes', $this->datos())->assertForbidden();
        $this->actingAs($consulta)->post(route('solicitudes.aprobar', $s))->assertForbidden();
        $this->actingAs($consulta)->post(route('solicitudes.rechazar', $s), ['nota' => 'x'])->assertForbidden();

        $this->assertSame('pendiente', $s->fresh()->estado);
    }

    public function test_al_usar_minutos_desde_saldos_se_resuelve_la_pendiente_que_cubren(): void
    {
        $s = $this->pendiente(80);

        $this->actingAs($this->directora)
            ->post(route('saldos.usar', $this->docente), ['minutos' => 80, 'fecha' => '2026-03-10'])
            ->assertRedirect();

        $this->assertSame('aprobada', $s->fresh()->estado);
        $this->assertSame(40, $this->saldo());
    }

    public function test_una_pendiente_mayor_a_lo_usado_sigue_pendiente(): void
    {
        $s = $this->pendiente(80);

        $this->actingAs($this->directora)
            ->post(route('saldos.usar', $this->docente), ['minutos' => 40, 'fecha' => '2026-03-10']);

        $this->assertSame('pendiente', $s->fresh()->estado);
    }

    public function test_si_ya_no_hay_saldo_la_pendiente_no_ofrece_aprobar(): void
    {
        $this->pendiente(80);
        (new SaldoService())->usar($this->docente, $this->curso->id, 120, '2026-03-11');

        $this->actingAs($this->directora)->get('/solicitudes')
            ->assertSee('Ya no tiene saldo suficiente')
            ->assertSee('Descartar');
    }

    public function test_saldos_muestra_el_boton_para_usar_o_modificar(): void
    {
        $this->actingAs($this->directora)->get('/saldos')->assertSee('Usar / modificar');
    }
}
