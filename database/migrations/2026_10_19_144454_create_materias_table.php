<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('materias', function (Blueprint $table) {
            $table->id();                                   // número único de cada materia
            $table->string('nombre')->unique();             // nombre, sin repetir
            $table->boolean('es_especial')->default(true);  // ¿la imparte un especialista?
            $table->timestamps();                           // fecha de creación y de cambio
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materias');
    }
};
