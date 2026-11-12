<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horarios', function (Blueprint $table) {
            $table->id();                                                          // número único de la fila
            $table->foreignId('docente_id')->constrained('docentes');              // quién da la lección
            $table->foreignId('grupo_id')->constrained('grupos');                  // a qué grupo
            $table->foreignId('materia_id')->constrained('materias');              // qué materia
            $table->foreignId('franja_id')->constrained('franjas_leccion');        // en qué franja del día
            $table->unsignedTinyInteger('dia_semana');                             // 1 = lunes ... 5 = viernes
            $table->date('vigente_desde');                                         // desde cuándo rige este horario
            $table->date('vigente_hasta')->nullable();                             // hasta cuándo (vacío = sigue vigente)
            $table->timestamps();

            // Índice: acelera preguntar "¿qué da esta docente este día?"
            $table->index(['docente_id', 'dia_semana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horarios');
    }
};
