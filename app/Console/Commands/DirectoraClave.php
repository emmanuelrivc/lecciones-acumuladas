<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class DirectoraClave extends Command
{
    protected $signature = 'directora:clave {--nombre=} {--email=} {--clave=}';

    protected $description = 'Crea la cuenta de la directora, o le pone una clave nueva si se le olvidó';

    public function handle(): int
    {
        $directora = User::where('rol', 'directora')->first();

        if ($directora) {
            $this->info("Ya existe la cuenta de la directora: {$directora->email}");
            $this->info('Se le pondrá una clave nueva.');
        } else {
            $this->info('Todavía no existe la cuenta de la directora. Vamos a crearla.');
        }

        $nombre = $directora?->name ?? ($this->option('nombre') ?: $this->ask('Nombre completo de la directora'));
        $email = $directora?->email ?? ($this->option('email') ?: $this->ask('Correo para entrar al sistema'));

        $clave = $this->option('clave');
        if (! $clave) {
            $clave = $this->secret('Clave nueva (mínimo 8 caracteres)');
            $repetida = $this->secret('Escriba la clave otra vez');
            if ($clave !== $repetida) {
                $this->error('Las dos claves no son iguales. No se cambió nada.');

                return self::FAILURE;
            }
        }

        if (mb_strlen((string) $clave) < 8) {
            $this->error('La clave debe tener al menos 8 caracteres. No se cambió nada.');

            return self::FAILURE;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('El correo no es válido. No se cambió nada.');

            return self::FAILURE;
        }

        $usuario = $directora ?? new User();
        $usuario->forceFill([
            'name' => $nombre,
            'email' => $email,
            'password' => Hash::make($clave),
            'rol' => 'directora',
            'email_verified_at' => $usuario->email_verified_at ?? now(),
        ])->save();

        $this->info('Listo. La directora ya puede entrar con: '.$email);

        return self::SUCCESS;
    }
}
