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
        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_lectivo_id')->constrained('cursos_lectivos');
            $table->unsignedTinyInteger('nivel');
            $table->string('seccion', 1);
            $table->foreignId('guia_id')->nullable()->constrained('docentes');
            $table->timestamps();

            $table->unique(['curso_lectivo_id', 'nivel', 'seccion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupos');
    }
};
