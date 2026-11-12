<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_uso', function (Blueprint $table) {
            $table->id();                                                          // número único
            $table->foreignId('docente_id')->constrained('docentes');              // quién pide usar minutos
            $table->foreignId('curso_lectivo_id')->constrained('cursos_lectivos'); // en qué año escolar
            $table->unsignedSmallInteger('minutos');                               // cuántos minutos pide
            $table->date('fecha_uso');                                             // para qué día los quiere usar
            $table->string('motivo');                                              // para qué los necesita
            $table->string('estado')->default('pendiente');                        // pendiente | aprobada | rechazada
            $table->foreignId('registrada_por')->constrained('users');             // quién la anotó en el sistema
            $table->foreignId('resuelta_por')->nullable()->constrained('users');   // quién aprobó o rechazó
            $table->timestamp('resuelta_en')->nullable();                          // cuándo se resolvió
            $table->string('nota_resolucion')->nullable();                         // por qué se rechazó (u observación)
            $table->foreignId('movimiento_id')->nullable()->constrained('movimientos'); // el uso que se descontó al aprobar
            $table->timestamps();

            $table->index(['curso_lectivo_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_uso');
    }
};
