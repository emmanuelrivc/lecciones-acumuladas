<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Columna nueva: el rol de cada usuario ('directora' o 'consulta')
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol')->default('consulta')->after('email');
        });

        // Las cuentas que YA existen (la tuya) pasan a ser directora
        DB::table('users')->update(['rol' => 'directora']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('rol');
        });
    }
};
