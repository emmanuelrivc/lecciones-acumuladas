<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FranjaLeccion extends Model
{
    // Nombre real de la tabla (Laravel no lo adivinaría bien)
    protected $table = 'franjas_leccion';

    // Campos que se pueden llenar en bloque
    protected $fillable = ['numero', 'hora_inicio', 'hora_fin', 'es_receso'];

    // Convierte el 0/1 de la base de datos en false/true
    protected function casts(): array
    {
        return ['es_receso' => 'boolean'];
    }
}
