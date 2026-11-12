<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CursoLectivo extends Model
{
    protected $table = 'cursos_lectivos';
    protected $fillable = ['anio', 'fecha_vencimiento', 'cerrado'];

    // Conversiones automáticas de tipo
    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
            'cerrado' => 'boolean',
        ];
    }

    // El curso lectivo "actual": el del año más reciente (esté abierto o cerrado)
    public static function actual(): self
    {
        return static::orderByDesc('anio')->firstOrFail();
    }
}
