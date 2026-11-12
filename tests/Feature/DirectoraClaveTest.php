<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DirectoraClaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_la_cuenta_de_la_directora(): void
    {
        $this->artisan('directora:clave', [
            '--nombre' => 'Directora Prueba',
            '--email' => 'dir@escuela.test',
            '--clave' => 'ClaveSegura123',
        ])->assertSuccessful();

        $u = User::where('email', 'dir@escuela.test')->first();
        $this->assertSame('directora', $u->rol);
        $this->assertTrue(Hash::check('ClaveSegura123', $u->password));
        $this->assertNotNull($u->email_verified_at);
    }

    public function test_si_ya_existe_solo_cambia_la_clave(): void
    {
        $this->artisan('directora:clave', ['--nombre' => 'Dir', '--email' => 'dir@escuela.test', '--clave' => 'ClaveVieja123']);
        $this->artisan('directora:clave', ['--clave' => 'ClaveNueva456'])->assertSuccessful();

        $this->assertSame(1, User::where('rol', 'directora')->count());
        $this->assertTrue(Hash::check('ClaveNueva456', User::first()->password));
    }

    public function test_rechaza_una_clave_corta(): void
    {
        $this->artisan('directora:clave', ['--nombre' => 'Dir', '--email' => 'dir@escuela.test', '--clave' => 'corta'])
            ->assertFailed();

        $this->assertSame(0, User::count());
    }
}
