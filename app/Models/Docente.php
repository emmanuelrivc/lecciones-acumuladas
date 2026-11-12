<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Docente extends Model
{
    protected $fillable = ['nombre', 'activo'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    // Todos los movimientos de saldo de esta docente (una docente tiene MUCHOS)
    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class, 'docente_id');
    }

    // Saldo en minutos dentro de un curso lectivo = la SUMA de sus movimientos
    // (los que suman son positivos y los que restan son negativos)
    public function saldoEn(int $cursoLectivoId): int
    {
        return (int) $this->movimientos()
            ->where('curso_lectivo_id', $cursoLectivoId)
            ->sum('minutos');
    }
}
