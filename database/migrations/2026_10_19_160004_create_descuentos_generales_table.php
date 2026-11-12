<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('descuentos_generales', function (Blueprint $table) {
            $table->id();                                                          // número único
            $table->foreignId('curso_lectivo_id')->constrained('cursos_lectivos'); // en qué año escolar
            $table->date('fecha');                                                 // cuándo se aplicó
            $table->unsignedSmallInteger('minutos');                               // minutos a descontar a cada docente
            $table->string('motivo');                                              // por qué (ej. día libre por la directora)
            $table->boolean('revertido')->default(false);                          // true si se deshizo
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('descuentos_generales');
    }
};
