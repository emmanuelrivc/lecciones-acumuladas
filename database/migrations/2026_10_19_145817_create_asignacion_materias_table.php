<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignaciones_materia', function (Blueprint $table) {
            $table->id();                                                         // número único de la fila
            $table->foreignId('docente_id')->constrained('docentes');             // quién la da
            $table->foreignId('materia_id')->constrained('materias');             // qué materia
            $table->foreignId('curso_lectivo_id')->constrained('cursos_lectivos'); // en qué año
            $table->unsignedTinyInteger('nivel');                                 // nivel 1 a 6
            $table->timestamps();                                                 // fechas automáticas

            // Una misma asignación no se puede registrar dos veces
            $table->unique(['docente_id', 'materia_id', 'curso_lectivo_id', 'nivel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones_materia'); // deshacer: borra la tabla
    }
};
