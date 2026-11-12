<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_uso', function (Blueprint $table) {
            $table->string('motivo')->nullable()->change(); // ya no es obligatorio
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_uso', function (Blueprint $table) {
            $table->string('motivo')->nullable(false)->default('')->change();
        });
    }
};
