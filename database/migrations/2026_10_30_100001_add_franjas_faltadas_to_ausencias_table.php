<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ausencias', function (Blueprint $table) {
            // Lecciones (franjas) en las que faltó. null = faltó todo el día (como antes).
            $table->json('franjas_faltadas')->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('ausencias', function (Blueprint $table) {
            $table->dropColumn('franjas_faltadas');
        });
    }
};
