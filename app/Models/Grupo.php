<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grupo extends Model
{
    protected $fillable = ['curso_lectivo_id', 'nivel', 'seccion', 'guia_id'];

    public function cursoLectivo(): BelongsTo
    {
        return $this->belongsTo(CursoLectivo::class, 'curso_lectivo_id');
    }

    public function guia(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'guia_id');
    }
}