<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DescuentoGeneral extends Model
{
    // Nombre real de la tabla (Laravel no lo adivinaría bien)
    protected $table = 'descuentos_generales';

    // Campos que se pueden llenar en bloque
    protected $fillable = ['curso_lectivo_id', 'fecha', 'minutos', 'motivo', 'revertido'];

    // Conversiones automáticas de tipo
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'revertido' => 'boolean',
        ];
    }

    public function cursoLectivo(): BelongsTo
    {
        return $this->belongsTo(CursoLectivo::class, 'curso_lectivo_id');
    }
}
