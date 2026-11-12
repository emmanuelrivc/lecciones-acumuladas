<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bitacora extends Model
{
    // Nombre real de la tabla (Laravel no lo adivinaría bien)
    protected $table = 'bitacora';

    // Esta tabla usa su propia columna "fecha", no created_at/updated_at
    public $timestamps = false;

    // Campos que se pueden llenar en bloque
    protected $fillable = ['user_id', 'accion', 'tabla', 'registro_id', 'fecha'];

    // Conversiones automáticas de tipo
    protected function casts(): array
    {
        return ['fecha' => 'datetime'];
    }

    // El usuario que realizó la acción
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
