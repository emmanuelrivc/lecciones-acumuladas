<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudUso extends Model
{
    // Nombre real de la tabla (Laravel no lo adivinaría bien)
    protected $table = 'solicitudes_uso';

    protected $fillable = [
        'docente_id', 'curso_lectivo_id', 'minutos', 'fecha_uso', 'motivo', 'estado',
        'registrada_por', 'resuelta_por', 'resuelta_en', 'nota_resolucion', 'movimiento_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_uso' => 'date',
            'resuelta_en' => 'datetime',
        ];
    }

    // La docente que pide los minutos
    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'docente_id');
    }

    // El movimiento de "uso" que se creó al aprobar
    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(Movimiento::class, 'movimiento_id');
    }
}
