<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsignacionMateria extends Model
{
    // Nombre real de la tabla (Laravel no lo adivinaría bien)
    protected $table = 'asignaciones_materia';

    // Campos que se pueden llenar en bloque
    protected $fillable = ['docente_id', 'materia_id', 'curso_lectivo_id', 'nivel'];

    // Cada asignación pertenece a UNA docente
    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'docente_id');
    }

    // ...a UNA materia
    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class, 'materia_id');
    }

    // ...y a UN curso lectivo
    public function cursoLectivo(): BelongsTo
    {
        return $this->belongsTo(CursoLectivo::class, 'curso_lectivo_id');
    }
}