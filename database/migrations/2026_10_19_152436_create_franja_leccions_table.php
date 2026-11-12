<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('franjas_leccion', function (Blueprint $table) {
            $table->id();                                   // número único de la fila
            $table->unsignedTinyInteger('numero')->unique(); // orden en el día: 1, 2, 3...
            $table->time('hora_inicio');                    // ej. 07:00
            $table->time('hora_fin');                       // ej. 07:40
            $table->boolean('es_receso')->default(false);   // true si es recreo o almuerzo
            $table->timestamps();                           // fechas automáticas
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('franjas_leccion'); // deshacer: borra la tabla
    }
};