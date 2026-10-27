<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cada partida pertenece a un año de presupuesto; lo cargado antes de este cambio queda en 2026. */
    public function up(): void
    {
        Schema::table('partidas', function (Blueprint $table) {
            $table->unsignedSmallInteger('anio')->default(2026)->index()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('partidas', function (Blueprint $table) {
            $table->dropIndex(['anio']);
            $table->dropColumn('anio');
        });
    }
};
