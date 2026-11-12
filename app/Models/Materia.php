<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Materia extends Model
{
    // Campos que se pueden llenar en bloque (protección contra "asignación masiva")
    protected $fillable = ['nombre', 'es_especial'];

    // Convierte el 0/1 de la base de datos en false/true de verdad
    protected function casts(): array
    {
        return ['es_especial' => 'boolean'];
    }
}