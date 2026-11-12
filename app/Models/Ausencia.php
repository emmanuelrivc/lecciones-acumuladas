<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ausencia extends Model
{
    // Campos que se pueden llenar en bloque
    protected $fillable = [
        'docente_id', 'curso_lectivo_id', 'registrada_por',
        'fecha', 'motivo', 'aviso_previo', 'justificacion', 'estado', 'franjas_faltadas',
    ];

    // Conversiones automáticas de tipo
    protected function casts(): array
    {
        return [
            'fecha' => 'date',            // texto de la base de datos -> fecha de verdad
            'aviso_previo' => 'boolean',  // 0/1 -> false/true
            'franjas_faltadas' => 'array', // lecciones en las que faltó (null = todo el día)
        ];
    }

    // La docente que faltó
    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'docente_id');
    }

    // El curso lectivo al que pertenece
    public function cursoLectivo(): BelongsTo
    {
        return $this->belongsTo(CursoLectivo::class, 'curso_lectivo_id');
    }

    // El usuario (directora) que la registró
    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por');
    }

    // Las lecciones perdidas en esta ausencia (una ausencia tiene MUCHAS)
    public function lecciones(): HasMany
    {
        return $this->hasMany(AusenciaLeccion::class, 'ausencia_id');
    }
}
