<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ausencia_lecciones', function (Blueprint $table) {
            $table->id();                                                              // número único
            $table->foreignId('ausencia_id')->constrained('ausencias');                // a qué ausencia pertenece
            $table->foreignId('grupo_id')->constrained('grupos');                      // grupo que se quedó sin lección
            $table->foreignId('cubre_docente_id')->constrained('docentes');            // quién cubrió la lección
            $table->date('fecha');                                                     // día de la lección
            $table->foreignId('franja_id')->constrained('franjas_leccion');            // franja del día
            $table->unsignedSmallInteger('minutos')->default(40);                      // duración (una lección = 40 min)
            $table->boolean('anulada')->default(false);                                // true si se anuló (no se borra)
            $table->timestamps();

            // La misma lección no se puede registrar dos veces en la misma ausencia
            $table->unique(['ausencia_id', 'franja_id', 'grupo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ausencia_lecciones');
    }
};
