<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Movimiento extends Model
{
    // Campos que se pueden llenar en bloque
    protected $fillable = [
        'docente_id', 'curso_lectivo_id', 'ausencia_leccion_id',
        'descuento_general_id', 'revierte_id',
        'tipo', 'minutos', 'fecha', 'nota',
    ];

    // Conversiones automáticas de tipo
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'minutos' => 'integer',
        ];
    }

    // A quién le cambia el saldo
    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'docente_id');
    }

    public function cursoLectivo(): BelongsTo
    {
        return $this->belongsTo(CursoLectivo::class, 'curso_lectivo_id');
    }

    // La lección que originó este movimiento (si aplica)
    public function ausenciaLeccion(): BelongsTo
    {
        return $this->belongsTo(AusenciaLeccion::class, 'ausencia_leccion_id');
    }

    // El descuento general que lo originó (si aplica)
    public function descuentoGeneral(): BelongsTo
    {
        return $this->belongsTo(DescuentoGeneral::class, 'descuento_general_id');
    }

    // El movimiento que este deshace (la tabla se apunta a sí misma)
    public function revierte(): BelongsTo
    {
        return $this->belongsTo(Movimiento::class, 'revierte_id');
    }
}
