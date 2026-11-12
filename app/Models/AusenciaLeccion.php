<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AusenciaLeccion extends Model
{
    // Nombre real de la tabla (Laravel no lo adivinaría bien)
    protected $table = 'ausencia_lecciones';

    // Campos que se pueden llenar en bloque
    protected $fillable = [
        'ausencia_id', 'grupo_id', 'cubre_docente_id',
        'fecha', 'franja_id', 'minutos', 'anulada',
    ];

    // Conversiones automáticas de tipo
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'anulada' => 'boolean',
        ];
    }

    // La ausencia a la que pertenece esta lección
    public function ausencia(): BelongsTo
    {
        return $this->belongsTo(Ausencia::class, 'ausencia_id');
    }

    // El grupo que se quedó sin lección
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    // Quién cubrió la lección (esta persona acumula los 40 minutos)
    public function cubreDocente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'cubre_docente_id');
    }

    public function franja(): BelongsTo
    {
        return $this->belongsTo(FranjaLeccion::class, 'franja_id');
    }
}
