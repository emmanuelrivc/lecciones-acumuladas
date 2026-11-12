<?php

namespace Tests\Feature;

use App\Models\CursoLectivo;
use App\Models\Docente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaldosPantallaTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_visitante_sin_sesion_es_enviado_al_login(): void
    {
        $this->get('/saldos')->assertRedirect('/login');
    }

    public function test_un_usuario_con_sesion_ve_la_lista_de_saldos(): void
    {
        CursoLectivo::create(['anio' => 2026, 'fecha_vencimiento' => '2026-12-12', 'cerrado' => false]);
        Docente::create(['nombre' => 'Docente Prueba', 'activo' => true]);

        $this->actingAs(User::factory()->create())
            ->get('/saldos')
            ->assertOk()
            ->assertSee('Docente Prueba');
    }

    public function test_solo_la_directora_pasa_el_guardia(): void
    {
        \Illuminate\Support\Facades\Route::middleware(['web', 'auth', 'directora'])
            ->get('/solo-directora-prueba', fn () => 'ok');

        $this->actingAs(User::factory()->create(['rol' => 'consulta']))
            ->get('/solo-directora-prueba')->assertForbidden();

        $this->actingAs(User::factory()->create(['rol' => 'directora']))
            ->get('/solo-directora-prueba')->assertOk();
    }
}
