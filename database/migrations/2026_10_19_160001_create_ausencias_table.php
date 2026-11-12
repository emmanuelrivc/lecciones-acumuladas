<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ausencias', function (Blueprint $table) {
            $table->id();                                                          // número único de la ausencia
            $table->foreignId('docente_id')->constrained('docentes');              // quién faltó
            $table->foreignId('curso_lectivo_id')->constrained('cursos_lectivos'); // en qué año escolar
            $table->foreignId('registrada_por')->constrained('users');             // quién la registró
            $table->date('fecha');                                                 // día de la ausencia
            $table->string('motivo');                                              // obligatorio: por qué faltó
            $table->boolean('aviso_previo')->default(false);                       // ¿avisó con anticipación?
            $table->text('justificacion')->nullable();                             // detalle opcional
            $table->string('estado')->default('activa');                           // 'activa' o 'anulada'
            $table->timestamps();

            // Índice: acelera buscar las ausencias de una docente por fecha
            $table->index(['docente_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ausencias');
    }
};
