<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos', function (Blueprint $table) {
            $table->id();                                                          // número único del movimiento
            $table->foreignId('docente_id')->constrained('docentes');              // a quién le cambia el saldo
            $table->foreignId('curso_lectivo_id')->constrained('cursos_lectivos'); // en qué año escolar

            // Origen del movimiento (solo uno aplica; los demás quedan vacíos)
            $table->foreignId('ausencia_leccion_id')->nullable()->constrained('ausencia_lecciones');
            $table->foreignId('descuento_general_id')->nullable()->constrained('descuentos_generales');

            // Si este movimiento deshace otro, apunta a él (la tabla se apunta a sí misma)
            $table->foreignId('revierte_id')->nullable()->constrained('movimientos');

            $table->string('tipo');                                                // acumulacion, uso, descuento, ajuste, reversion
            $table->integer('minutos');                                            // con signo: + suma saldo, - resta saldo
            $table->date('fecha');                                                 // fecha del movimiento
            $table->text('nota')->nullable();                                      // explicación opcional
            $table->timestamps();

            // Índice: acelera calcular el saldo de una docente en un curso
            $table->index(['docente_id', 'curso_lectivo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos');
    }
};
