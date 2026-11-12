<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Horario extends Model
{
    // Campos que se pueden llenar en bloque
    protected $fillable = [
        'docente_id', 'grupo_id', 'materia_id', 'franja_id',
        'dia_semana', 'vigente_desde', 'vigente_hasta',
    ];

    // Conversiones automáticas de tipo
    protected function casts(): array
    {
        return [
            'vigente_desde' => 'date',
            'vigente_hasta' => 'date',
        ];
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'docente_id');
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class, 'materia_id');
    }

    public function franja(): BelongsTo
    {
        return $this->belongsTo(FranjaLeccion::class, 'franja_id');
    }
}
