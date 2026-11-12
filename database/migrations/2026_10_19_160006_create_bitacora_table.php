<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora', function (Blueprint $table) {
            $table->id();                                                  // número único
            $table->foreignId('user_id')->constrained('users');            // quién hizo la acción
            $table->string('accion');                                      // crear, editar, anular, cerrar...
            $table->string('tabla');                                       // sobre qué tabla
            $table->unsignedBigInteger('registro_id')->nullable();         // sobre qué fila de esa tabla
            $table->dateTime('fecha')->useCurrent();                       // cuándo (se llena sola)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora');
    }
};
